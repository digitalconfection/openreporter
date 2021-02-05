<?php

namespace Drupal\default_content;

use Drupal\Component\Graph\Graph;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\default_content\Event\DefaultContentEvents;
use Drupal\default_content\Event\ImportEvent;
use Drupal\default_content\Normalizer\ContentEntityNormalizerInterface;
use Drupal\file\FileInterface;
use Drupal\hal\LinkManager\LinkManagerInterface;
use Drupal\user\EntityOwnerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\Serializer;

/**
 * A service for handling import of default content.
 *
 * @todo throw useful exceptions
 */
class Importer implements ImporterInterface {

  /**
   * Defines relation domain URI for entity links.
   *
   * @var string
   */
  protected $linkDomain;

  /**
   * The serializer service.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * A list of vertex objects keyed by their link.
   *
   * @var array
   */
  protected $vertexes = [];

  /**
   * The graph entries.
   *
   * @var array
   */
  protected $graph = [];

  /**
   * The link manager service.
   *
   * @var \Drupal\hal\LinkManager\LinkManagerInterface
   */
  protected $linkManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The content file storage.
   *
   * @var \Drupal\default_content\ContentFileStorageInterface
   */
  protected $contentFileStorage;

  /**
   * The account switcher.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * The YAML normalizer.
   *
   * @var \Drupal\default_content\Normalizer\ContentEntityNormalizer
   */
  protected $contentEntityNormalizer;

  /**
   * Constructs the default content manager.
   *
   * @param \Symfony\Component\Serializer\Serializer $serializer
   *   The serializer service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\hal\LinkManager\LinkManagerInterface $link_manager
   *   The link manager service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\default_content\ContentFileStorageInterface $content_file_storage
   *   The file scanner.
   * @param string $link_domain
   *   Defines relation domain URI for entity links.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $account_switcher
   *   The account switcher.
   * @param \Drupal\default_content\Normalizer\ContentEntityNormalizerInterface $content_entity_normaler
   *   The YAML normalizer.
   */
  public function __construct(Serializer $serializer, EntityTypeManagerInterface $entity_type_manager, LinkManagerInterface $link_manager, EventDispatcherInterface $event_dispatcher, ContentFileStorageInterface $content_file_storage, $link_domain, AccountSwitcherInterface $account_switcher, ContentEntityNormalizerInterface $content_entity_normaler) {
    $this->serializer = $serializer;
    $this->entityTypeManager = $entity_type_manager;
    $this->linkManager = $link_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->contentFileStorage = $content_file_storage;
    $this->linkDomain = $link_domain;
    $this->accountSwitcher = $account_switcher;
    $this->contentEntityNormalizer = $content_entity_normaler;
  }

  /**
   * {@inheritdoc}
   */
  public function importContent($module) {
    $created = [];
    $folder = drupal_get_path('module', $module) . "/content";

    if (file_exists($folder)) {
      $root_user = $this->entityTypeManager->getStorage('user')->load(1);
      $this->accountSwitcher->switchTo($root_user);
      $file_map = [];
      foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
        $reflection = new \ReflectionClass($entity_type->getClass());
        // We are only interested in importing content entities.
        if ($reflection->implementsInterface(ConfigEntityInterface::class)) {
          continue;
        }
        if (!file_exists($folder . '/' . $entity_type_id)) {
          continue;
        }
        $files = $this->contentFileStorage->scan($folder . '/' . $entity_type_id);
        // Default content uses drupal.org as domain.
        // @todo Make this use a uri like default-content:.
        $this->linkManager->setLinkDomain($this->linkDomain);
        // Parse all of the files and sort them in order of dependency.
        foreach ($files as $file) {
          $contents = $this->parseFile($file);

          $extension = pathinfo($file->uri, PATHINFO_EXTENSION);

          // Decode the file contents.
          if ($extension == 'json') {
            $decoded = $this->serializer->decode($contents, 'hal_json');
            // Get the link to this entity.
            $item_uuid = $decoded['uuid'][0]['value'];
          }
          else {
            $decoded = Yaml::decode($contents);
            // Get the UUID to this entity.
            $item_uuid = $decoded['_meta']['uuid'];
          }

          // Throw an exception when this UUID already exists.
          if (isset($file_map[$item_uuid])) {
            // Reset link domain.
            $this->linkManager->setLinkDomain(FALSE);
            throw new \Exception(sprintf('Default content with uuid "%s" exists twice: "%s" "%s"', $item_uuid, $file_map[$item_uuid]->uri, $file->uri));
          }

          // Store the entity type with the file.
          $file->entity_type_id = $entity_type_id;
          // Store the file in the file map.
          $file_map[$item_uuid] = $file;
          // Create a vertex for the graph.
          $vertex = $this->getVertex($item_uuid);
          $this->graph[$vertex->id]['edges'] = [];
          if ($extension == 'json') {
            if (empty($decoded['_embedded'])) {
              // No dependencies to resolve.
              continue;
            }
            // Here we need to resolve our dependencies:
            foreach ($decoded['_embedded'] as $embedded) {
              foreach ($embedded as $item) {
                $uuid = $item['uuid'][0]['value'];
                $edge = $this->getVertex($uuid);
                $this->graph[$vertex->id]['edges'][$edge->id] = TRUE;
              }
            }
          }
          else {
            if (empty($decoded['_meta']['depends'])) {
              // No dependencies to resolve.
              continue;
            }
            // Here we need to resolve our dependencies:
            foreach (array_keys($decoded['_meta']['depends']) as $uuid) {
              $edge = $this->getVertex($uuid);
              $this->graph[$vertex->id]['edges'][$edge->id] = TRUE;
            }
          }
        }
      }

      // @todo what if no dependencies?
      $sorted = $this->sortTree($this->graph);
      foreach ($sorted as $link => $details) {
        if (!empty($file_map[$link])) {
          $file = $file_map[$link];
          $entity_type_id = $file->entity_type_id;
          $class = $this->entityTypeManager->getDefinition($entity_type_id)->getClass();
          $contents = $this->parseFile($file);
          $extension = pathinfo($file->uri, PATHINFO_EXTENSION);
          if ($extension == 'json') {
            $entity = $this->serializer->deserialize($contents, $class, 'hal_json', ['request_method' => 'POST']);
          }
          else {
            $entity = $this->contentEntityNormalizer->denormalize(Yaml::decode($contents));
          }

          $entity->enforceIsNew(TRUE);
          // Ensure that the entity is not owned by the anonymous user.
          if ($entity instanceof EntityOwnerInterface && empty($entity->getOwnerId())) {
            $entity->setOwner($root_user);
          }

          // If a file exists in the same folder, copy it to the designed
          // target URI.
          if ($entity instanceof FileInterface) {
            $file_source = \dirname($file->uri) . '/' . $entity->getFilename();
            if (\file_exists($file_source)) {
              $target_directory = dirname($entity->getFileUri());
              \Drupal::service('file_system')->prepareDirectory($target_directory, FileSystemInterface::CREATE_DIRECTORY);
              $new_uri = \Drupal::service('file_system')->copy($file_source, $entity->getFileUri());
              $entity->setFileUri($new_uri);
            }
          }

          $entity->save();

          $created[$entity->uuid()] = $entity;
        }
      }
      $this->eventDispatcher->dispatch(DefaultContentEvents::IMPORT, new ImportEvent($created, $module));
      $this->accountSwitcher->switchBack();
    }
    // Reset the tree.
    $this->resetTree();
    // Reset link domain.
    $this->linkManager->setLinkDomain(FALSE);
    return $created;
  }

  /**
   * Parses content files.
   *
   * @param object $file
   *   The scanned file.
   *
   * @return string
   *   Contents of the file.
   */
  protected function parseFile($file) {
    return file_get_contents($file->uri);
  }

  /**
   * Resets tree properties.
   */
  protected function resetTree() {
    $this->graph = [];
    $this->vertexes = [];
  }

  /**
   * Sorts dependencies tree.
   *
   * @param array $graph
   *   Array of dependencies.
   *
   * @return array
   *   Array of sorted dependencies.
   */
  protected function sortTree(array $graph) {
    $graph_object = new Graph($graph);
    $sorted = $graph_object->searchAndSort();
    uasort($sorted, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
    return array_reverse($sorted);
  }

  /**
   * Returns a vertex object for a given item link.
   *
   * Ensures that the same object is returned for the same item link.
   *
   * @param string $item_link
   *   The item link as a string.
   *
   * @return object
   *   The vertex object.
   */
  protected function getVertex($item_link) {
    if (!isset($this->vertexes[$item_link])) {
      $this->vertexes[$item_link] = (object) ['id' => $item_link];
    }
    return $this->vertexes[$item_link];
  }

}

<?php

namespace Drupal\twig_tweak;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\media\Plugin\media\Source\OEmbedInterface;

/**
 * URI extractor service.
 */
class UriExtractor {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a UrlExtractor object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Returns a URI to the file.
   *
   * @param object|null $input
   *   An object that contains the URI.
   *
   * @return string|null
   *   A URI that may be used to access the file.
   */
  public function extractUri(?object $input): ?string {
    if ($input instanceof ContentEntityInterface) {
      return self::getUriFromEntity($input);
    }
    elseif ($input instanceof EntityReferenceFieldItemListInterface) {
      if ($item = $input->first()) {
        return $this->getUriFromEntity($item->entity);
      }
    }
    elseif ($input instanceof EntityReferenceItem) {
      return self::getUriFromEntity($input->entity);
    }
    return NULL;
  }

  /**
   * Extracts file URI from content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity object that contains information about the file.
   *
   * @return string|null
   *   A URI that may be used to access the file.
   */
  private function getUriFromEntity(ContentEntityInterface $entity): ?string {
    if ($entity instanceof MediaInterface) {
      $source = $entity->getSource();
      $value = $source->getSourceFieldValue($entity);
      if ($source instanceof OEmbedInterface) {
        return $value;
      }
      /** @var \Drupal\file\FileInterface $file */
      $file = $this->entityTypeManager->getStorage('file')->load($value);
      if ($file) {
        return $file->getFileUri();
      }
    }
    elseif ($entity instanceof FileInterface) {
      return $entity->getFileUri();
    }
    return NULL;
  }

}

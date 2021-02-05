<?php

namespace Drupal\install_profile_generator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Serialization\Yaml;
use Drush\Drupal\ExtensionDiscovery;
use Drush\Drush;

/**
 * A profile object to manage the creation of the new install profile.
 *
 * @internal
 *   Install profile generator's API are the Drush commands.
 */
class Profile {

  /**
   * The profile's machine name.
   *
   * @var string
   */
  protected $machineName;

  /**
   * The profile's human readable name.
   *
   * @var string
   */
  protected $name;

  /**
   * The profile's description.
   *
   * @var string
   */
  protected $description;

  /**
   * Drupal application's root directory.
   *
   * @var string
   */
  protected $appRoot;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * ProfileCreator constructor.
   *
   * @param string $machine_name
   *   The profile's machine name.
   * @param string $name
   *   The profile's human readable name.
   * @param string $description
   *   The profile's description.
   * @param string $app_root
   *   Drupal application's root directory.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration object factory.
   */
  public function __construct($machine_name, $name, $description, $app_root, FileSystemInterface $file_system, ConfigFactoryInterface $config_factory) {
    $this->machineName = $machine_name;
    $this->name = $name;
    $this->description = $description;
    $this->appRoot = $app_root;
    $this->fileSystem = $file_system;
    $this->configFactory = $config_factory;
  }

  /**
   * Creates a new profile.
   *
   * @return $this
   *
   * @throws \Exception
   */
  public function create() {
    // Create the profile directory.
    $profile_path = $this->getProfilePath();
    if (!$this->fileSystem->mkdir($profile_path)) {
      throw new \Exception(dt('Could not create @profile_path directory', ['@profile_path' => $profile_path]));
    }

    // Create the profile .info.yml.
    $info = [
      'name' => $this->name,
      'type' => 'profile',
      'description' => $this->description,
      // @todo - why is this not \Drupal::version?
      'core' => '8.x',
      'core_version_requirement' => '^8 || ^9',
    ];
    if (!file_put_contents("$profile_path/{$this->machineName}.info.yml", Yaml::encode($info))) {
      throw new \Exception(dt('Could not write @profile_path/@profile_name.info.yml', ['@profile_path' => $profile_path, '@profile_name' => $this->machineName]));
    }

    // Create profile's config/sync directory.
    if (!$this->fileSystem->mkdir($profile_path . '/config/sync', NULL, TRUE)) {
      throw new \Exception(dt('Could not create @config_sync directory', ['@@config_sync' => $profile_path . '/config/sync']));
    }
    return $this;
  }

  /**
   * Exports configuration to the profile using Drush's config-export command.
   *
   * @return $this
   *
   * @throws \Exception
   */
  public function writeConfig() {
    $self = Drush::service('site.alias.manager')->getSelf();

    $process = Drush::drush($self, 'config-export', [], ['destination' => $this->getProfilePath() . '/config/sync']);

    try {
      // Run a full configuration export to the profile's config/sync directory.
      $process->mustRun();
    } catch (\Exception $e) {
      throw new \Exception(dt('Could not export active config to @config_sync directory', ['@@config_sync' => $this->getProfilePath() . '/config/sync']));
    }

    return $this;
  }

  /**
   * Installs the install profile by writing to core.extension.
   *
   * @return $this
   *
   * @throws \Exception
   */
  public function install() {

    // Reset the static cache for discovered files.
    ExtensionDiscovery::reset();

    /** @var \Drupal\Core\Extension\ProfileExtensionList $extension_list */
    $extension_list = \Drupal::service('extension.list.profile');

    // Reset the stored extension list.
    $extension_list->reset();

    // Change the site to use the new installation profile.
    $extension_config = $this->configFactory->getEditable('core.extension');
    $current_profile = $extension_config->get('profile');
    $extension_config
      // Change the current profile to the generator profile.
      ->set('profile', $this->machineName)
      // Uninstall the Install Profile Generator module - it is a one time
      // thing.
      ->clear('module.install_profile_generator')
      // Uninstall the current install profile.
      ->clear('module.' . $current_profile)
      // Install the current install profile. It will automatically go at the
      // end.
      ->set('module.' . $this->machineName, 1000)
      ->save();

    // Make the same changes to the already exported configuration. We do it
    // this way around so that we can be sure the configuration export and the
    // core.extension update is successful.
    $exported_config = new FileStorage($this->getProfilePath() . '/config/sync');
    if (!$exported_config->write('core.extension', $extension_config->get())) {
      throw new \Exception(
        dt('Could not write exported configuration to @config_sync directory', ['@config_sync' => $this->getProfilePath() . '/config/sync'])
      );
    }
    return $this;
  }

  /**
   * Gets the path to the profile directory.
   *
   * @return string
   *   Path to the profile directory.
   */
  protected function getProfilePath() {
    return $this->appRoot . '/profiles/' . $this->machineName;
  }

}

<?php

namespace Drupal\install_profile_generator\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\install_profile_generator\Profile;

/**
 * Creates Profile objects.
 *
 * @internal
 *   Install profile generator's API are the Drush commands.
 */
class ProfileFactory {

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
   * @param string $app_root
   *   App root service.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct($app_root, FileSystemInterface $file_system, ConfigFactoryInterface $config_factory) {
    $this->appRoot = $app_root;
    $this->fileSystem = $file_system;
    $this->configFactory = $config_factory;
  }

  /**
   * Creates a new profile object.
   *
   * @param string $machine_name
   *   The machine name for the profile.
   * @param string $name
   *   The human readable name for the profile.
   * @param string $description
   *   The profile's description.
   *
   * @return \Drupal\install_profile_generator\Profile
   *   Constructed Profile object.
   */
  public function create($machine_name, $name, $description) {
    return new Profile($machine_name, $name, $description, $this->appRoot, $this->fileSystem, $this->configFactory);
  }

}

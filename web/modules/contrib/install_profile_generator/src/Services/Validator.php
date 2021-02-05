<?php

namespace Drupal\install_profile_generator\Services;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Language\LanguageInterface;

/**
 * Class InstallProfileGenerator.
 *
 * @internal
 *   Install profile generator's API are the Drush commands.
 */
class Validator {

  /**
   * Application root.
   *
   * @var string
   */
  protected $appRoot;

  /**
   * Module Handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Theme Handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * Transliteration service.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected $transliteration;

  /**
   * The install profile.
   *
   * @var string
   */
  protected $installProfile;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * Validator constructor.
   *
   * @param string $app_root
   *   App root service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   Theme handler service.
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
   *   Transliteration service.
   * @param string $install_profile
   *   The install profile.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list.
   */
  public function __construct($app_root, ModuleHandlerInterface $module_handler, ThemeHandlerInterface $theme_handler, TransliterationInterface $transliteration, $install_profile, ModuleExtensionList $module_extension_list) {
    $this->appRoot = $app_root;
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
    $this->transliteration = $transliteration;
    $this->installProfile = $install_profile;
    $this->moduleExtensionList = $module_extension_list;
  }

  /**
   * Converts a name to a valid machine name.
   *
   * @param string $name
   *   A name to turn into a valid machine name.
   *
   * @return string
   *   A valid machine name.
   */
  public function convertToMachineName($name) {
    $new_value = $this->transliteration->transliterate($name, LanguageInterface::LANGCODE_DEFAULT, '_');
    $new_value = strtolower($new_value);
    $new_value = preg_replace('/[^a-z0-9_]+/', '_', $new_value);
    return preg_replace('/_+/', '_', $new_value);
  }

  /**
   * Validate whether we should proceed with generation of install profile.
   *
   * @param string $name
   *   Name of the new install profile.
   * @param string $machine_name
   *   Machine name of the new install profile.
   *
   * @throws \Exception
   *   Thrown when $name, $machine_name or the environment fails validation.
   */
  public function validate($name, $machine_name) {
    // Check if modules/theme exist in current profile folder.
    if ($this->extensionInCurrentProfile()) {
      throw new \Exception(dt('The current profile contains extensions. It is not possible to generate a new profile using Drush.'));
    }

    // Ensure we have a name.
    if (empty($machine_name) || empty($name) || $machine_name === TRUE || $name === TRUE) {
      throw new \Exception(dt('To generate a new profile using Drush you have to provide a name or a machine name for the new profile.'));
    }

    // Ensure we have a valid machine name.
    if ($machine_name !== $this->convertToMachineName($machine_name)) {
      throw new \Exception(dt('To generate a new profile using Drush you have to provide a valid machine name. Can only contain lowercase letters, numbers, and underscores.'));
    }

    // Ensure we won't create a profile with the same name as an existing
    // extension.
    $modules = $this->moduleExtensionList->reset()->getList();
    $themes = $this->themeHandler->rebuildThemeData();
    if (isset($modules[$machine_name]) || isset($themes[$machine_name])) {
      throw new \Exception(dt('The machine name @machine_name already exists', ['@machine_name' => $machine_name]));
    }

    // Ensure that the /profiles directory can be written too.
    if (!is_writable($this->appRoot . '/profiles')) {
      throw new \Exception(dt('Can not write to the @directory directory', ['@directory' => $this->appRoot . '/profiles']));
    }
  }

  /**
   * Checks if the current installation profile contains modules or themes.
   *
   * @return bool
   *   TRUE - current installation profile contains modules or themes.
   *   FALSE - current installation profile does not contains modules or themes.
   */
  protected function extensionInCurrentProfile() {
    $has_extension_in_current_profile = FALSE;
    $modules = $this->moduleHandler->getModuleList();
    $profile_path = $modules[$this->installProfile]->getPath();

    unset($modules[$this->installProfile]);
    foreach ($modules as $module) {
      if (strpos($module->getPath(), $profile_path) === 0) {
        $has_extension_in_current_profile = TRUE;
      }
    }

    $themes = $this->themeHandler->listInfo();
    foreach ($themes as $theme) {
      if (strpos($theme->getPath(), $profile_path) === 0) {
        $has_extension_in_current_profile = TRUE;
      }
    }
    return $has_extension_in_current_profile;
  }

}

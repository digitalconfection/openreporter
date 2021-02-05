<?php

namespace Drupal\comment_notify;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\user\UserDataInterface;

/**
 * Defines the Comment notify user settings.
 */
class UserNotificationSettings {

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserData
   */
  protected $userData;

  /**
   * Config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * UserSettings constructor.
   *
   * @param \Drupal\user\UserDataInterface $userData
   *   The user data service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory;
   */
  public function __construct(UserDataInterface $userData, ConfigFactoryInterface $configFactory) {
    $this->userData = $userData;
    $this->configFactory = $configFactory;
  }

  /**
   * Get the notification preferences for a specific user.
   *
   * @param int $uid
   *   The user id.
   *
   * @return array|null
   *   array if found, else NULL
   */
  public function getSettings($uid) {
    //$users = &drupal_static(__FUNCTION__);
    if (!isset($users[$uid])) {
      if (is_null($uid)) {
        throw new \Exception('Cannot get user preference, uid missing');
      }

      // Handle anonymous users with defaults.
      if ($uid == 0) {
        $users[0] = $this->getDefaultSettings();
      }
      else {
        $settings = $this->userData->get('comment_notify', $uid);
        $users[$uid] = empty($settings) ? NULL : $settings;
      }
    }
    return $users[$uid];
  }

  /**
   * Returns the default values of the user notification settings.
   */
  public function getDefaultSettings() {
    $config = $this->configFactory->get('comment_notify.settings');
    return [
      'comment_notify' => $config->get('enable_default.watcher'),
      'entity_notify' => $config->get('enable_default.entity_author'),
    ];
  }

  /**
   * Remove comment notification preferences for a user.
   *
   * @param int $uid
   *   The user id.
   */
  public function deleteSettings($uid) {
    return $this->userData->delete('comment_notify', $uid);
  }

  /**
   * Get a user's default preference.
   *
   * @param $uid
   * @param $setting
   *   Possible values: 'comment_notify', 'entity_notify'
   *
   * @return string
   */
  public function getSetting($uid, $setting) {
    $settings = $this->getSettings($uid);
    if (!$settings) {
      $settings = $this->getDefaultSettings();
    }
    return $settings[$setting];
  }

  /**
   * Sets the notification preferences for a specific user.
   *
   * @param int $uid
   *   The User ID.
   * @param int $entity_notification
   *   The entity notification value.
   * @param int $comment_notification
   *   The comment notification value.
   */
  function saveSettings($uid, $entity_notification = NULL, $comment_notification = NULL) {
    if (!$uid) {
      throw new \Exception('Cannot set user preference, uid missing');
    }
    if (!is_null($entity_notification)) {
      $this->userData->set('comment_notify', $uid, 'entity_notify', $entity_notification);
    }
    if (!is_null($comment_notification)) {
      $this->userData->set('comment_notify', $uid, 'comment_notify', $comment_notification);
    }
  }

}

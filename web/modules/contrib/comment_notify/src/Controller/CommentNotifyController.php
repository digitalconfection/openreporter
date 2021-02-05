<?php

namespace Drupal\comment_notify\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Default controller for the comment_notify module.
 */
class CommentNotifyController extends ControllerBase {

  /**
   * Creates a page for disabling notifications.
   *
   * @param string $hash
   *   A hash identifying the notification entry to disable.
   *
   * @return array
   *   A renderable array.
   */
  public function disable($hash) {
    module_load_include('inc', 'comment_notify', 'comment_notify');
    if (comment_notify_unsubscribe_by_hash($hash)) {
      return ['#markup' => $this->t('Your comment follow-up notification for this post was disabled. Thanks.')];
    }
    else {
      return ['#markup' => $this->t('Sorry, there was a problem unsubscribing from notifications.')];
    }
  }

}

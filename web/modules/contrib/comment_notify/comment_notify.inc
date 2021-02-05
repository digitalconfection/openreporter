<?php

/**
 * @file
 * Contains functions which utilize the database and other internal helpers.
 */

use Drupal\comment\CommentInterface;

/**
 * Add a notification against a comment.
 *
 * @param int $cid
 *   Comment Id.
 * @param int $notify
 *   The notification type.
 * @param string $notify_hash
 *   The comment hash.
 * @param int|null $notified
 *   If the user has been already notified.
 *
 * @return bool
 *   TRUE if the notification was added correctly.
 */
function comment_notify_add_notification($cid, $notify, $notify_hash, $notified) {
  // Check if comment already exist.
  $results = \Drupal::database()->select('comment_notify', 'cn')
    ->fields('cn', ['cid'])
    ->condition('cn.cid', $cid)
    ->execute()
    ->fetchField();

  // Update comment if exist.
  if ($results) {
    return (bool) \Drupal::database()->update('comment_notify')
      ->fields([
        'notify' => $notify === NULL ? 0 : $notify,
        'notify_hash' => $notify_hash,
        'notified' => $notified === NULL ? 0 : $notified,
      ])
      ->condition('cid', $cid)
      ->execute();
  }

  // Create new entry.
  else {
    return (bool) \Drupal::database()->insert('comment_notify')
      ->fields([
        'cid' => $cid,
        'notify' => $notify === NULL ? 0 : $notify,
        'notify_hash' => $notify_hash,
        'notified' => $notified === NULL ? 0 : $notified,
      ])
      ->execute();
  }
}

/**
 * Remove all the notifications linked with a comment.
 *
 * @param int $cid
 *   The comment ID.
 *
 * @return bool
 *   TRUE if all the notifications were removed correctly.
 */
function comment_notify_remove_all_notifications($cid) {
  return (bool) \Drupal::database()->delete('comment_notify')
    ->condition('cid', $cid)
    ->execute();
}

/**
 * Updated a notification with a different notification type.
 *
 * @param int $cid
 *   The comment id.
 * @param int $notify
 *   The value that is going to be updated.
 *
 * @return bool
 *   TRUE if the notification was updated correctly.
 */
function comment_notify_update_notification($cid, $notify) {
  return (bool) \Drupal::database()->update('comment_notify')
    ->fields([
      'notify' => $notify === NULL ? 0 : $notify,
    ])
    ->condition('cid', $cid)
    ->execute();
}

/**
 * Get the type of notification for a comment notification record.
 *
 * @param int $cid
 *   The comment id.
 *
 * @return int
 *   Return the notification type.
 */
function comment_notify_get_notification_type($cid) {
  return \Drupal::database()->select('comment_notify', 'cn')
    ->fields('cn', ['notify'])
    ->condition('cid', $cid)
    ->execute()
    ->fetchField();
}

/**
 * Get a list of mails which need to be contacted for an entity.
 *
 * @param int $entity_id
 *   The entity id.
 * @param string $comment_type
 *   The comment type.
 *
 * @return \Drupal\comment\CommentInterface[]
 *   A list of comment entities.
 */
function comment_notify_get_watchers($entity_id, $comment_type) {
  $cids = \Drupal::database()->query("SELECT c.cid FROM {comment_field_data} c INNER JOIN {comment_notify} cn ON c.cid = cn.cid LEFT JOIN {users_field_data} u ON c.uid = u.uid WHERE c.entity_id = :entity_id AND c.comment_type = :comment_type AND c.status = :status AND cn.notify <> :notify AND (u.uid = 0 OR u.status = 1)", [
    ':entity_id' => $entity_id,
    ':comment_type' => $comment_type,
    ':status' => CommentInterface::PUBLISHED,
    ':notify' => COMMENT_NOTIFY_DISABLED,
  ])->fetchCol();
  return \Drupal::entityTypeManager()->getStorage('comment')->loadMultiple($cids);
}

/**
 * Records that the owner of a comment notification request has been notified.
 *
 * @param int $comment
 *   The comment ID.
 *
 * @return bool
 *   True if it can be updated correctly.
 */
function comment_notify_mark_comment_as_notified($comment) {
  // First, mark the passed comment (an object, so passed by reference).
  $comment->notified = 1;

  // Next, store this fact in the DB as well.
  return (bool) \Drupal::database()->update('comment_notify')
    ->fields([
      'notified' => 1,
    ])
    ->condition('cid', $comment->id())
    ->execute();
}

/**
 * Unsubscribe all comment notification requests associated with an email.
 *
 * If the email belongs to a user, it will unsubscribe all of their Comment
 * Notify records. If it does not, then it will unsubscribe all anonymous users.
 *
 * @param string $mail
 *   The mail that is going to be unsubscribed.
 *
 * @return bool
 *   TRUE if the comment was unsubscribed correctly.
 */
function comment_notify_unsubscribe_by_email($mail) {
  $comment_query = \Drupal::entityQuery('comment');
  if ($user = user_load_by_mail($mail)) {
    $comment_query->condition('uid', $user->id());
  }
  else {
    $comment_query->condition('mail', $mail);
  }

  $comments = $comment_query->execute();

  if (empty($comments)) {
    return FALSE;
  }

  $update_query = \Drupal::database()->update('comment_notify');
  $update_query->fields(['notify' => 0]);
  $update_query->condition('cid', $comment_query->execute(), 'IN');

  return (bool) $update_query->execute();
}

/**
 * Unsubscribe comment notification requests associated with a hash.
 *
 * This is used in the unsubscribe link.
 *
 * @param string $hash
 *   The hash that identified the comment.
 *
 * @return bool
 *   Returns TRUE if the comment was unsubscribed correctly, FALSE otherwise.
 */
function comment_notify_unsubscribe_by_hash($hash) {
  $query = \Drupal::database()->select('comment_notify', 'cn');
  $query->join('comment_field_data', 'cf', 'cn.cid = cf.cid');
  $query->condition('cn.notify_hash', $hash)
    ->condition('cn.notify', COMMENT_NOTIFY_DISABLED, '!=')
    ->fields('cn', ['cid', 'notify', 'notified'])
    ->fields('cf', ['entity_id', 'entity_type', 'uid'])
    ->execute()->fetchObject();
  $notification = $query->execute()->fetchObject();

  if (empty($notification)) {
    return FALSE;
  }

  // If this notification is at the entity level and the commenter has a Drupal
  // account, delete all notifications for this entity.
  if (COMMENT_NOTIFY_ENTITY == $notification->notify && $notification->uid) {
    $result = \Drupal::database()->query("SELECT cid FROM {comment_field_data} WHERE entity_id = :entity_id AND entity_type = :entity_type AND uid = :uid", [
      ':entity_id' => $notification->entity_id,
      ':entity_type' => $notification->entity_type,
      ':uid' => $notification->uid,
    ]);
    $cids = $result->fetchCol();

    // Update all comment notifications to be disabled.
    return (bool) \Drupal::database()->update('comment_notify')
      ->fields([
        'notify' => 0,
      ])
      ->condition('cid', $cids, 'IN')
      ->execute();
  }
  else {
    // Update this notification to be disabled.
    return (bool) \Drupal::database()->update('comment_notify')
      ->fields([
        'notify' => 0,
      ])
      ->condition('notify_hash', $hash)
      ->execute();
  }
}

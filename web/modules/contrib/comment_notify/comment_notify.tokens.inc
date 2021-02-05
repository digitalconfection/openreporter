<?php

/**
 * @file
 * Builds placeholder replacement tokens for comment_notify.module.
 */

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function comment_notify_token_info() {
  // Comment tokens.
  $info['tokens']['comment']['unsubscribe-url'] = [
    'name' => t('Unsubscribe URL'),
    'description' => t('The URL to disable notifications for the comment.'),
    'type' => 'url',
  ];

  // Comment subscriber token type (extends the comment token type).
  $info['types']['comment-subscribed'] = [
    'name' => t('Subscribed comment'),
    'description' => t('Tokens related to a comment that is subscribed to new comments.'),
    'type' => 'comment',
  ];

  return $info;
}

/**
 * Implements hook_tokens().
 */
function comment_notify_tokens($type, $tokens, array $data = [], array $options = [], BubbleableMetadata $bubbleable_metadata) {
  $url_options = ['absolute' => TRUE];
  if (isset($options['language'])) {
    $url_options['language'] = $options['language'];
    $language_code = $options['language']->language;
  }
  else {
    $language_code = NULL;
  }
  $sanitize = !empty($options['sanitize']);

  $replacements = [];

  if ($type == 'comment' && !empty($data['comment'])) {
    $comment = $data['comment'];

    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'unsubscribe-url':
          if ($unsubscribe_url = comment_notify_get_unsubscribe_url($comment)) {
            $replacements[$original] = $unsubscribe_url;
          }
          break;
      }
    }

    // [comment:unsubscribe-url:*] chained token replacements.
    if (($unsubscribe_url_tokens = \Drupal::token()->findWithPrefix($tokens, 'unsubscribe-url')) && $unsubscribe_url = comment_notify_get_unsubscribe_url($comment)) {
      $replacements += \Drupal::token()->generate('url', $unsubscribe_url_tokens, ['path' => $unsubscribe_url], $options, $bubbleable_metadata);
    }
  }

  // Comment subscriber tokens (pass through to comment token replacement).
  if ($type == 'comment-subscribed' && !empty($data['comment-subscribed'])) {
    $replacements += \Drupal::token()->generate('comment', $tokens, ['comment' => $data['comment-subscribed']], $options, $bubbleable_metadata);
  }

  return $replacements;
}

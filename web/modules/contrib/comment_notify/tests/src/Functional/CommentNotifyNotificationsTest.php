<?php

namespace Drupal\Tests\comment_notify\Functional;

use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests that all the notifications are sent as expected.
 *
 * @group comment_notify
 */
class CommentNotifyNotificationsTest extends CommentNotifyTestBase {

  use TaxonomyTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'comment_notify',
    'node',
    'comment',
    'token',
    'taxonomy',
  ];

  /**
   * The permissions required by the tests.
   *
   * @var array
   */
  protected $permissions = [
    'access comments',
    'access content',
    'edit own comments',
    'post comments',
    'skip comment approval',
    'subscribe to comments',
  ];

  /**
   * Tests that the Mail notification is sent properly and it is only send once.
   */
  public function testCommentNotification() {
    $user1 = $this->drupalCreateUser($this->permissions);
    /** @var \Drupal\comment_notify\UserNotificationSettings $user_settings */
    $this->container->get('comment_notify.user_settings')->saveSettings($user1->id(), COMMENT_NOTIFY_ENTITY, COMMENT_NOTIFY_COMMENT);
    $user2 = $this->drupalCreateUser($this->permissions);
    $node = $this->drupalCreateNode(
      [
        'type' => 'article',
        'uid' => $user1,
      ]
    );
    $this->drupalLogin($user2);
    $comment = $this->postComment(
      $node->toUrl()->toString(),
      $this->randomMachineName(),
      $this->randomMachineName(),
      ['notify' => TRUE, 'notify_type' => COMMENT_NOTIFY_ENTITY]
    );
    // Test that the notification was sent.
    $this->assertMail('to', $user1->getEmail(), t('Message was sent to the user.'));
    $this->container->get('state')->set('system.test_mail_collector', []);

    // Edit the comment, no notification must be sent.
    $this->drupalGet('comment/' . $comment['id'] . '/edit');
    $this->getSession()->getPage()->fillField(t('Comment'), $this->randomMachineName());
    $this->getSession()->getPage()->pressButton(t('Save'));
    $captured_emails = $this->container->get('state')->get('system.test_mail_collector');
    $this->assertEmpty($captured_emails, 'No notifications has been sent.');
    $this->drupalLogout();

  }

  /**
   * Tests the notifications are sent correctly with multiple comment types.
   */
  public function testCommentTypeNotification() {
    // Add a second comment type.
    $this->addDefaultCommentField('node', 'article', 'field_comment', CommentItemInterface::OPEN, 'comment_type_2');
    /** @var \Drupal\Core\Config\Config $config */
    $config = $this->container->get('config.factory')->getEditable('comment_notify.settings');
    $config->set('bundle_types', ['node--article--comment', 'node--article--field_comment']);
    $config->save();
    $user1 = $this->drupalCreateUser($this->permissions);
    $user2 = $this->drupalCreateUser($this->permissions);

    $node = $this->drupalCreateNode(
      [
        'type' => 'article',
        'uid' => $this->adminUser,
      ]
    );

    // Comment of the comment type 1.
    $comment1 = Comment::create([
      'comment_type' => 'comment',
      'langcode' => 'und',
      'entity_id' => $node->id(),
      'entity_type' => $node->getEntityTypeId(),
      'uid' => $user1->id(),
      'subject' => $this->randomMachineName(),
      'status' => 1,
      'field_name' => 'comment',
      'comment_body' => [
        'summary' => '',
        'value' => $this->randomMachineName(),
        'format' => 'basic_html',
      ],
    ]);
    $comment1->save($comment1);
    $notify_hash = \Drupal::csrfToken()->get('127.0.0.1' . $comment1->id());
    comment_notify_add_notification($comment1->id(), TRUE, $notify_hash, 1);

    // Comment of the comment type 2.
    $comment2 = Comment::create([
      'comment_type' => 'comment_type_2',
      'langcode' => 'und',
      'entity_id' => $node->id(),
      'entity_type' => $node->getEntityTypeId(),
      'uid' => $user2->id(),
      'subject' => $this->randomMachineName(),
      'status' => 1,
      'field_name' => 'field_comment',
      'comment_body' => [
        'summary' => '',
        'value' => $this->randomMachineName(),
        'format' => 'basic_html',
      ],
    ]);
    $comment2->save($comment2);
    $notify_hash = \Drupal::csrfToken()->get('127.0.0.1' . $comment1->id());
    comment_notify_add_notification($comment2->id(), TRUE, $notify_hash, 1);

    $this->assertEmpty($this->getMails(), 'No notifications has been sent.');

    // User 1 reply user 2 in comment type 2, user 2 should get a notification.
    $this->drupalLogin($user1);
    $this->postComment(
      "/comment/reply/node/{$node->id()}/field_comment/{$comment2->id()}",
      $this->randomMachineName(),
      $this->randomMachineName(),
      ['notify' => FALSE, 'notify_type' => COMMENT_NOTIFY_COMMENT]
    );
    $this->drupalLogout();
    $this->assertMail('to', $user2->getEmail(), t('Message was sent to the user2 user.'));
    $this->container->get('state')->set('system.test_mail_collector', []);

    // User 2 reply user 1 in comment type 1, user 1 should get a notification.
    $this->drupalLogin($user2);
    $this->postComment(
      "/comment/reply/node/{$node->id()}/comment/{$comment1->id()}",
      $this->randomMachineName(),
      $this->randomMachineName(),
      ['notify' => FALSE, 'notify_type' => COMMENT_NOTIFY_COMMENT]
    );
    $this->drupalLogout();
    $this->assertMail('to', $user1->getEmail(), t('Message was sent to the user1 user.'));
    $this->container->get('state')->set('system.test_mail_collector', []);

  }

  /**
   * Tests that the notifications are working on a different entity than a node.
   */
  public function testEntityNotification() {
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->createVocabulary();
    $this->addDefaultCommentField('taxonomy_term', $vocabulary->id(), 'field_comment_taxonomy', CommentItemInterface::OPEN, 'comment_type_2');
    $comment_field = FieldConfig::loadByName('taxonomy_term', $vocabulary->id(), 'field_comment_taxonomy');
    $comment_field->setSetting('anonymous', CommentInterface::ANONYMOUS_MAY_CONTACT);
    $comment_field->save();

    /** @var \Drupal\Core\Config\Config $config */
    $config = $this->container->get('config.factory')->getEditable('comment_notify.settings');
    $config->set('bundle_types', ['taxonomy_term--' . $vocabulary->id() . '--field_comment_taxonomy']);
    $config->save();

    // Allow anonymous users to post comments and get notifications.
    user_role_grant_permissions(
      AccountInterface::ANONYMOUS_ROLE,
      [
        'access comments',
        'access content',
        'post comments',
        'skip comment approval',
        'subscribe to comments',
      ]
    );

    $permissions = array_merge($this->permissions, ['access user profiles']);
    $user1 = $this->drupalCreateUser($permissions);
    $this->container->get('comment_notify.user_settings')->saveSettings($user1->id(), COMMENT_NOTIFY_DISABLED, COMMENT_NOTIFY_ENTITY);
    $user2 = $this->drupalCreateUser($permissions);
    $this->container->get('comment_notify.user_settings')->saveSettings($user2->id(), COMMENT_NOTIFY_DISABLED, COMMENT_NOTIFY_COMMENT);

    $term = $this->createTerm($vocabulary);
    $term2 = $this->createTerm($vocabulary);

    // User1 Should get notification for any new comment on the entity.
    $this->drupalLogin($user1);
    $this->postComment(
      $term->toUrl()->toString(),
      $this->randomMachineName(),
      $this->randomMachineName(),
      ['notify' => TRUE, 'notify_type' => COMMENT_NOTIFY_ENTITY]
    );
    $this->drupalLogout();
    $this->postComment(
      $term->toUrl()->toString(),
      $this->randomMachineName(),
      $this->randomMachineName(),
      ['notify' => FALSE, 'notify_type' => COMMENT_NOTIFY_ENTITY],
      ['name' => $this->randomMachineName(), 'mail' => $this->getRandomEmailAddress()]
    );
    $this->assertMail('to', $user1->getEmail(), t('Message was sent to the user1 user.'));
    $this->container->get('state')->set('system.test_mail_collector', []);

    // User 2 should get a notification only when someone reply to its comment.
    $this->drupalLogin($user2);
    $comment = $this->postComment(
      $term2->toUrl()->toString(),
      $this->randomMachineName(),
      $this->randomMachineName(),
      ['notify' => TRUE, 'notify_type' => COMMENT_NOTIFY_COMMENT]
    );
    $this->drupalLogout();
    $this->postComment(
      $term2->toUrl()->toString(),
      $this->randomMachineName(),
      $this->randomMachineName(),
      ['notify' => FALSE, 'notify_type' => COMMENT_NOTIFY_ENTITY],
      ['name' => $this->randomMachineName(), 'mail' => $this->getRandomEmailAddress()]
    );
    $this->assertEmpty($this->getMails(), 'No notification was sent');
    $this->drupalGet('/comment/reply/taxonomy_term/' . $term2->id() . '/field_comment_taxonomy/' . $comment['id']);
    // /comment/reply/taxonomy_term/1/field_comment_taxonomy/2.
    $this->postComment(
      '/comment/reply/taxonomy_term/' . $term2->id() . '/field_comment_taxonomy/' . $comment['id'],
      $this->randomMachineName(),
      $this->randomMachineName(),
      ['notify' => FALSE, 'notify_type' => COMMENT_NOTIFY_ENTITY],
      ['name' => $this->randomMachineName(), 'mail' => $this->getRandomEmailAddress()]
    );
    $this->assertMail('to', $user2->getEmail(), t('Message was sent to the user1 user.'));
    $this->container->get('state')->set('system.test_mail_collector', []);
  }

}

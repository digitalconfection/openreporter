<?php

namespace Drupal\Tests\comment_notify\Functional;

use Drupal\Core\Session\AccountInterface;

/**
 * Comment notifications tests as anonymous user.
 *
 * @group comment_notify
 */
class CommentNotifyAnonymousTest extends CommentNotifyTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

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
  }

  /**
   * Tests that the mail is required for anonymous users.
   */
  public function testMail() {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $this->drupalCreateNode(['type' => 'article']);

    $subscribe = ['notify' => TRUE, 'notify_type' => COMMENT_NOTIFY_ENTITY];
    $this->drupalGet($node->toUrl());
    $this->postComment($node->toUrl()->toString(), $this->randomMachineName(), $this->randomMachineName(), $subscribe);
    $this->assertTrue($this->getSession()->getPage()->hasContent(t('If you want to subscribe to comments you must supply a valid e-mail address.')));
  }

  /**
   * Tests the "All comments" notification option used by an anonymous user.
   */
  public function testAnonymousAllCommentsTest() {
    /** @var \Drupal\node\Entity\Node $node */
    $node = $this->drupalCreateNode(['type' => 'article']);
    $subscribe = ['notify' => TRUE, 'notify_type' => COMMENT_NOTIFY_ENTITY];
    $contact = ['name' => $this->randomMachineName(), 'mail' => $this->getRandomEmailAddress()];
    $comment = $this->postComment(
      $node->toUrl()->toString(),
      $this->randomMachineName(),
      $this->randomMachineName(),
      $subscribe,
      $contact
    );

    // Confirm that the notification is saved.
    $result = comment_notify_get_notification_type($comment['id']);
    $this->assertEquals($result, $subscribe['notify_type'], 'All Comments option was saved properly.');

    // Tests that the user receives the email if a new comment is posted.
    $this->postComment(
      $node->toUrl()->toString(),
      $this->randomMachineName(),
      $this->randomMachineName(),
      ['notify' => FALSE, 'notify_type' => COMMENT_NOTIFY_ENTITY],
      ['name' => $this->randomMachineName(), 'mail' => $this->getRandomEmailAddress()]
    );
    $this->assertMail('to', $contact['mail'], t('Message was sent to the anonymous user.'));

    // Test the unsubscribe link.
    $mails = $this->getMails();
    preg_match("/\/comment_notify\/disable\/.+/", $mails[0]['body'], $output);
    $this->drupalGet($output[0]);
    $this->assertTrue($this->getSession()->getPage()->hasContent("Your comment follow-up notification for this post was disabled. Thanks."));
    // Confirm that the notification has been disabled.
    $result = comment_notify_get_notification_type($comment['id']);
    $this->assertEquals($result, COMMENT_NOTIFY_DISABLED, 'The notification has been disabled');
    // Tests that the user stopped receiving notifications.
    $this->container->get('state')->set('system.test_mail_collector', []);
    $this->postComment(
      $node->toUrl()->toString(),
      $this->randomMachineName(),
      $this->randomMachineName(),
      ['notify' => FALSE, 'notify_type' => COMMENT_NOTIFY_ENTITY],
      ['name' => $this->randomMachineName(), 'mail' => $this->getRandomEmailAddress()]
    );
    $captured_emails = $this->container->get('state')->get('system.test_mail_collector');
    $this->assertEmpty($captured_emails, 'No notifications has been sent.');
  }

  /**
   * Tests the "Replies to my comment" option used by anonymous user.
   */
  public function testAnonymousRepliesTest() {
    // Create a node with comments allowed.
    $node = $this->drupalCreateNode(['type' => 'article']);
    $subscribe = ['notify' => TRUE, 'notify_type' => COMMENT_NOTIFY_COMMENT];
    $contact = ['name' => $this->randomMachineName(), 'mail' => $this->getRandomEmailAddress()];
    $comment = $this->postComment($node->toUrl()->toString(), $this->randomMachineName(), $this->randomMachineName(), $subscribe, $contact);

    // Confirm that the notification is saved.
    $result = comment_notify_get_notification_type($comment['id']);
    $this->assertEquals($result, $subscribe['notify_type'], 'Replies to my comment option was saved properly.');

    // Tests that the user doesn't receive a mail if a new comment is posted.
    $this->postComment(
      $node->toUrl()->toString(),
      $this->randomMachineName(),
      $this->randomMachineName(),
      ['notify' => FALSE, 'notify_type' => COMMENT_NOTIFY_ENTITY],
      ['name' => $this->randomMachineName(), 'mail' => $this->getRandomEmailAddress()]
    );
    $captured_emails = $this->container->get('state')->get('system.test_mail_collector');
    $this->assertEmpty($captured_emails, 'No notifications has been sent.');

    // Tests that the user receives a mail if a reply has been posted.
    $this->postComment(
      "/comment/reply/node/{$node->id()}/comment/{$comment['id']}",
      $this->randomMachineName(),
      $this->randomMachineName(),
      ['notify' => FALSE, 'notify_type' => COMMENT_NOTIFY_ENTITY],
      ['name' => $this->randomMachineName(), 'mail' => $this->getRandomEmailAddress()]
    );
    $this->assertMail('to', $contact['mail'], t('Message was sent to the anonymous user.'));

    // Test the unsubscribe link.
    $mails = $this->getMails();
    preg_match("/\/comment_notify\/disable\/.+/", $mails[0]['body'], $output);
    $this->drupalGet($output[0]);
    $this->assertTrue($this->getSession()->getPage()->hasContent("Your comment follow-up notification for this post was disabled. Thanks."));
    // Confirm that the notification has been disabled.
    $result = comment_notify_get_notification_type($comment['id']);
    $this->assertEquals($result, COMMENT_NOTIFY_DISABLED, 'The notification has been disabled');
    // Tests that the user stopped receiving notifications.
    $this->container->get('state')->set('system.test_mail_collector', []);
    $this->postComment(
      "/comment/reply/node/{$node->id()}/comment/{$comment['id']}",
      $this->randomMachineName(),
      $this->randomMachineName(),
      ['notify' => FALSE, 'notify_type' => COMMENT_NOTIFY_ENTITY],
      ['name' => $this->randomMachineName(), 'mail' => $this->getRandomEmailAddress()]
    );
    $captured_emails = $this->container->get('state')->get('system.test_mail_collector');
    $this->assertEmpty($captured_emails, 'No notifications has been sent.');
  }

}

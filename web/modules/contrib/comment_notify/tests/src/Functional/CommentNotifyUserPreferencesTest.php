<?php

namespace Drupal\Tests\comment_notify\Functional;

use Drupal\Core\Session\AccountInterface;

/**
 * Tests the Comment Notify users preferences.
 *
 * @group comment_notify
 */
class CommentNotifyUserPreferencesTest extends CommentNotifyTestBase {

  /**
   * Authenticated User.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $authenticatedUser;

  /**
   * Permissions required by the module.
   *
   * @var array
   */
  protected $permissions = [
    'post comments',
    'skip comment approval',
    'subscribe to comments',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->authenticatedUser = $this->drupalCreateUser($this->permissions);
  }

  /**
   * Tests that the comment notify box is displayed correctly.
   *
   * It should display different options depending the permissions of the user.
   */
  public function testUserCommentNotifyBox() {
    // The user hasn't the subscribe to comments permission nor the 'administer
    // nodes' permission, nor has permission to create content, so it shouldn't
    // see any Comment notify settings in the profile page.
    $this->authenticatedUser = $this->drupalCreateUser(
      [
        'post comments',
        'skip comment approval',
      ]
    );
    $this->drupalLogin($this->authenticatedUser);
    $this->drupalGet($this->authenticatedUser->toUrl('edit-form')->toString());
    $this->assertFalse($this->getSession()->getPage()->hasContent(t('Comment follow-up notification settings')));
    $this->drupalLogout();

    // The user only has the 'subscribe to comments' permission, he should be
    // able to see the Comment Notify settings box but the 'Receive content
    // follow-up notification e-mails' checkbox shouldn't appear.
    $this->authenticatedUser = $this->drupalCreateUser([
      'post comments',
      'skip comment approval',
      'subscribe to comments',
    ]);
    $this->drupalLogin($this->authenticatedUser);
    $this->drupalGet($this->authenticatedUser->toUrl('edit-form')->toString());
    $this->assertTrue($this->getSession()->getPage()->hasContent(t('Comment follow-up notification settings')));
    $this->assertTrue($this->getSession()->getPage()->hasContent(t('Receive comment follow-up notification e-mails')));
    $this->assertFalse($this->getSession()->getPage()->hasContent(t('Receive content follow-up notification e-mails')));
    $this->drupalLogout();

    // The user only has the 'administer nodes' permission, he should be
    // able to see the Comment Notify settings box but the 'Comment follow-up
    // notification settings' dropdown shouldn't appear.
    $this->authenticatedUser = $this->drupalCreateUser([
      'post comments',
      'skip comment approval',
      'administer nodes',
    ]);
    $this->drupalLogin($this->authenticatedUser);
    $this->drupalGet($this->authenticatedUser->toUrl('edit-form')->toString());
    $this->assertTrue($this->getSession()->getPage()->hasContent(t('Comment follow-up notification settings')));
    $this->assertFalse($this->getSession()->getPage()->hasContent(t('Receive comment follow-up notification e-mails')));
    $this->assertTrue($this->getSession()->getPage()->hasContent(t('Receive content follow-up notification e-mails')));
    $this->drupalLogout();

    // The user only hasn't the 'administer nodes' permission nor the 'subscribe
    // to comments' permission but he can create nodes of the type article, so
    // he should be able to see the Comment Notify settings box with the
    // 'Receive content follow-up notification e-mails' checkbox.
    $this->authenticatedUser = $this->drupalCreateUser([
      'post comments',
      'skip comment approval',
      'create article content',
    ]);
    $this->drupalLogin($this->authenticatedUser);
    $this->drupalGet($this->authenticatedUser->toUrl('edit-form')->toString());
    $this->assertTrue($this->getSession()->getPage()->hasContent(t('Comment follow-up notification settings')));
    $this->assertFalse($this->getSession()->getPage()->hasContent(t('Receive comment follow-up notification e-mails')));
    $this->assertTrue($this->getSession()->getPage()->hasContent(t('Receive content follow-up notification e-mails')));
    $this->drupalLogout();

    // The has all the permissions, so he should be able to see all the Comment
    // notify settings.
    $this->authenticatedUser = $this->drupalCreateUser([
      'post comments',
      'skip comment approval',
      'create article content',
      'subscribe to comments',
    ]);
    $this->drupalLogin($this->authenticatedUser);
    $this->drupalGet($this->authenticatedUser->toUrl('edit-form')->toString());
    $this->assertTrue($this->getSession()->getPage()->hasContent(t('Comment follow-up notification settings')));
    $this->assertTrue($this->getSession()->getPage()->hasContent(t('Receive comment follow-up notification e-mails')));
    $this->assertTrue($this->getSession()->getPage()->hasContent(t('Receive content follow-up notification e-mails')));
    $this->drupalLogout();
  }

  /**
   * Tests the Comment follow-up notification settings.
   */
  public function testUserCommentPreferences() {

    // Tests that the settings are present in the user profile.
    $this->drupalLogin($this->authenticatedUser);
    $this->drupalGet($this->authenticatedUser->toUrl('edit-form')->toString());
    $this->assertTrue($this->getSession()->getPage()->hasContent(t('Comment follow-up notification settings')));
    $this->drupalLogout();
  }

  /**
   * Tests the "Comment Follow-up notifications" options.
   */
  public function testsCommentFollowUpsNotifications() {
    $this->drupalLogin($this->authenticatedUser);
    $this->drupalGet($this->authenticatedUser->toUrl('edit-form')->toString());
    $this->assertTrue($this->getSession()->getPage()->hasContent(t('Receive comment follow-up notification e-mails')));

    // Test the "No notifications" option.
    $this->getSession()->getPage()->selectFieldOption('comment_notify', COMMENT_NOTIFY_DISABLED);
    $this->getSession()->getPage()->pressButton(t('Save'));
    $node = $this->drupalCreateNode(['type' => 'article']);
    $this->drupalGet($node->toUrl()->toString());
    $this->assertTrue($this->getSession()->getPage()->hasUncheckedField('Notify me when new comments are posted'));

    // Test the "All comments" option.
    $this->drupalGet($this->authenticatedUser->toUrl('edit-form')->toString());
    $this->getSession()->getPage()->selectFieldOption('comment_notify', COMMENT_NOTIFY_ENTITY);
    $this->getSession()->getPage()->pressButton(t('Save'));
    $this->drupalGet($node->toUrl()->toString());
    $this->assertTrue($this->getSession()->getPage()->hascheckedField('Notify me when new comments are posted'));
    $this->assertTrue($this->getSession()->getPage()->hascheckedField('All comments'));

    // Tests the "Replies to my comments" option.
    $this->drupalGet($this->authenticatedUser->toUrl('edit-form')->toString());
    $this->getSession()->getPage()->selectFieldOption('comment_notify', COMMENT_NOTIFY_COMMENT);
    $this->getSession()->getPage()->pressButton(t('Save'));
    $this->drupalGet($node->toUrl()->toString());
    $this->assertTrue($this->getSession()->getPage()->hascheckedField('Notify me when new comments are posted'));
    $this->assertTrue($this->getSession()->getPage()->hascheckedField('Replies to my comment'));

    $this->drupalLogout();

  }

  /**
   * Tests the Content follow-up notification settings.
   */
  public function testUserNodePreferences() {

    // Tests that the option not present in the user profile unless the user has
    // the 'administer nodes' permission.
    $this->drupalLogin($this->authenticatedUser);
    $this->drupalGet($this->authenticatedUser->toUrl('edit-form')->toString());
    $this->assertFalse($this->getSession()->getPage()->hasContent(t('Receive content follow-up notification e-mails')));
    $this->drupalLogout();

    // Tests that the option is present in the user profile if the user has the
    // 'administer nodes' permission.
    $permissions = array_merge($this->permissions, ['administer nodes']);
    $this->authenticatedUser = $this->createUser($permissions);
    $this->drupalLogin($this->authenticatedUser);
    $this->drupalGet($this->authenticatedUser->toUrl('edit-form')->toString());
    $this->assertTrue($this->getSession()->getPage()->hasContent(t('Receive content follow-up notification e-mails')));
    $this->getSession()->getPage()->checkField('Receive content follow-up notification e-mails');
    $this->getSession()->getPage()->pressButton(t('Save'));
    $node_notify_preference = $this->container->get('comment_notify.user_settings')->getSetting($this->authenticatedUser->id(), 'entity_notify');
    $this->assertEquals(COMMENT_NOTIFY_ENTITY, $node_notify_preference);
    $this->assertSession()->checkboxChecked(t('Receive content follow-up notification e-mails'));
    $this->drupalLogout();

    // Tests that the notification is sent when the content created by the user
    // receives a new comment.
    $node = $this->drupalCreateNode(
      [
        'type' => 'article',
        'uid' => $this->authenticatedUser,
      ]
    );
    // Write a comment as anonymous user.
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
    $this->postComment(
      $node->toUrl()->toString(),
      $this->randomMachineName(),
      $this->randomMachineName(),
      ['notify' => FALSE, 'notify_type' => COMMENT_NOTIFY_ENTITY]
    );

    // Test that the notification was sent.
    $this->assertMail('to', $this->authenticatedUser->getEmail(), t('Message was sent to the user.'));

    // Tests that the notification is not sent when the user hasn't selected
    // the content notifications setting.
    $this->drupalLogin($this->authenticatedUser);
    $this->drupalGet($this->authenticatedUser->toUrl('edit-form')->toString());
    $this->assertTrue($this->getSession()->getPage()->hasContent(t('Receive content follow-up notification e-mails')));
    $this->getSession()->getPage()->uncheckField('Receive content follow-up notification e-mails');
    $this->getSession()->getPage()->pressButton(t('Save'));
    $this->assertTrue($this->getSession()->getPage()->hasUncheckedField('Receive content follow-up notification e-mails'));
    $this->drupalLogout();
    $node_notify_preference = $this->container->get('comment_notify.user_settings')->getSetting($this->authenticatedUser->id(), 'entity_notify');
    $this->assertEquals(COMMENT_NOTIFY_DISABLED, $node_notify_preference);

    $this->container->get('state')->set('system.test_mail_collector', []);
    $node = $this->drupalCreateNode(
      [
        'type' => 'article',
        'uid' => $this->authenticatedUser,
      ]
    );
    $this->postComment(
      $node->toUrl()->toString(),
      $this->randomMachineName(),
      $this->randomMachineName(),
      ['notify' => FALSE, 'notify_type' => COMMENT_NOTIFY_ENTITY]
    );
    $captured_emails = $this->container->get('state')->get('system.test_mail_collector');
    $this->assertEmpty($captured_emails, 'No notifications has been sent.');

  }

  /**
   * Tests that when a user is canceled all the notifications are deleted.
   */
  public function testUserCancelAccount() {
    $cancel_method_options = [
      'user_cancel_block',
      'user_cancel_block_unpublish',
      'user_cancel_reassign',
    ];

    foreach ($cancel_method_options as $cancel_method_option) {
      $user = $this->drupalCreateUser($this->permissions);
      $this->container->get('comment_notify.user_settings')->saveSettings($user->id(), COMMENT_NOTIFY_ENTITY, COMMENT_NOTIFY_COMMENT);
      user_cancel([], $user->id(), $cancel_method_option);
      $this->assertTrue(is_null($this->container->get('comment_notify.user_settings')->getSettings($user->id())));
    }

    // Delete Account.
    $user = $this->drupalCreateUser($this->permissions);
    $this->container->get('comment_notify.user_settings')->saveSettings($user->id(), COMMENT_NOTIFY_ENTITY, COMMENT_NOTIFY_COMMENT);
    $user->delete();
    $this->assertTrue(is_null($this->container->get('comment_notify.user_settings')->getSettings($user->id())));
  }

}

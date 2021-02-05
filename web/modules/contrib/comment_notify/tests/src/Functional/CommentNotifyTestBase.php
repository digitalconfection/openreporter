<?php

namespace Drupal\Tests\comment_notify\Functional;

use Drupal\comment\Tests\CommentTestTrait;
use Drupal\comment\CommentInterface;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Comment notify Base Test class.
 */
abstract class CommentNotifyTestBase extends BrowserTestBase {

  use CommentTestTrait;
  use AssertMailTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * Admin User.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

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
  ];

  /**
   * Test that the config page is working.
   */
  protected function setUp() {
    parent::setUp();

    // Create and login administrative user.
    $this->adminUser = $this->drupalCreateUser(
      [
        'administer comment notify',
        'administer permissions',
        'administer comments',
      ]
    );

    // Enable comment notify on this node and allow anonymous information in
    // comments.
    $this->drupalCreateContentType([
      'type' => 'article',
    ]);
    $this->addDefaultCommentField('node', 'article');
    $comment_field = FieldConfig::loadByName('node', 'article', 'comment');
    $comment_field->setSetting('anonymous', CommentInterface::ANONYMOUS_MAY_CONTACT);
    $comment_field->save();
  }

  /**
   * Post comment.
   *
   * @param string $url
   *   The url where the comment will be submitted.
   * @param string $subject
   *   Comment subject.
   * @param string $comment
   *   Comment body.
   * @param array $notify
   *   An array with the notify values.
   * @param mixed $contact
   *   Set to NULL for no contact info, TRUE to ignore success checking, and
   *   array of values to set contact info.
   *
   * @return array|bool
   *   return an array with the comment or false if the post comment fails.
   */
  protected function postComment($url, $subject, $comment, array $notify, $contact = NULL) {
    $edit = [];
    $edit['subject[0][value]'] = $subject;
    $edit['comment_body[0][value]'] = $comment;

    if ($notify !== NULL && is_array($notify)) {
      $edit += $notify;
    }

    if ($contact !== NULL && is_array($contact)) {
      $edit += $contact;
    }

    $this->drupalPostForm($url, $edit, t('Save'));

    $match = [];
    // Get comment ID.
    preg_match('/#comment-([^"]+)/', $this->getURL(), $match);

    // Get comment.
    // If true then attempting to find error message.
    if (!empty($match[1])) {
      if ($subject) {
        $this->assertTrue($this->getSession()->getPage()->hasContent($subject), 'Comment subject posted.');
      }
      $this->assertTrue($this->getSession()->getPage()->hasContent($comment), 'Comment body posted.');
      $this->assertTrue((!empty($match) && !empty($match[1])), t('Comment id found.'));
    }

    if (isset($match[1])) {
      return ['id' => $match[1], 'subject' => $subject, 'comment' => $comment];
    }

    return FALSE;
  }

  /**
   * Checks current page for specified comment.
   *
   * @param object $comment
   *   Comment object.
   * @param bool $reply
   *   The comment is a reply to another comment.
   *
   * @return bool
   *   Comment found.
   */
  protected function commentExists($comment, $reply = FALSE) {
    if ($comment && is_object($comment)) {
      $regex = '/' . ($reply ? '<div class="indented">(.*?)' : '');
      // Comment anchor.
      $regex .= '<a id="comment-' . $comment->id . '"(.*?)';
      // Begin in comment div.
      $regex .= '<div(.*?)';
      // Match subject.
      $regex .= $comment->subject . '(.*?)';
      // Match comment.
      $regex .= $comment->comment . '(.*?)';
      // Dot matches newlines and ensure that match doesn't bleed outside
      // comment div.
      $regex .= '<\/div>/s';

      return (boolean) preg_match($regex, $this->getSession()->getPage()->getContent());
    }
    else {
      return FALSE;
    }
  }

  /**
   * Returns a randomly generated valid email address.
   *
   * @return string
   *   A random email.
   */
  public function getRandomEmailAddress() {
    return $this->randomMachineName() . '@example.com';
  }

}

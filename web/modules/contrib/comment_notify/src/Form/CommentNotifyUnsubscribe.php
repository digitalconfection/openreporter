<?php

namespace Drupal\comment_notify\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Unsubscribe form for Comment Notify.
 */
class CommentNotifyUnsubscribe extends FormBase {

  /**
   * Messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger')
    );
  }

  /**
   * CommentNotifyUnsubscribe constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'comment_notify_unsubscribe';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['comment_notify_unsubscribe'] = [];

    $form['comment_notify_unsubscribe']['email'] = [
      '#type' => 'textfield',
      '#title' => t('Email to unsubscribe'),
      '#description' => $this->t('All comment notification requests associated with this email will be revoked.'),
      '#required' => TRUE,
    ];
    $form['comment_notify_unsubscribe']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Unsubscribe this e-mail'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    module_load_include('inc', 'comment_notify', 'comment_notify');
    $email = trim($form_state->getValue(['email']));
    $comments = comment_notify_unsubscribe_by_email($email);
    // Update the admin about the state of the subscription.
    if ($comments == 0) {
      $this->messenger->addWarning($this->t("There were no active comment notifications for that email."));
    }
    else {
      $this->messenger->addStatus($this->t("Email unsubscribed from all the comment notifications."));
    }
  }

}

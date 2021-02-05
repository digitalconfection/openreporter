<?php

namespace Drupal\comment_notify\Form;

use Drupal\comment\CommentInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for the Comment Notify module.
 */
class CommentNotifySettings extends ConfigFormBase {

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $fieldManager;

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'comment_notify_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['comment_notify.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_field.manager'),
      $container->get('module_handler'),
      $container->get('messenger')
    );
  }

  /**
   * CommentNotifySettings constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Entity\EntityFieldManager $field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityFieldManager $field_manager, ModuleHandlerInterface $module_handler, MessengerInterface $messenger) {
    parent::__construct($config_factory);
    $this->fieldManager = $field_manager;
    $this->moduleHandler = $module_handler;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('comment_notify.settings');

    $bundle_checkboxes = $this->getCommentFieldIdentifiers();
    // Only perform comment_notify for certain bundle types.
    $enabled_bundles = $config->get('bundle_types');

    $anonymous_problems = [];
    // If they don't have the ability to leave contact info, then we make a
    // report.
    $entity_types = [];
    foreach ($bundle_checkboxes as $comment_field_identifier => $bundle_checkbox_label) {
      $comment_field_info = explode('--', $comment_field_identifier);
      $entity_type = $comment_field_info[0];
      $entity_bundle = $comment_field_info[1];
      $field_name = $comment_field_info[2];
      $entity_types[$entity_type][] = $comment_field_identifier;
      $comment_field = FieldConfig::loadByName($entity_type, $entity_bundle, $field_name);

      if (in_array($entity_type . '--' . $entity_bundle . '--' . $field_name, $enabled_bundles) && $comment_field && $comment_field->getSetting('anonymous') == CommentInterface::ANONYMOUS_MAYNOT_CONTACT) {
        if (User::getAnonymousUser()->hasPermission('subscribe to comments')) {
          // Provide a link if the field_ui module is installed.
          if ($this->moduleHandler->moduleExists('field_ui')) {
            $link = Link::fromTextAndUrl($comment_field_identifier, $comment_field->toUrl($entity_type . '-field-edit-form'));
            $no_allowed_contact_info_field[] = $link->toString();
          }
          else {
            $no_allowed_contact_info_field[] = $comment_field_identifier;
          }
        }
      }
    }

    // If anonymous users can subscribe to comments they must be allowed to
    // post comments and leave their contact information.
    if (User::getAnonymousUser()->hasPermission('subscribe to comments')) {

      if (!User::getAnonymousUser()->hasPermission('post comments')) {
        $anonymous_problems = $this->t('Post comments');
      }
      elseif (!empty($no_allowed_contact_info_field)) {
        $markup = new Markup();
        $fields = $markup->create(implode('</li><li>', $no_allowed_contact_info_field));
        $anonymous_problems = $this->t("Leave their contact information on the following fields: <ul><li>@fields</li></ul>", ['@fields' => $fields]);
      }

      if (!empty($anonymous_problems)) {
        $this->messenger->addWarning($this->t('Anonymous commenters have the permission to subscribe to comments but they need to be allowed to:<br/>@anonymous_problems', ['@anonymous_problems' => $anonymous_problems]));
      }
    }

    $form['bundle_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Bundles to enable for comment notification'),
      '#default_value' => $enabled_bundles,
      '#options' => $bundle_checkboxes,
      '#description' => $this->t('Comments on bundle types enabled here will have the option of comment notification. Written as "Entity Type: Bundle: Comment field".'),
    ];

    $form['available_alerts'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Available subscription modes'),
      '#return_value' => 1,
      '#default_value' => array_keys(array_filter($config->get('available_alerts'))),
      '#description' => $this->t('Choose which notification subscription styles are available for users'),
      '#options' => [
        COMMENT_NOTIFY_ENTITY => $this->t('All comments'),
        COMMENT_NOTIFY_COMMENT => $this->t('Replies to my comment'),
      ],
    ];

    $available_options[COMMENT_NOTIFY_DISABLED] = $this->t('No notifications');
    $available_options += _comment_notify_options();

    $form['enable_default'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    $form['enable_default']['watcher'] = [
      '#type' => 'select',
      '#title' => $this->t('Default state for the notification selection box'),
      '#return_value' => 1,
      '#default_value' => $config->get('enable_default.watcher'),
      '#description' => $this->t('This flag presets the flag for the follow-up notification on the form that users will see when posting a comment'),
      '#options' => $available_options,
    ];

    $form['enable_default']['entity_author'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Subscribe users to their entity follow-up notification emails by default'),
      '#default_value' => $config->get('enable_default.entity_author'),
      '#description' => $this->t('If this is checked, new users will receive e-mail notifications for follow-ups on their entities by default until they individually disable the feature.'),
    ];

    $form['mail_templates'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $form['mail_templates']['watcher'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    $form['mail_templates']['entity_author'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    // Create notification options for each supported entity type.
    foreach ($entity_types as $entity_type => $checkboxes) {
      // The #states logic is rather messy. It needs to allow any of the
      // checkboxes for a specific entity type to make the email fields visible
      // so each checkbox has to be part of a jQuery OR selector.
      $checkboxeses = [];
      foreach ($checkboxes as $checkbox) {
        $checkboxeses[] = ':input[name="bundle_types[' . $checkbox . ']"]';
      }
      $checkboxeses = implode(',', $checkboxeses);

      $form['mail_templates']['watcher'][$entity_type] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
      $form['mail_templates']['watcher'][$entity_type]['subject'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Default mail subject for sending out :entity_type notifications to commenters', [':entity_type' => $entity_type]),
        '#default_value' => $config->get('mail_templates.watcher.' . $entity_type . '.subject'),
        '#token_types' => [
          'comment', 'comment-subscribed', $entity_type,
        ],
        '#element_validate' => ['token_element_validate'],
        '#states' => [
          'visible' => [
            $checkboxeses => [
              'checked' => TRUE,
            ],
          ],
        ],
      ];
      $form['mail_templates']['watcher'][$entity_type]['body'] = [
        '#type' => 'textarea',
        '#title' => $this->t('%label: Default mail text for sending out notifications to commenters', [
          '%label' => $entity_type,
        ]),
        '#default_value' => $config->get('mail_templates.watcher.' . $entity_type . '.body'),
        '#cols' => 80,
        '#rows' => 15,
        // @todo Change from 'node' to 'entity'.
        // See Issue #1061750 on Drupal.org
        '#token_types' => [
          'comment', 'comment-subscribed', $entity_type,
        ],
        '#element_validate' => ['token_element_validate'],
        '#states' => [
          'visible' => [
            $checkboxeses => [
              'checked' => TRUE,
            ],
          ],
        ],
      ];

      $form['mail_templates']['entity_author'][$entity_type] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
      $form['mail_templates']['entity_author'][$entity_type]['subject'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Default mail subject for sending out :entity_type notifications to authors', [':entity_type' => $entity_type]),
        '#default_value' => $config->get('mail_templates.entity_author.' . $entity_type . '.subject'),
        '#token_types' => [
          'comment', $entity_type, 'user',
        ],
        '#element_validate' => ['token_element_validate'],
        '#states' => [
          'visible' => [
            $checkboxeses => [
              'checked' => TRUE,
            ],
          ],
        ],
      ];
      $form['mail_templates']['entity_author'][$entity_type]['body'] = [
        '#type' => 'textarea',
        '#title' => $this->t('%label: Default mail text for sending out the notifications to entity authors', [
          '%label' => $entity_type,
        ]),
        '#default_value' => $config->get('mail_templates.entity_author.' . $entity_type . '.body'),
        '#cols' => 80,
        '#rows' => 15,
        // @todo: Change token from 'node' to 'entity'
        // See Issue #1061750 on Drupal.org
        '#token_types' => [
          'comment', $entity_type, 'user',
        ],
        '#element_validate' => ['token_element_validate'],
        '#states' => [
          'visible' => [
            $checkboxeses => [
              'checked' => TRUE,
            ],
          ],
        ],
      ];
    }

    $form['token_help'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => [
        'comment', 'comment-subscribed', 'node', 'user',
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Returns array of constructed machine names for each comment field.
   *
   * Machine names used as array keys, checkbox labels used as values.
   *
   * @return array
   *   Identifier for each comment field, formatted:
   *   [entity_type]--[bundle]--[field_name].
   */
  public function getCommentFieldIdentifiers() {
    $bundle_checkboxes = [];
    // Provide all comment fields as options.
    $comment_field_map = $this->fieldManager->getFieldMapByFieldType('comment');
    foreach ($comment_field_map as $entity_type => $comment_fields) {
      foreach ($comment_fields as $field_name => $field_info) {
        foreach ($field_info['bundles'] as $field_bundle) {
          $bundle_checkboxes[$entity_type . '--' . $field_bundle . '--' . $field_name] = Html::escape($entity_type . ': ' . $field_bundle . ': ' . $field_name);
        }
      }
    }

    return $bundle_checkboxes;
  }

  /**
   * Get the field identifier machine name for a specific comment from config.
   *
   * Returns the machine name of field identifier from bundle_types config for a
   * specific comment.
   *
   * @param \Drupal\Core\Entity\EntityInterface $comment
   *   The comment entity.
   *
   * @return string
   *   Identifier for the comment field, formatted:
   *   [entity_type]--[bundle]--[field_name].
   */
  public static function getCommentFieldIdentifier(EntityInterface $comment) {
    $comment_on_entity_type = $comment->getCommentedEntityTypeId();
    $comment_on_bundle_type = $comment->getCommentedEntity()->bundle();
    $comment_on_field_name = $comment->getFieldName();
    $comment_on_identifier = implode('--', [
      $comment_on_entity_type,
      $comment_on_bundle_type,
      $comment_on_field_name,
    ]);

    return $comment_on_identifier;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!array_filter($form_state->getValue('available_alerts'))) {
      $form_state->setErrorByName('available_alerts', 'You must enable at least one subscription mode.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('comment_notify.settings');
    $config->set('bundle_types', array_keys(array_filter($form_state->getValue('bundle_types'))));
    $config->set('available_alerts', $form_state->getValue('available_alerts'));
    $config->set('enable_default', $form_state->getValue('enable_default'));
    $bundle_checkboxes = $this->getCommentFieldIdentifiers();

    foreach ($bundle_checkboxes as $identifier => $label) {
      $comment_field_info = explode('--', $identifier);
      $entity_type = $comment_field_info[0];
      $config->set("mail_templates.watcher.$entity_type.subject", $form_state->getValue([
        'mail_templates',
        'watcher',
        $entity_type,
        'subject',
      ]));
      $config->set("mail_templates.watcher.$entity_type.body", $form_state->getValue([
        'mail_templates',
        'watcher',
        $entity_type,
        'body',
      ]));
      $config->set("mail_templates.entity_author.$entity_type.subject", $form_state->getValue([
        'mail_templates',
        'entity_author',
        $entity_type,
        'subject',
      ]));
      $config->set("mail_templates.entity_author.$entity_type.body", $form_state->getValue([
        'mail_templates',
        'entity_author',
        $entity_type,
        'body',
      ]));
    }
    $config->save();
    parent::submitForm($form, $form_state);
  }

}

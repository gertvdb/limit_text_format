<?php

namespace Drupal\limit_text_format\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\text\Plugin\Field\FieldWidget\TextareaWidget;
use Drupal\Core\Render\Element;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Limit the allowed text formats.
 *
 * @FieldWidget(
 *   id = "limit_text_format",
 *   label = @Translation("Limit Text Format"),
 *   field_types = {
 *     "text",
 *     "text_long",
 *     "text_with_summary"
 *   }
 * )
 */
class LimitTextFormatWidget extends TextareaWidget implements ContainerFactoryPluginInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct($pluginId, $pluginDefinition, FieldDefinitionInterface $fieldDefinition, array $settings, array $thirdPartySettings, ConfigFactoryInterface $configFactory, AccountProxyInterface $currentUser) {
    parent::__construct($pluginId, $pluginDefinition, $fieldDefinition, $settings, $thirdPartySettings);
    $this->configFactory = $configFactory;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition) {
    return new static(
      $pluginId,
      $pluginDefinition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('config.factory'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'limit_text_format' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Allowed text formats: @formats', ['@formats' => implode(', ', $this->getAllowedTextFormats())]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $formState) {
    $element = parent::settingsForm($form, $formState);

    $optionFormats = [];
    foreach (filter_formats() as $format) {
      $optionFormats[$format->id()] = $format->label();
    }

    $element['limit_text_format'] = [
      '#title' => t('Limit text formats'),
      '#type' => 'checkboxes',
      '#options' => $optionFormats,
      '#default_value' => $this->getAllowedTextFormats(),
      '#required' => TRUE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $formState) {
    $element = parent::formElement($items, $delta, $element, $form, $formState);

    // Get a list of formats that the current user has access to.
    $formats = filter_formats($this->currentUser);

    // Allow the list of formats to be restricted.
    $formats = array_intersect_key($formats, array_flip($this->getAllowedTextFormats()));

    // Pass the widget allowed formats to the after build.
    $widgetAllowedFormats = [];
    foreach ($formats as $format) {
      $widgetAllowedFormats[$format->id()] = $format->label();
    }

    $element['#widget_limit_text_format'] = $widgetAllowedFormats;
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function afterBuild(array $element, FormStateInterface $formState) {
    $children = Element::children($element);
    foreach ($children as $key) {

      if (isset($element[$key]['#widget_limit_text_format'])) {
        $element[$key]['format']['format']['#options'] = $element[$key]['#widget_limit_text_format'];

        // Disable text format selection when there is only one format.
        if (count($element[$key]['#widget_limit_text_format']) < 1) {
          $element[$key]['format']['format']['#attributes']['disabled'] = 'disabled';
        }
      }

      // Remove the 'about formats' help link.
      $element[$key]['format']['guidelines']['#access'] = FALSE;
      $element[$key]['format']['help']['#access'] = FALSE;
    }

    return $element;
  }

  /**
   * The text formats defined by the widget.
   *
   * @return array
   *   An array of text formats keyed by id.
   */
  private function getAllowedTextFormats() {
    // Get the defined text formats for this widget.
    $allowedTextFormats = array_filter($this->getSetting('limit_text_format'));

    // Get the filter config and append the default fallback format
    // when no formats are defined or when option to always show the
    // default fallback is checked.
    $config = $this->configFactory->get('filter.settings');
    if (empty($allowedTextFormats) || $config->get('always_show_fallback_choice')) {
      $allowedTextFormats += [$config->get('fallback_format') => $config->get('fallback_format')];
    }

    return $allowedTextFormats;
  }

}

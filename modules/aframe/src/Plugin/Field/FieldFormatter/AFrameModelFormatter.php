<?php

/**
 * @file
 * Contains \Drupal\aframe\Plugin\Field\FieldFormatter\AFrameModelFormatter.
 */

namespace Drupal\aframe\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileFormatterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'aframe_model' formatter.
 *
 * @FieldFormatter(
 *   id = "aframe_model",
 *   label = @Translation("A-Frame Model"),
 *   field_types = {
 *     "file"
 *   }
 * )
 */
class AFrameModelFormatter extends FileFormatterBase implements ContainerFactoryPluginInterface {

  use AFrameFormatterTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings']
    );
  }


  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $defaults = [];

    // Get A-Frame global formatter settings defaults.
    $defaults += AFrameFormatterTrait::globalDefaultSettings();

    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = [];

    // Get A-Frame global formatter settings form.
    $element += $this->globalSettingsForm($form, $form_state);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    // Get A-Frame global formatter settings summary.
    $summary = array_merge($summary, $this->globalSettingsSummary());

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $files = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($files)) {
      return $elements;
    }

    /** @var \Drupal\file\Entity\File $file */
    foreach ($files as $delta => $file) {
      $elements[$delta] = [
        '#type'       => 'aframe_model',
        '#attributes' => [
          'format' => pathinfo($file->getFilename())['extension'] == 'obj' ? 'obj' : 'collada',
          'src'    => file_create_url($file->getFileUri()),
        ],
        '#cache'      => [
          'tags' => $file->getCacheTags(),
        ],
      ];

      // Get A-Frame global formatter attributes.
      $elements[$delta]['#attributes'] += $this->getAttributes();
    }

    return $elements;
  }

}

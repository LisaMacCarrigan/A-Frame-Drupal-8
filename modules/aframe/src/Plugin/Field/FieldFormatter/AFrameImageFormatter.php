<?php

/**
 * @file
 * Contains \Drupal\aframe\Plugin\Field\FieldFormatter\AFrameImageFormatter.
 */

namespace Drupal\aframe\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;

/**
 * Plugin implementation of the 'aframe_image' formatter.
 *
 * @FieldFormatter(
 *   id = "aframe_image",
 *   label = @Translation("A-Frame Image"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class AFrameImageFormatter extends ImageFormatter implements ContainerFactoryPluginInterface {

  use AFrameFormatterTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $defaults = parent::defaultSettings();
    unset($defaults['image_link']);

    $defaults['aframe_image_height_ratio'] = 1;
    $defaults['aframe_image_width_ratio'] = 1;
    $defaults['aframe_image_opacity'] = 1;

    // Get A-Frame global formatter settings defaults.
    $defaults += AFrameFormatterTrait::globalDefaultSettings();

    return $defaults;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    unset($element['image_link']);

    $element['aframe_image_height_ratio'] = [
      '#title'       => t('Height ratio'),
      '#type'        => 'number',
      '#step'        => 0.1,
      '#description' => t('Height pixel to centimeter (cm) conversion ratio.'),
      '#value'       => $this->getSetting('aframe_image_height_ratio'),
      '#required'    => TRUE,
    ];

    $element['aframe_image_width_ratio'] = [
      '#title'       => t('Width ratio'),
      '#type'        => 'number',
      '#step'        => 0.1,
      '#description' => t('Width pixel to centimeter (cm) conversion ratio.'),
      '#value'       => $this->getSetting('aframe_image_width_ratio'),
      '#required'    => TRUE,
    ];

    $element['aframe_image_opacity'] = [
      '#title'    => t('Opacity'),
      '#type'     => 'number',
      '#step'     => 0.05,
      '#value'    => $this->getSetting('aframe_image_opacity'),
      '#required' => TRUE,
    ];

    // Get A-Frame global formatter settings form.
    $element += $this->globalSettingsForm($form, $form_state);

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $image_styles = image_style_options(FALSE);
    // Unset possible 'No defined styles' option.
    unset($image_styles['']);
    // Styles could be lost because of enabled/disabled modules that defines
    // their styles in code.
    $image_style_setting = $this->getSetting('image_style');
    if (isset($image_styles[$image_style_setting])) {
      $summary[] = t('Image style: @style', ['@style' => $image_styles[$image_style_setting]]);
    }
    else {
      $summary[] = t('Original image');
    }

    $summary[] = t('Height ratio: @height', ['@height' => $this->getSetting('aframe_image_height_ratio')]);
    $summary[] = t('Width ratio: @width', ['@width' => $this->getSetting('aframe_image_width_ratio')]);
    $summary[] = t('Opacity: @opacity', ['@opacity' => $this->getSetting('aframe_image_opacity')]);

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

    $image_style_setting = $this->getSetting('image_style');

    // Collect cache tags to be added for each item in the field.
    $cache_tags = [];
    if (!empty($image_style_setting)) {
      /** @var \Drupal\image\Entity\ImageStyle $image_style */
      $image_style = $this->imageStyleStorage->load($image_style_setting);
      $cache_tags = $image_style->getCacheTags();
    }

    /** @var \Drupal\file\Entity\File $file */
    foreach ($files as $delta => $file) {
      $cache_tags = Cache::mergeTags($cache_tags, $file->getCacheTags());

      $item = $file->_referringItem;
      $dimensions = [
        'height' => $item->get('height')->getCastedValue(),
        'width'  => $item->get('width')->getCastedValue(),
      ];

      $url = file_create_url($file->getFileUri());
      if (isset($image_style)) {
        $image_style->transformDimensions($dimensions, $file->getFileUri());
        $url = $image_style->buildUrl($file->getFileUri());
      }

      $elements[$delta] = [
        '#type'       => 'aframe_image',
        '#attributes' => [
          'height'  => $dimensions['height'] * ($this->getSetting('aframe_image_height_ratio') / 100),
          'width'   => $dimensions['width'] * ($this->getSetting('aframe_image_width_ratio') / 100),
          'src'     => $url,
          'opacity' => $this->getSetting('aframe_image_opacity'),
        ],
        '#cache'      => [
          'tags' => $cache_tags,
        ],
      ];

      // Get A-Frame global formatter attributes.
      $elements[$delta]['#attributes'] += $this->getAttributes();
    }

    return $elements;
  }

}

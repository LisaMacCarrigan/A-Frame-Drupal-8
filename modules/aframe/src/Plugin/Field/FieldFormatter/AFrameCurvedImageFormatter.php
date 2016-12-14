<?php

/**
 * @file
 * Contains \Drupal\aframe\Plugin\Field\FieldFormatter\AFrameCurvedImageFormatter.
 */

namespace Drupal\aframe\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;

/**
 * Plugin implementation of the 'aframe_curvedimage' formatter.
 *
 * @FieldFormatter(
 *   id = "aframe_curvedimage",
 *   label = @Translation("A-Frame Curved Image"),
 *   field_types = {
 *     "image"
 *   }
 * )
 */
class AFrameCurvedImageFormatter extends ImageFormatter implements ContainerFactoryPluginInterface {

  use AFrameFormatterTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $defaults = parent::defaultSettings();
    unset($defaults['image_link']);

    $defaults['aframe_curvedimage_height_ratio'] = 1;
    $defaults['aframe_curvedimage_opacity'] = 1;
    $defaults['aframe_curvedimage_radius'] = 2;
    $defaults['aframe_curvedimage_segments_radial'] = 48;
    $defaults['aframe_curvedimage_theta_length'] = 360;
    $defaults['aframe_curvedimage_theta_start'] = 0;
    $defaults['aframe_curvedimage_transparent'] = TRUE;

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

    $element['aframe_curvedimage_height_ratio'] = [
      '#title'       => t('Height ratio'),
      '#type'        => 'number',
      '#description' => t('Height pixel to centimeter (cm) conversion ratio.'),
      '#value'       => $this->getSetting('aframe_curvedimage_height_ratio'),
      '#required'    => TRUE,
    ];

    $element['aframe_curvedimage_opacity'] = [
      '#title'    => t('Opacity'),
      '#type'     => 'number',
      '#step'     => 0.05,
      '#value'    => $this->getSetting('aframe_curvedimage_opacity'),
      '#required' => TRUE,
    ];

    $element['aframe_curvedimage_radius'] = [
      '#title'    => t('Radius'),
      '#type'     => 'number',
      '#step'     => 0.05,
      '#value'    => $this->getSetting('aframe_curvedimage_radius'),
      '#required' => TRUE,
    ];

    $element['aframe_curvedimage_segments_radial'] = [
      '#title'    => t('Segments radial'),
      '#type'     => 'number',
      '#step'     => 1,
      '#value'    => $this->getSetting('aframe_curvedimage_segments_radial'),
      '#required' => TRUE,
    ];

    $element['aframe_curvedimage_theta_length'] = [
      '#title'    => t('Theta length'),
      '#type'     => 'number',
      '#step'     => 0.05,
      '#value'    => $this->getSetting('aframe_curvedimage_theta_length'),
      '#required' => TRUE,
    ];

    $element['aframe_curvedimage_theta_start'] = [
      '#title'    => t('Theta start'),
      '#type'     => 'number',
      '#step'     => 0.05,
      '#value'    => $this->getSetting('aframe_curvedimage_theta_start'),
      '#required' => TRUE,
    ];

    $element['aframe_curvedimage_transparent'] = [
      '#title' => t('Transparent'),
      '#type'  => 'checkbox',
      '#value' => $this->getSetting('aframe_curvedimage_transparent'),
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

    $summary[] = t('Opacity: @opacity', ['@opacity' => $this->getSetting('aframe_curvedimage_opacity')]);
    $summary[] = t('Radius: @radius', ['@radius' => $this->getSetting('aframe_curvedimage_radius')]);
    $summary[] = t('Segments radial: @segments-radial', ['@segments-radial' => $this->getSetting('aframe_curvedimage_segments_radial')]);
    $summary[] = t('Theta length: @theta-length', ['@theta-length' => $this->getSetting('aframe_curvedimage_theta_length')]);
    $summary[] = t('Theta start: @theta-start', ['@theta-start' => $this->getSetting('aframe_curvedimage_theta_start')]);
    $summary[] = t('Transparent: @transparent', ['@transparent' => $this->getSetting('aframe_curvedimage_transparent')]);

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
        '#type'       => 'aframe_curvedimage',
        '#attributes' => [
          'height'          => $dimensions['height'] * ($this->getSetting('aframe_curvedimage_height_ratio') / 100),
          'opacity'         => $this->getSetting('aframe_curvedimage_opacity'),
          'radius'          => $this->getSetting('aframe_curvedimage_radius'),
          'segments-radial' => $this->getSetting('aframe_curvedimage_segments_radial'),
          'src'             => $url,
          'theta-length'    => $this->getSetting('aframe_curvedimage_theta_length'),
          'theta-start'     => $this->getSetting('aframe_curvedimage_theta_start'),
          'transparent'     => $this->getSetting('aframe_curvedimage_transparent'),
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

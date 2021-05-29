<?php
/**
 * @package     ttc-freebies.plugin-responsive-images
 *
 * @copyright   Copyright (C) 2020 Dimitrios Grammatikogiannis. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ttc\Freebies\Responsive;

defined('_JEXEC') || die();

require_once 'vendor/autoload.php';

use Intervention\Image\ImageManager;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * Content responsive images plugin
 */
class Helper {
  private $sizeSplit   = '_';
  private $baseDir     = '';
  private $quality     = 85;
  private $scaleUp     = false;
  private $validSizes  = array(200, 320, 480, 768, 992, 1200, 1600, 1920);
  private $validExt    = array('jpg', 'jpeg', 'png');

  public function __construct() {
    if ($this->baseDir === '') {
      $this->baseDir = JPATH_ROOT . '/' .
        Factory::getApplication('site')
          ->getParams('com_media')
          ->get('file_path', 'images');
    }

    $plugin          = PluginHelper::getPlugin('content', 'responsive');
    $this->params    = new Registry($plugin->params);
    $this->quality   = (int) $this->params->get('quality', 85);
    $this->scaleUp   = (bool) $this->params->get('scaleUp', 0);
    $this->sizeSplit = '_';
  }

  /**
   * Takes an image tag and returns the picture tag
   *
   * @param string  $image        the image tag
   * @param array   $breakpoints  the breakpoints
   *
   * @return string
   *
   * @throws \Exception
   */
  public function transformImage($image, $breakpoints) {
    if (!is_array($breakpoints)) {
      return $image;
    }

    // Get the original path
    preg_match('/src\s*=\s*"(.+?)"/', $image, $match);
    $originalImagePath = $match[1];
    $originalImagePath = str_replace(Uri::base(), '', $originalImagePath);
    $path = realpath(JPATH_ROOT . (substr($originalImagePath, 0, 1) === '/' ? $originalImagePath : '/'. $originalImagePath));

    if (strpos($path, $this->baseDir) !== 0 || strpos($path, $this->baseDir) === false) {
      return $image;
    }

    $originalImagePathInfo = pathinfo($originalImagePath);

    // Bail out if no images supported
    if (!in_array(mb_strtolower($originalImagePathInfo['extension']), $this->validExt) || !file_exists(JPATH_ROOT . '/' . $originalImagePath)) {
      return $image;
    }

    if (!is_dir(JPATH_ROOT . '/media/cached-resp-images/' . $originalImagePathInfo['dirname'])) {
      if (
        !@mkdir(JPATH_ROOT . '/media/cached-resp-images/' . $originalImagePathInfo['dirname'], 0755, true)
        && !is_dir(JPATH_ROOT . '/media/cached-resp-images/' . $originalImagePathInfo['dirname'])
      ) {
        throw new \RuntimeException('There was a file permissions problem in folder \'media\'');
      }
    }

    // If the responsive image doesn't exist we will create it
    if (
    !file_exists(
      JPATH_ROOT . '/media/cached-resp-images/' . $originalImagePathInfo['dirname'] . '/' .
      $originalImagePathInfo['filename'] . $this->sizeSplit . $validSize[0] . '.' . $originalImagePathInfo['extension']
    )
    ) {
      self::createImages(
        $validSize,
        $originalImagePathInfo['dirname'],
        $originalImagePathInfo['filename'],
        $originalImagePathInfo['extension']
      );
    }

    // If the responsive image exists use it
    if (
    file_exists(
      JPATH_ROOT . '/media/cached-resp-images/' . $originalImagePathInfo['dirname'] . '/' .
      $originalImagePathInfo['filename'] . $this->sizeSplit . $validSize[0] . '.' . $originalImagePathInfo['extension']
    )
    ) {
      $srcSets = self::buildSrcset(
        $breakpoints,
        $originalImagePathInfo['dirname'],
        $originalImagePathInfo['filename'],
        $originalImagePathInfo['extension'],
        $this->sizeSplit
      );

      if (empty($srcSets['base']['srcset'])) {
        return $image;
      }

      $type = in_array(mb_strtolower($originalImagePathInfo['extension']), ['jpg', 'jpeg']) ? 'jpeg' : $originalImagePathInfo['extension'];

      $output = '<picture class="responsive-image">';

      if ($srcSets['webp']['sizes']) {
        $output .= '<source type="image/webp" sizes="' . implode(', ', array_reverse($srcSets['webp']['sizes'])) . '" srcset="' . implode(', ', array_reverse($srcSets['webp']['srcset'])) . '">';
      }

      $output .= '<source type="image/' . $type . '" sizes="' . implode(', ', array_reverse($srcSets['base']['sizes'])). '" srcset="' . implode(', ', array_reverse($srcSets['base']['srcset'])) . '">';

      // Create the fallback img
      $image = preg_replace(
        '/src\s*=\s*".+?"/',
        'src="/media/cached-resp-images/' . $originalImagePathInfo['dirname'] . '/' . $originalImagePathInfo['filename'] .
        $this->sizeSplit . $validSize[0] . '.' . $originalImagePathInfo['extension'] . '"',
        $image
      );

      if (strpos($image, ' loading=') === false) {
        $image = str_replace('<img ', '<img loading="lazy" ', $image);
    }
      $output .= $image;
      $output .= '</picture>';

    if (!$created) {
      return $image;
    }

    return
      $this->buildSrcsetFromCache(
        $breakpoints,
        $created,
        $image
      );
  }

  /**
   * Build the srcset string from the cache data
   *
   * @param  array     $breakpoints  the different breakpoints
   * @param  stdClass  $fileInfo     the cached object.
   * @param  string    $image        the image html element.
   *
   * @return string
   *
   * @since  1.0
   */
   private static function buildSrcset($breakpoints = array(200, 320, 480, 768, 992, 1200, 1600, 1920), $dirname, $filename, $extension, $sizeSplit) {
    $srcset = array(
      'base' => array(
        'srcset' => array(),
        'sizes' => array(),
      ),
      'webp' => array(
        'srcset' => array(),
        'sizes' => array(),
      )
    );

    if (!empty($breakpoints)) {
      for ($i = 0, $l = count($breakpoints); $i < $l; $i++) {
        $fileSrc = 'media/cached-resp-images/' . $dirname . '/' . $filename . $sizeSplit . $breakpoints[$i];

        if (file_exists(JPATH_ROOT . '/' . $fileSrc . '.' . $extension)) {
          array_push($srcset['base']['srcset'], $fileSrc . '.' . $extension . ' ' . $breakpoints[$i] . 'w');
          array_push($srcset['base']['sizes'], '(min-width: ' . $breakpoints[$i] . 'px) ' . $breakpoints[$i] . 'px');
        }
        if (file_exists(JPATH_ROOT . '/' . $fileSrc . '.webp')) {
          array_push($srcset['webp']['srcset'], $fileSrc . '.webp ' . $breakpoints[$i] . 'w');
          array_push($srcset['webp']['sizes'], '(min-width: ' . $breakpoints[$i] . 'px) ' . $breakpoints[$i] . 'px');
        }
      }
    }

    if (!count($srcSets['base']['srcset'])) {
      return $image;
    }

    $output = '<picture class="responsive-image">';

    if ($srcSets['webp']['sizes']) {
      $output .= '<source type="image/webp" sizes="' . implode(', ', array_reverse($srcSets['webp']['sizes'])) . '" srcset="' . implode(', ', array_reverse($srcSets['webp']['srcset'])) . '">';
    }

    $output .= '<source type="' . $fileInfo->mime . '" sizes="' . implode(', ', array_reverse($srcSets['base']['sizes'])) . '" srcset="' . implode(', ', array_reverse($srcSets['base']['srcset'])) . '">';

    // Create the fallback img
    $fallBack = preg_replace('/src\s*=\s*".+?"/', 'src="' . $fileInfo->tag . '?' . $fileInfo->hash . '"', $image);

    if (strpos($fallBack, ' loading=') === false) {
      $fallBack = str_replace('<img ', '<img loading="lazy" ', $fallBack);
    }

    $output .= $fallBack . '</picture>';

    return  $output;
  }

  /**
   * Create the thumbs
   *
   * @param array    $breakpoints  the different breakpoints
   * @param string   $dirname      the folder name
   * @param string   $filename     the file name
   * @param string   $extension    the file extension
   *
   * @since  1.0
   */
  private function createImages($breakpoints = array(200, 320, 480, 768, 992, 1200, 1600, 1920), $dirname, $filename, $extension) {
    if (!count($breakpoints)) {
      return false;
    }

    if (extension_loaded('gd')){
      $driver = 'gd';
    }

    if (extension_loaded('imagick')){
      $driver = 'imagick';
    }

    if (!$driver) {
      file_put_contents(JPATH_ROOT . '/media/cached-resp-images-data/' . $dirname . '/' . $filename . '.json', 'false');
      return false;
    }

    // Create the images with width = breakpoint
    $manager = new ImageManager(array('driver' => $driver));

    // Getting the image info
    $info = @getimagesize(JPATH_ROOT . '/' . $dirname . '/' .$filename . '.' . $extension);

    if (empty($info)) {
      file_put_contents(JPATH_ROOT . '/media/cached-resp-images-data/' . $dirname . '/' . $filename . '.json', 'false');
      return false;
    }

    $imageWidth = $info[0];
    $imageHeight = $info[1];

    // Skip if the width is less or equal to the required
    if ($imageWidth <= (int) $breakpoints[0]) {
      file_put_contents(JPATH_ROOT . '/media/cached-resp-images-data/' . $dirname . '/' . $filename . '.json', 'false');
      return false;
    }

    // Check if we support the given image
    if (!in_array($info['mime'], array('image/jpeg', 'image/jpg', 'image/png'))) {
      file_put_contents(JPATH_ROOT . '/media/cached-resp-images-data/' . $dirname . '/' . $filename . '.json', 'false');
      return false;
    }

    $channels = $info['channels'];

    if ($info['mime'] == 'image/png') {
      $channels = 4;
    }

    if (!isset($info['bits'])) {
      $info['bits'] = 16;
    }

    $imageBits = ($info['bits'] / 8) * $channels;

    // Do some memory checking
    if (!self::checkMemoryLimit(array('width' => $imageWidth, 'height' => $imageHeight, 'bits' => $imageBits), $dirname . '/' .$filename . '.' . $extension)) {
      file_put_contents(JPATH_ROOT . '/media/cached-resp-images-data/' . $dirname . '/' . $filename . '.json', 'false');
      return false;
    }

    if (!is_dir(JPATH_ROOT . '/media/cached-resp-images/' . $dirname)) {
      if (
        !@mkdir(JPATH_ROOT . '/media/cached-resp-images/' . $dirname, 0755, true)
        && !is_dir(JPATH_ROOT . '/media/cached-resp-images/' . $dirname)
      ) {
        // @todo Log this
        file_put_contents(JPATH_ROOT . '/media/cached-resp-images-data/' . $dirname . '/' . $filename . '.json', 'false');
        return false;
      }
    }

    if (!is_dir(JPATH_ROOT . '/media/cached-resp-images-data/' . $dirname)) {
      if (
        !@mkdir(JPATH_ROOT . '/media/cached-resp-images-data/' . $dirname, 0755, true)
        && !is_dir(JPATH_ROOT . '/media/cached-resp-images-data/' . $dirname)
      ) {
        // @todo Log this
        file_put_contents(JPATH_ROOT . '/media/cached-resp-images-data/' . $dirname . '/' . $filename . '.json', 'false');
        return false;
      }
    }

    $data = new \stdClass();
    $data->srcsetBase = new \stdClass();
    $data->srcsetWebp = new \stdClass();
    $data->mime = $info['mime'];
    $data->hash = hash_file('md5', JPATH_ROOT . '/' . $dirname . '/' .$filename . '.' . $extension);
    $data->tag = 'media/cached-resp-images/' . $dirname . '/' . $filename .
      $this->sizeSplit . (int) $breakpoints[0]. '.' . $extension;

    for ($i = 0, $l = count($breakpoints); $i < $l; $i++) {
      if ($this->scaleUp || ($imageWidth >= (int) $breakpoints[$i])) {
        // Load the image
        $image = $manager->make(JPATH_ROOT . '/' . $dirname . '/' .$filename . '.' . $extension);
        // Resize the image
        $image->resize($breakpoints[$i], null, function ($constraint) {
          $constraint->aspectRatio();
          $constraint->upsize();
        });

        // Save the image
        $image->save(
          JPATH_ROOT . '/media/cached-resp-images/' . $dirname . '/' . $filename .
          $this->sizeSplit . (int) $breakpoints[$i]. '.' . $extension,
          $this->quality,
          $extension
        );

        $data->srcsetBase->{$breakpoints[$i]} = 'media/cached-resp-images/' . $dirname . '/' . $filename .
          $this->sizeSplit . $breakpoints[$i]. '.' . $extension . '?' . $data->hash . ' ' . $breakpoints[$i] . 'w';

        if ($driver === 'gd' && function_exists('imagewebp')) {
          // Save the image as webp
          $image->encode('webp', $this->quality);
          $image->save(
            JPATH_ROOT . '/media/cached-resp-images/' . $dirname . '/' . $filename .
            $this->sizeSplit . (int) $breakpoints[$i]. '.webp',
            $this->quality,
            'webp'
          );
        }

        if ($driver === 'imagick' && \Imagick::queryFormats('WEBP')) {
          // Save the image as webp
          $image->encode('webp', $this->quality);
          $image->save(
            JPATH_ROOT . '/media/cached-resp-images/' . $dirname . '/' . $filename . $this->sizeSplit . $breakpoints[$i]. '.webp',
            $this->quality,
            'webp'
          );
          $data->srcsetWebp->{$breakpoints[$i]} = 'media/cached-resp-images/' . $dirname . '/' . $filename .
            $this->sizeSplit . $breakpoints[$i]. '.webp?' . $data->hash . ' ' . $breakpoints[$i] . 'w';
        }

        if ($driver === 'imagick' && \Imagick::queryFormats('WEBP')) {
          // Save the image as webp
          $image->encode('webp', $this->quality);
          $image->save(
            JPATH_ROOT . '/media/cached-resp-images/' . $dirname . '/' . $filename . $this->sizeSplit . $breakpoints[$i]. '.webp',
            $this->quality,
            'webp'
          );
          $data->srcsetWebp->{$breakpoints[$i]} = 'media/cached-resp-images/' . $dirname . '/' . $filename .
            $this->sizeSplit . $breakpoints[$i]. '.webp?' . $data->hash . ' ' . $breakpoints[$i] . 'w';
        }

        $image->destroy();
      }
    }

    file_put_contents(JPATH_ROOT . '/media/cached-resp-images-data/' . $dirname . '/' . $filename . '.json', json_encode($data));
    return $data;
  }

  /**
   * Check memory boundaries
   *
   * @param object  $properties   the Image properties object
   * @param string  $imagePath    the image path
   *
   * @return boolean
   *
   * @since  3.0.3
   *
   * @author  Niels Nuebel: https://github.com/nielsnuebel
   */
  protected static function checkMemoryLimit($properties, $imagePath) {
    $memorycheck = ($properties['width'] * $properties['height'] * $properties['bits']);
    $memorycheck_text = $memorycheck / (1024 * 1024);
    $memory_limit = ini_get('memory_limit');

    if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
      if ($matches[2] == 'M') {
        $memory_limit_value = $matches[1] * 1024 * 1024; // nnnM -> nnn MB
      } else if ($matches[2] == 'K') {
        $memory_limit_value = $matches[1] * 1024; // nnnK -> nnn KB
      }
    }

    if (isset($memory_limit_value) && $memorycheck > $memory_limit_value) {
      $app = Factory::getApplication();
      $app->enqueueMessage(Text::sprintf('Image too big to be processed' ,$imagePath, $memorycheck_text, $memory_limit), 'error');

      return false;
    }

    return true;
  }
}

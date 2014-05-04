<?php

/**
 * @version     $Id: contentpicturefill.php 2/8/2013 Stuart Laverick $
 * @package     Joomla
 * @subpackage  Content.contentpicturefill
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters. All rights reserved.
 * @license     GNU/GPL, see LICENSE.php
 * Joomla! is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details. 
 */
defined('_JEXEC') or die('Restricted access');
jimport('joomla.plugin.plugin');

// Load user_profile plugin language
$lang = JFactory::getLanguage();
$lang->load('plg_content_contentpicturefill', JPATH_ADMINISTRATOR);

class plgContentResponsiveimages extends JPlugin {

    private $size_array = Array();

    public function __construct(& $subject, $config) {
        parent::__construct($subject, $config);
        plgContentResponsiveimages::log($config);
        $this->loadJavascript();

        // Get the array of image sizes defined in the plugin Config
        $this->size_array = $this->getSizeArray();
        // Bool to use captions or not
        $this->captions = $this->params->get('captions');
    }

    /**
     * Converts the image size settings from the plugin configuration into a more useful format
     * @return array a size name based dictionary of image sizes
     */
    private function getSizeArray() {

        $sizes = $this->params->get('imagesizes');

        $size_array = Array();

        foreach ($sizes as $size) {
            $size_array[$size->name] = array(
            'width' => $size->width,
            'height' => $size->height,
            'resolution' => $size->resolution
            );
        }
        return $size_array;
    }

    /**
     * this function searches through the saved text and then passes any images it finds 
     * to the resize image function
     */
    public function onContentBeforeSave($context, $article, $isNew) {
        // onContentBeforeSave is run when you save an article or category in joomla

        $text = '';
        // we have to look in different places depending on if this is an article or a category
        if ($context == 'com_content.article') {
            $text = $article->introtext;
        } else if ($context == 'com_categories.category') {
            $text = $article->description;
        }
        $image_array = $this->findIMGTags($text);
        foreach ($image_array as $image) {
            $this->resizeImage($image->url);
        }
    }

    /**
     * onContentPrepare is run before joomla displays a page
     * this function replaces any image tags with the mark up necessary for picturefill.js to do its thing
     * @param type $context
     * @param type $article
     * @return boolean 
     */
    public function onContentPrepare($context, $article) {
        plgContentResponsiveimages::log($context);
        plgContentResponsiveimages::log($article);
        // if we are neither an article or category ('text') abort
        if ($context != 'text' && $context != 'com_content.article')
            return false;
        // get text
        if ($context == 'com_content.article')
            $text = (isset($article->fulltext) && $article->fulltext ) ?
                    $article->fulltext : $article->introtext;
        else if ($context == 'text')
            $text = $article->text;

        // load dom object
        $dom = new DOMDocument();
        $quotes = array(
            '’' => "'",
            '‘' => "'",
            "“" => '"',
            "”" => '"'
        );
        $text = str_replace(array_keys($quotes), $quotes, $text);
        $dom->encoding = 'utf-8';
        $dom->version = '1.0';
        $dom->loadHTML(utf8_decode($text));


        $image_nodes = $dom->getElementsByTagName('img');
        $nodes_to_remove = Array();
        $no_script_tags = Array();
        foreach ($image_nodes as $image_node) {
            $no_script_src = $src = $image_node->getAttribute('src');
            $alt = $image_node->getAttribute('alt');
            $title = $image_node->getAttribute('title');
            $class = $image_node->getAttribute('class');
            $parent_class = $image_node->parentNode->getAttribute('class');
            // If the element has a class of ignore we ignore it
            if (FALSE !== strpos($class, 'ignore'))
                continue;

            $image_host = parse_url($src, PHP_URL_HOST);
            if ($image_host && $image_host != $_SERVER['SERVER_NAME']) {
                // if image is not local we cannot resize it so just have to use it as is
                continue;
            }
            $url = explode('/', $src);
            plgContentResponsiveimages::log(parse_url($src));
            // if the url starts with 'images' replace it with 'image_sizes' or else append '/image_sizes' 
            // to the start
            if ($url[0] == 'images') {
                $url[0] = '/image_sizes';
            } else {
                array_unshift($url, '/image_sizes');
            }
            // remove file from end of url
            $file = array_pop($url);
            $url = implode('/', $url);

            $full_image_size = getimagesize(JPATH_BASE . '/' . $src);
            // split file name into name and type
            $file_array = explode('.', $file);
            $file_name = $file_array[0];
            $file_type = (isset($file_array[1])) ? $file_array[1] : 'jpg';

            // generate html
            $picture_fill = $dom->createElement('figure');
            $picture_fill->setAttribute('class', 'picture-fill ' . $class . ' ' . $parent_class);
            $picture_fill->setAttribute('data-picture', NULL);
            $picture_fill->setAttribute('data-alt', $alt);

            $full_image_width = $full_image_size[0];

            // add link for lightbox
            $lightbox_anchor = $dom->createElement('a');
            $lightbox_anchor->setAttribute('href', $src);
            $lightbox_anchor->setAttribute('title', $alt);
            if ($full_image_width)
                $lightbox_anchor->setAttribute('data-width', $full_image_width);
            $picture_fill->appendChild($lightbox_anchor);
            // append a fallback image
            $fallback_image = 'cache' . $url . '/' . $file_name . '_' . 'large.' . $file_type;
            $picture_fill_fallback = $dom->createElement('span');
            $picture_fill_fallback->setAttribute('data-src', $fallback_image);
            $picture_fill->appendChild($picture_fill_fallback);
            // loop through sizes
            foreach ($this->size_array as $key => $size) {
                $file_url = $url . '/' . $file_name . '_' . $key . '.' . $file_type;

                // if we encounter a file size has not been created, run the resizeImage function
                // which will create any missing image sizes
                if (!file_exists(JPATH_CACHE . '/' . $file_url))
                    $this->resizeImage($src);

                // If the resized images were not created then bail
                if (!file_exists(JPATH_CACHE . '/' . $file_url))
                    continue;

                $image_size = getimagesize(JPATH_CACHE . '/' . $file_url);

                // if the size has been changed, also run the resizeImage function
                if ($image_size && $image_size[0] != $size['width']) {
                    $this->resizeImage($src);
                    $image_size = getimagesize(JPATH_CACHE . '/' . $file_url);
                }
                if ($file_exists) {
                    // append new image to dom
                    $picture_size = $dom->createElement('span');
                    if ($key == 'large')
                        $no_script_src = "/cache" . $file_url;
                    $picture_size->setAttribute('data-media', '(min-width: ' . $size['resolution'] . 'px)');
                    $picture_size->setAttribute('data-src', "/cache" . $file_url);
                    if (isset($image_size[0]))
                        $picture_size->setAttribute('data-width', $image_size[0]);
                    $picture_fill->appendChild($picture_size);
                }
            }
            $no_script = $dom->createElement('noscript');

            $no_script->setAttribute('data-no-script-src', $no_script_src);
            $no_script->setAttribute('data-no-script-alt', $alt);
            $no_script_tags[] = $no_script; // do not add image tag while iterating over list of image tags!
            $picture_fill->appendChild($no_script);
            if ($this->captions && $title) {
                $caption = $dom->createElement('figcaption');
                $caption_text = $dom->createTextNode($title);
                $caption->appendChild($caption_text);
                $picture_fill->appendChild($caption);
            }
            $image_node->parentNode->parentNode->insertBefore($picture_fill, $image_node->parentNode);
            $nodes_to_remove[] = $image_node; // removing nodes directly from domNodeList breaks foreach loop
        }

        foreach ($nodes_to_remove as $node_to_remove) {
            $node_to_remove->parentNode->removeChild($node_to_remove);
        }
        foreach ($no_script_tags as $no_script_tag) {
            $no_script_img = $dom->createElement('img');
            $no_script_img->setAttribute('src', $no_script_tag->getAttribute('data-no-script-src'));
            $no_script_img->setAttribute('alt', $no_script_tag->getAttribute('data-no-script-alt'));
            $no_script_tag->removeAttribute('data-no-script-src');
            $no_script_tag->removeAttribute('data-no-script-alt');
            $no_script_tag->appendChild($no_script_img);
        }

        $article->text = preg_replace('/^<!DOCTYPE.+?>/', '', str_replace(array('<html>', '</html>', '<body>', '</body>'), array('', '', '', ''), $dom->saveHTML()));
    }

    public function onAfterInitialise() {
        $this->loadJavascript();
    }

    private function loadJavascript() {
        $document = JFactory::getDocument();

        $document->addScript('/plugins/content/imagesizer/js/picturefill.js');
    }

    private function findIMGTags($content) {
        // finds img tags in a bunch of html
        if (!$content)
            return Array();
        $image_array = Array();
        $dom = new DOMDocument;
        $dom->loadXML($content);
        $image_nodes = $dom->getElementsByTagName('img');

        foreach ($image_nodes as $key => $image_node) {
            $image_array[$key]->url = $image_node->getAttribute('src');
            $image_array[$key]->alt = $image_node->getAttribute('alt');
        }
        return $image_array;
    }

    private function resizeImage($url) {
        // creates required sizes for a given image
        if (!function_exists('gd_info'))
            return false;
        plgContentResponsiveimages::log($url);
        $image_host = parse_url($url, PHP_URL_HOST);

        if ($image_host && $image_host != $_SERVER['SERVER_NAME']) {
            // this is a local plugin for local images, there's nothing for you here
            return false;
        }
        // this is a local image for local people
        $original_image = JPATH_ROOT . '/' . $url;
        $parts = pathinfo($url);
        $path = $parts['dirname'];
        $file = $parts['basename'];
        $filename = $parts['filename'];
        $ext = $parts['extension'];
        $replace = 1;
        // if the url starts with 'images' replace it with 'image_sizes' or else append '/image_sizes' 
        // to the start
        dump($path, 'Image Path');
        if (strpos('images', ltrim($path, '/')) === 0)
            $path = str_replace('images', 'image_sizes', $path, $replace);

        dump($path, 'Image Size Path');
//        if ($url[0] == 'images') {
//            $url[0] = '/image_sizes';
//            $path = '';
//        } else {
//            $path = '/image_sizes';
//        }
        // construct a path from elements of the url, excluding the file name, if necessary making directories as we go
//        for ($i = 0; $i < count($url) - 1; $i++) {
//            $path .= $url[$i] . '/';
        if (!is_dir(JPATH_CACHE . $path)) {
            if (!mkdir(JPATH_CACHE . $path, 0775, true))
                plgContentResponsiveimages::log('Failed to create Directory ' . JPATH_CACHE . $path);
        }
//        }
        $path = JPATH_CACHE . $path;
        dump($path, 'Cache Path');
//        $url = explode('/', $url);
//        $file = $url[count($url) - 1];
        // split the file name and file extention
//        $filename_array = explode('.', $file);
//        $file_name = $filename_array[0];
        // get image size. decide if portrait or landscape
        $file_info = getimagesize($original_image);
        $format = ($file_info[0] > $file_info[1]) ? 'landscape' : 'portrait';

        switch ($file_info[2]) {
            case 1: // gif
                $old_image = imagecreatefromgif($original_image);
                $file_type = 'gif';
                break;
            case 2: // jpg
                $old_image = imagecreatefromjpeg($original_image);
                $file_type = 'jpg';
                break;
            case 3: // png
                $old_image = imagecreatefrompng($original_image);
                $file_type = 'png';
                break;
            default:
        }

        foreach ($this->size_array as $key => $size) {
            if (ctype_alpha($key)):
                $new_file_name = $filename . '_' . $key . '.' . $ext;

                // if this image size is bigger than the source image, 
                // make the file but leave it at the original size
                if ($size[0] > $file_info[0]) {
                    $size[0] = $file_info[0];
                }
                if ($size[1] > $file_info[1]) {
                    $size[1] = $file_info[1];
                }
                $ratio = ($format == 'landscape') ? $size[0] / $file_info[0] : $size[1] / $file_info[1];

                $destination_w = ($format == 'landscape') ? $size[0] : floor($file_info[0] * $ratio);
                $destination_h = ($format == 'portrait') ? $size[1] : floor($file_info[1] * $ratio);
                $do_it_anyway = false;
                if (file_exists($path . $new_file_name)) {
                    // if the current file exists, but is the wrong size
                    $current_file_image_info = getimagesize($path . $new_file_name);
                    if ($current_file_image_info[0] != $destination_w || $current_file_image_info[1] != $destination_h) {
                        $do_it_anyway = true;
                    }
                }
                if (!file_exists($path . $new_file_name) || $do_it_anyway === true) {
                    $new_image = imagecreatetruecolor($destination_w, $destination_h);

                    imagecopyresampled($new_image, $old_image, 0, 0, 0, 0, $destination_w, $destination_h, $file_info[0], $file_info[1]);

                    // output image to file
                    switch ($file_info[2]) {
                        case 1: // gif
                            imagegif($new_image, $path . $new_file_name);
                            break;
                        case 2: // jpg
                            imagejpeg($new_image, $path . $new_file_name, 100);
                            break;
                        case 3: // png
                            imagepng($new_image, $path . $new_file_name, 9);
                            break;
                        default:
                    }
                    imagedestroy($new_image);
                }
            endif; // if ctype_alpha($key)
        }
        imagedestroy($old_image);
    }

    public static function log($message = '') {
        jimport('joomla.error.log');
        $errorLog = & JLog::getInstance();
        $trace = debug_backtrace();
        $caller = $trace[1];
        $errorLog->addEntry(array('status' => 'DEBUG', 'comment' => isset($caller['class']) ? $caller['class'] . '::' . $caller['function'] : $caller['function']));
        if ($message)
            $errorLog->addEntry(array('status' => 'DEBUG', 'comment' => is_array($message) || is_object($message) ? print_r($message, true) : $message));
    }

}

?>

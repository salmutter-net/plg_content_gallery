<?php
/**
 *  @Copyright
 *  @package     GALLERY - Content Image Gallery
 *  @author      Philipp Salmutter
 *  @version     0-1 - 2014-04-03
 *
 *  @license Commercial
 */

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\System\Imagetransformer\Extension\Imagetransformer;
use Joomla\Registry\Registry;

defined('_JEXEC') or die('Restricted access');

class PlgContentGallery extends CMSPlugin implements SubscriberInterface
{
    protected $_absolute_path;
    protected $_live_site;
    protected $_rootfolder;
    protected $_images_dir;
    public $imageTransformer = false;

    // Request-scoped counters (replace the old $_SESSION usage)
    private $galleryCount = -1;
    private $galleryCountArticles = -1;

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare' => 'onContentPrepare',
        ];
    }

    public function __construct($subject, $config) {
        parent::__construct($subject, $config);

        $app = Factory::getApplication();

        if ($app->isClient('administrator')) {
            return;
        }

        if (class_exists(Imagetransformer::class) && PluginHelper::isEnabled('system', 'imagetransformer')) {
            $this->imageTransformer = 'Imagetransformer';
        } elseif (class_exists('ImgResizeCache')) {
            $this->imageTransformer = 'ImgResizeCache';
        }

        $this->_absolute_path = JPATH_SITE;
        $this->_live_site = rtrim(Uri::base(), '/');

        $this->_params = array();
    }

    public function onContentPrepare(ContentPrepareEvent $event) {
        $object = $event->getArgument('subject');
        $textToParse = $object->text;
        $type = 'text';
        if (empty($textToParse) && !empty($object->introtext)) {
            $textToParse = $object->introtext;
            $type = 'introtext';
        } elseif (empty($textToParse) && !empty($object->fulltext)) {
            $textToParse = $object->fulltext;
            $type = 'fulltext';
        }
        if (!preg_match('@{gallery}(.*){/gallery}@Us', $textToParse)) {
            return;
        }

        $this->_rootfolder = '/images/';

        if (preg_match_all('@{gallery}(.*){/gallery}@Us', $textToParse, $matches, PREG_PATTERN_ORDER) > 0) {
            $this->galleryCountArticles++;

            $this->_params['lang'] = Factory::getApplication()->getLanguage()->getTag();

            foreach($matches[0] as $match) {
                $this->galleryCount++;
                $galleryPath = preg_replace('@{.+?}@', '', $match);

                unset($images);
                $imageCount = 0;
                if ($dh = @opendir($this->_absolute_path . $this->_rootfolder . $galleryPath)) {
                    while(($file = readdir($dh)) !== false) {
                        if ( substr(strtolower($file), -3) == 'jpg'
                        OR substr(strtolower($file), -3) == 'JPG'
                        OR substr(strtolower($file), -4) == 'jpeg'
                        OR substr(strtolower($file), -4) == 'JPEG'
                        OR substr(strtolower($file), -3) == 'gif'
                        OR substr(strtolower($file), -3) == 'GIF'
                        OR substr(strtolower($file), -3) == 'png'
                        OR substr(strtolower($file), -3) == 'PNG'
                        ) {
                            $images[$file] = 'images/' . $galleryPath . '/' . $file;
                            $imageCount++;
                        }
                    }
                    if (!empty($images)) {
                        ksort($images);
                    }
                    closedir($dh);
                }

                if ($imageCount) {
                    $this->renderGallery($type, $object, $images, $galleryPath);
                } else {
                    $html = '<strong>'.Text::_('Im angegebenen Ordner befinden sich keine Bilder bzw. können sie nicht ausgelesen werden.').'</strong><br />'.Text::_('Pfad: ').' '.$this->_live_site.$this->_rootfolder.$this->_images_dir;
                }
            }
        }
        unset($textToParse);
    }

    private function parseWidthList($value): array {
        $widths = [];
        foreach (explode(',', (string)$value) as $part) {
            $w = (int)trim($part);
            if ($w > 0) {
                $widths[] = $w;
            }
        }
        return array_values(array_unique($widths));
    }

    private function getContainedImageSize($imagePath, $maxW, $maxH) {
        $fullPath = $this->_absolute_path . '/' . $imagePath;
        $size = @getimagesize($fullPath);
        if (!$size || $size[0] <= 0 || $size[1] <= 0) {
            return [(int)$maxW, (int)$maxH];
        }

        $width  = $size[0];
        $height = $size[1];

        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($fullPath, 'IFD0');
            if (is_array($exif) && !empty($exif['Orientation'])) {
                $orientation = (int) $exif['Orientation'];
                if (in_array($orientation, [5, 6, 7, 8], true)) {
                    $tmp = $width;
                    $width = $height;
                    $height = $tmp;
                }
            }
        }

        $scale = min($maxW / $width, $maxH / $height);
        return [(int)round($width * $scale), (int)round($height * $scale)];
    }

    function renderGallery($type, &$object, $images, $galleryPath) {
        if (empty($type) || empty($object) || empty($images) || empty($galleryPath)) {
            echo '<strong>'.Text::_('Essentielle Variable nicht übergeben!').'</strong>';
            return NULL;
        }
        if ( $this->imageTransformer === 'Imagetransformer' ) {
            $plugin = PluginHelper::getPlugin('system', 'imagetransformer');
            $pluginParams = new Registry($plugin->params);
            $imageCacheFolder = JPATH_ROOT . DIRECTORY_SEPARATOR . $pluginParams->get('image-cache-folder', 'images-cache');
        }
        if ($this->imageTransformer === 'ImgResizeCache') {
            $resizer = new ImgResizeCache();
        }
        $thumbW   = (int)$this->params->get('thumb_width', 394);
        $thumbH   = (int)$this->params->get('thumb_height', 296);
        $thumbSrc = $this->parseWidthList($this->params->get('thumb_srcset', '788,1182'));
        $thumbSz  = trim((string)$this->params->get('thumb_sizes', ''));
        $fullW    = (int)$this->params->get('full_width', 1660);
        $fullH    = (int)$this->params->get('full_height', 1245);
        $fullSrc  = $this->parseWidthList($this->params->get('full_srcset', '480,768,1024,1280,1660'));
        $fullSz   = trim((string)$this->params->get('full_sizes', ''));
        $columns  = max(1, (int)$this->params->get('columns', 4));
        $maxHeight = max(0, (int)$this->params->get('thumb_max_height', 180));

        $html = '';
        if ($this->galleryCountArticles === 0) {
            // Include the gallery's CSS and JS files once in the head of the page
            $head = array();
            $document = Factory::getApplication()->getDocument();
            $head[] = '<link rel="stylesheet" href="'.$this->_live_site.'/plugins/content/gallery/plugin_gallery/photoswipe.css" type="text/css" media="screen" />';
            $head[] = '<link rel="stylesheet" href="'.$this->_live_site.'/plugins/content/gallery/plugin_gallery/gallery.css" type="text/css" media="screen" />';
            $head[] = '<script type="text/javascript" src="'.$this->_live_site.'/plugins/content/gallery/plugin_gallery/photoswipe.umd.min.js"></script>';
            $head[] = '<script type="text/javascript" src="'.$this->_live_site.'/plugins/content/gallery/plugin_gallery/photoswipe-lightbox.umd.min.js"></script>';
            $head[] = '<script type="text/javascript" src="'.$this->_live_site.'/plugins/content/gallery/plugin_gallery/gallery.js"></script>';
            $head = "\n".implode("\n", $head)."\n";
            if (is_object($document) && method_exists($document, 'addCustomTag')) {
                $document->addCustomTag($head);
            }
        }

        // Generate the image thumbnail-list
        $html .= '<div class="gallery-wrapper" itemscope itemtype="http://schema.org/ImageGallery" style="--gallery-columns:' . $columns . ';--gallery-max-height:' . $maxHeight . 'px">';
        $count = 1;
        foreach ($images as $key => $image) {
            $largeSrcset = '';
            $smallSrcset = '';
            if ($this->imageTransformer === 'Imagetransformer') {
                $largeImgSrc = Imagetransformer::generateUrl($image, [
                    'w' => $fullW,
                    'h' => $fullH,
                    'fit' => 'contain',
                    'q' => 70,
                    'fm' => 'webp'
                ]);
                $largeImgSize = $this->getContainedImageSize($image, $fullW, $fullH);
                $smallImgSrc = Imagetransformer::generateUrl($image, [
                    'w' => $thumbW,
                    'h' => $thumbH,
                    'fit' => 'contain',
                    'q' => 70,
                    'fm' => 'webp'
                ]);
                $smallImgSize = $this->getContainedImageSize($image, $thumbW, $thumbH);

                // Build srcset entries, scaled proportionally to the configured bounding box
                $entries = [];
                foreach ($fullSrc as $w) {
                    if ($w <= $fullW) {
                        $h = max(1, (int)round($w * $fullH / $fullW));
                        $entries[] = Imagetransformer::generateUrl($image, ['w' => $w, 'h' => $h, 'fit' => 'contain', 'q' => 70, 'fm' => 'webp']) . ' ' . $w . 'w';
                    }
                }
                $largeSrcset = implode(', ', $entries);

                $entries = [];
                foreach ($thumbSrc as $w) {
                    $h = max(1, (int)round($w * $thumbH / $thumbW));
                    $entries[] = Imagetransformer::generateUrl($image, ['w' => $w, 'h' => $h, 'fit' => 'contain', 'q' => 70, 'fm' => 'webp']) . ' ' . $w . 'w';
                }
                $smallSrcset = implode(', ', $entries);
            } else if ($this->imageTransformer === 'ImgResizeCache') {
                $largeImgSrc = htmlspecialchars($resizer->resize($image, ['w' => $fullW, 'h' => $fullH, 'crop' => false, 'canvas-color' => '#000']));
                $largeImgSize = $this->getContainedImageSize($image, $fullW, $fullH);
                $smallImgSrc = htmlspecialchars($resizer->resize($image, ['w' => $thumbW, 'h' => $thumbH, 'crop' => false, 'canvas-color' => '#fff']));
                $smallImgSize = $this->getContainedImageSize($image, $thumbW, $thumbH);
            } else {
                $largeImgSrc = $image;
                $largeImgSize = @getimagesize($this->_absolute_path . '/' . $image);
                $smallImgSrc = $image;
                $smallImgSize = $largeImgSize;
            }

            if (!$largeImgSize || $largeImgSize[0] <= 0 || $largeImgSize[1] <= 0) {
                $largeImgSize = [$fullW, $fullH];
            }
            if (!$smallImgSize || $smallImgSize[0] <= 0 || $smallImgSize[1] <= 0) {
                $smallImgSize = [$thumbW, $thumbH];
            }

            $linkAttrs = 'href="' . $largeImgSrc . '" data-pswp-width="' . (int)$largeImgSize[0] . '" data-pswp-height="' . (int)$largeImgSize[1] . '"';
            if ($largeSrcset !== '') {
                $linkAttrs .= ' data-pswp-srcset="' . htmlspecialchars($largeSrcset) . '"';
                if ($fullSz !== '') {
                    $linkAttrs .= ' data-pswp-sizes="' . htmlspecialchars($fullSz) . '"';
                }
            }

            $imgAttrs = 'class="gallery-thumbnail-image" loading="lazy" width="' . (int)$smallImgSize[0] . '" height="' . (int)$smallImgSize[1] . '" src="' . $smallImgSrc . '"';
            if ($smallSrcset !== '') {
                $imgAttrs .= ' srcset="' . htmlspecialchars($smallSrcset) . '"';
                if ($thumbSz !== '') {
                    $imgAttrs .= ' sizes="' . htmlspecialchars($thumbSz) . '"';
                }
            }

            $html .= ' <figure class="gallery-thumbnail">';
            $html .= '     <a ' . $linkAttrs . ' aria-label="Öffnet Bild Nummer ' . $count . '">';
            $html .= '         <img ' . $imgAttrs . ' alt="" />';
            $html .= '     </a>';
            $html .= ' </figure>';
            $count++;
        }
        $html .= '</div>';
        unset($images, $image, $imgSrc);

        if ($type === 'introtext') {
            $object->introtext = preg_replace('@(<p>)?{gallery}'.$galleryPath.'{/gallery}(</p>)?@s', $html, $object->introtext);
        } elseif ($type === 'fulltext') {
            $object->fulltext = preg_replace('@(<p>)?{gallery}'.$galleryPath.'{/gallery}(</p>)?@s', $html, $object->fulltext);
        } else {
            $object->text = preg_replace('@(<p>)?{gallery}'.$galleryPath.'{/gallery}(</p>)?@s', $html, $object->text);
        }
        unset($html, $galleryPath);
    }
}

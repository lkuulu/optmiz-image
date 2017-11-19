<?php

namespace STHImage;

use Symfony\Component\HttpFoundation\Response;
use Silex\Application;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

include('BmpMetaData.php');

class ImageRenderer
{


    const ROOT = '/var/www/optmiz/image/files';
    const CACHE = '/var/www/optmiz/image/cache';
    const PRESETS = [
        'portrait' => ['w' => 3, 'h' => 4],
        'square' => ['w' => 1, 'h' => 1],
        '14x11' => ['w' => 14, 'h' => 11],
        'landscape' => ['w' => 4, 'h' => 3],
        'large' => ['w' => 16, 'h' => 9],
        'xlarge' => ['w' => 24, 'h' => 9]
    ];

    /**
     * @param $pathToFile
     * @param $width
     * @param $preset
     * @param $screenDensity
     * @param Application $app
     * @return Response
     * usage
     * /path/to/file.ext/w:1000/p:portrait/d:[?gen]
     *
     * /path/to/ => path to image
     * file.ext  => image file
     * w:1000     => will return a 1000 pixel width image
     * p:portrait  => preset in (original|square|portrait|landscape[large|xlarge)
     * => original
     * => square :     1 x 1
     * => portrait :   3 x 4
     * => landscape :  4 x 3
     * => large     : 16 x 9
     * => xlarge    : 24 x 9
     * d:2 => will return an image width X2  multiplier for retinas display
     * ?gen      => use to regenerate cache for this image/preset/width
     */
    public function getImage($pathToFile, $width, $preset, $screenDensity, Application $app)
    {
        $getParams = $app['request_stack']->getCurrentRequest()->query->all();
        $accept = $app['request_stack']->getCurrentRequest()->headers->get('Accept');
        $gen = isset($getParams['gen']);
        $fullPath = self::ROOT . '/' . $pathToFile;
        $acceptedContentType = (stripos($accept, 'image/webp') !== false) ? 'webp' : 'jpeg';
//        $app['monolog']->debug(" :: $pathToFile");
//        $app['monolog']->debug(" :: $width");
//        $app['monolog']->debug(" :: $preset");
//        $app['monolog']->debug(" :: $screenDensity");
        // basic checks
        if (!file_exists($fullPath)) {
            return new Response("Image not found", 404);
        }

        // Is image already calculated for this preset/width/webpOrNot/screenDensity
        // Calculate a path corresponding to those parameters
        // Pattern will be "cache"/<pathtofile>/<preset>/<width>/<Density>/<webpOrNot>
        $cacheDir = self::CACHE . "/$pathToFile/$preset/$width/$screenDensity/";
        $cacheFile = $cacheDir . $acceptedContentType;
        //$debug=1;
        if (!file_exists($cacheFile) || $gen || isset($debug)) {


            // create cache folder
            if (!file_exists($cacheDir)) {
                $fs = new Filesystem();
                try {
                    $fs->mkdir($cacheDir, 0755);
                } catch (IOExceptionInterface $e) {
                    return new Response("Bad Image Request - An error occurred while creating cache directory", 400);
                }
            }

            // Load Image object
            $im = $this->imageCreateFromAny($fullPath);
            if ($im === false) {
                return new Response(" 415 Unsupported Media Type", 415);
            }

            // first, get info from the image file:size, ...
            $metasData = ImageMetaData::initMetaDataImage($fullPath);
            $imageW = $metasData->size[0];
            $imageH = $metasData->size[1];
            $imageCropRatio = $metasData->getCropRatio();
            $imagePoi = $metasData->getPoi();

            // if parameter "width" is not present, set same width than original image
            if ($width == 'same') $width = $imageW;

            $coordinates = (object)$this->getCropCoordinates($preset, $imageW, $imageH, $imageCropRatio, $imagePoi);

            $dst_im = $this->getFinalImage($im, $width, $coordinates, $screenDensity);

            imagedestroy($im);

            $this->saveImageCache($dst_im, $cacheFile, $acceptedContentType);
            imagedestroy($dst_im);
        }

        return $this->serveImageResponse($cacheFile, "$pathToFile.p$preset-w$width-d$screenDensity.$acceptedContentType", "image/$acceptedContentType");
    }

    /**
     * @param $cacheFile
     * @param $filename
     * @param $contentType
     * @return Response
     */
    private function serveImageResponse($cacheFile, $filename, $contentType)
    {
        $response = new Response();
        $responseHeaders = $response->headers;
        $responseHeaders->set('Content-type', $contentType);
        $responseHeaders->set('Content-size', filesize($cacheFile));
        $responseHeaders->set('Content-disposition', 'filename="' . $filename . '"');
        $response->setContent(file_get_contents($cacheFile));
        return $response;
    }

    /**
     * @param $dst_im : image resource to save to filesystem
     * @param $cacheFile : image filename
     * @param $acceptedContentType : for browser who knows webp
     */
    private function saveImageCache($dst_im, $cacheFile, $acceptedContentType)
    {
        // finally, send the best content type for the accepted query header
        if ($acceptedContentType == 'webp') {
            // browser webp capabilities
            /*
              Known issue in imagewebp library.
              Sometimes, file length is not correct, so append a "\0" char to fix file)
            */
            // save it to cache
            imagewebp($dst_im, $cacheFile, 85);
            if (filesize($cacheFile) % 2 == 1) {
                file_put_contents($cacheFile, "\0", FILE_APPEND);
            }
        } else {
            // browser doesn't accept webp so serve a jpeg image
            imagejpeg($dst_im, $cacheFile, 85);
        }
    }

    /**
     * @param $im
     * @param $width
     * @param $coordinates
     * @param $screenDensity
     * @return object : image resource
     */
    private function getFinalImage($im, $width, $coordinates, $screenDensity)
    {
        $src_w = $coordinates->cx;
        $src_h = $coordinates->cy;

        // create cropped image container
        $cropped_im = ImageCreateTrueColor($coordinates->cx, $coordinates->cy);
        imagecopy($cropped_im, $im, 0, 0, $coordinates->x, $coordinates->y, $coordinates->cx, $coordinates->cy);
        // calculation of the final size from the original image with chosen ratio and chosen width (maximized by screenDensity)
        $dst_w = $width * $screenDensity;
        $dst_h = round(($dst_w / $src_w) * $src_h);
        $dst_im = ImageCreateTrueColor($dst_w, $dst_h);

        // copy file into the destination container with the final size
        imagecopyresampled($dst_im, $cropped_im, 0, 0, 0, 0, $dst_w, $dst_h, $src_w, $src_h);
        imagedestroy($cropped_im);
        return $dst_im;
    }


    /**
     * @param $preset
     * @param $imageW
     * @param $imageH
     * @param $imageCropRatio
     * @param $imagePoi
     * @return array
     *
     */
    private function getCropCoordinates($preset, $imageW, $imageH, $imageCropRatio, $imagePoi)
    {
        // many manipulations
        // #DONE : cropping : center
        // #DONE : resizing : match preset take care of retina screen multiplier (preset in path)
        // #DONE : cropping : coordinates stored in a json in iptc "comment" metadata [2#120] format {"square":{"x":1200,"y":600,"w":1248,"h":1248},"portrait":{"x":1200,"y":600,"w":936,"h":1248}}
        // #DONE : cropping : center point of interest

        $ratio = (isset(self::PRESETS[$preset])) ? self::PRESETS[$preset] : ['w' => $imageW, 'h' => $imageH];

        if (!$imageCropRatio && !$imagePoi) {
            return ['x' => 0, 'y' => 0, 'cx' => $imageW, 'cy' => $imageH];
        } else {
            // change ratio by cropping the original image
            // calculate new bounds center cropped if necessary
            // 3 cases :
            if ($preset == 'original') {
                // if parameter "preset" wasn't present, set original ratio is kept.
                // nothing to crop store original x, y, cx, cy
                $x = 0;
                $y = 0;
                $cx = $imageW;
                $cy = $imageH;
            } elseif (isset($imageCropRatio[$preset])) {
                // use ratio stored in the image iptc comment json
                $cx = $imageCropRatio[$preset]['w'];
                $x = $imageCropRatio[$preset]['x'];
                $y = $imageCropRatio[$preset]['y'];
                $cy = $imageCropRatio[$preset]['h'];
            } elseif (isset($imagePoi)) {
                // use Point Of Interest to determine maximal image for the ratio & width
                // Should store
                //   min-x from horizontal edges
                //   min-y from horizontal edges
                $minW = min($imagePoi['x'], $imageW - $imagePoi['x']) * 2;
                $minH = min($imagePoi['y'], $imageH - $imagePoi['y']) * 2;

                // Is poi OK for ratio coordinates
                if (($minW > $ratio['w']) && ($minH > $ratio['h'])) {
                    // minimum cropping is possible
                    if ($ratio['w'] > $ratio['h']) {
                        // wider than higher
                        // get max width around poi
                        // restriction to the height limit for this ratio ie minH
                        $cx = min($minH * $ratio['w'] / $ratio['h'], $minW); // $minW;
                        $x = $imagePoi['x'] - ($cx / 2);
                        $cy = $cx * $ratio['h'] / $ratio['w'];
                        $y = ($imagePoi['y'] - ($cy / 2));
                    } else {
                        // higher then wider
                        // get max height around poi
                        // restrict to width limit for this ratio
                        $cy = min($minW * $ratio['h'] / $ratio['w'], $minH);
                        $y = ($imagePoi['y'] - ($cy / 2));
                        $cx = $cy * $ratio['w'] / $ratio['h'];
                        $x = $imagePoi['x'] - ($cx / 2);
                    }
                } else {
                    // minimum cropping is not possible due to proximity of poi coordinates and image edges
                    return $this->centerCrop($imageW, $imageH, $ratio);
                }
            } else {
                return $this->centerCrop($imageW, $imageH, $ratio);
            }
            return ['x' => $x, 'y' => $y, 'cx' => $cx, 'cy' => $cy];
        }
    }


    /**
     * @param $src_w
     * @param $src_h
     * @param $ratio
     * @return array
     */
    private function centerCrop($src_w, $src_h, $ratio)
    {
        if (($src_w / $src_h) > ($ratio['w'] / $ratio['h'])) {
            // ratio at original width makes a vertical crop
            // store height
            // calculation of the new width (nw = src_h * ratio[w] / ratio[h] )
            $cx = $src_h * $ratio['w'] / $ratio['h'];
            $x = ($src_w - $cx) / 2;
            $y = 0;
            $cy = $src_h;
        } else {
            // ratio at original width makes a horizontal crop
            // store width
            // calculation of the new height (nh = src_w * ratio[w] / ratio[h] )
            $cy = $src_w * $ratio['h'] / $ratio['w'];
            $y = ($src_h - $cy) / 2;
            $cx = $src_w;
            $x = 0;
        }
        return ['x' => $x, 'y' => $y, 'cx' => $cx, 'cy' => $cy];
    }


    /*
     * Load Image from gif/png/jpg/bmp.
     * GD doesn't provide a unique method
     */
    function imageCreateFromAny($filePath)
    {
        $im = null;
        $type = exif_imagetype($filePath); // [] if you don't have exif you could use getImageSize()
        $allowedTypes = array(
            1,// [] gif
            2,// [] jpg
            3,// [] png
            6 // [] bmp
        );
//        $app['monolog']->debug(serialize($type));
        if (!in_array($type, $allowedTypes)) {
            return false;
        }
        switch ($type) {
            case 1 :
                $im = imageCreateFromGif($filePath);
                break;
            case 2 :
                $im = imageCreateFromJpeg($filePath);
                break;
            case 3 :
                $im = imageCreateFromPng($filePath);
                break;
            case 6 :
                $im = imagecreatefrombmp($filePath);
                break;
        }
        return $im;
    }


}
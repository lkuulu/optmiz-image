<?php

namespace STHImage;

use Silex\Application;
use Silex\Api\ControllerProviderInterface;

class ImageController implements ControllerProviderInterface
{


    public function connect(Application $app)
    {
        // creates a new controller based on the default route
        $controllers = $app['controllers_factory'];
        $controllers->get('/{pathToResource}', 'STHImage\ImageController::getImageByWidthPresetDensity')->assert('pathToResource', '(?P<path>[^*]*)\/w:(?P<width>[0-9]*)\/p:(?P<preset>[^\/]+)\/d:(?P<screenDensity>[^\/]+)\/?$');
        $controllers->get('/{pathToResource}', 'STHImage\ImageController::getImageByWidthDensity')->assert('pathToResource', '(?P<path>[^*]*)\/w:(?P<width>[0-9]*)\/d:(?P<screenDensity>[^\/]+)\/?$');
        $controllers->get('/{pathToResource}', 'STHImage\ImageController::getImageByPresetDensity')->assert('pathToResource', '(?P<path>[^*]*)\/p:(?P<preset>[^\/]+)\/d:(?P<screenDensity>[^\/]+)\/?$');
        $controllers->get('/{pathToResource}', 'STHImage\ImageController::getImageByPathDensity')->assert('pathToResource', '(?P<path>[^*]*)\/d:(?P<screenDensity>[^\/]+)\/?$');
        $controllers->get('/{pathToResource}', 'STHImage\ImageController::getImageByWidthPreset')->assert('pathToResource', '(?P<path>[^*]*)\/w:(?P<width>[0-9]*)\/p:(?P<preset>[^\/]+)\/?$');
        $controllers->get('/{pathToResource}', 'STHImage\ImageController::getImageByWidth')->assert('pathToResource', '(?P<path>[^*]*)\/w:(?P<width>[0-9]*)\/?$');
        $controllers->get('/{pathToResource}', 'STHImage\ImageController::getImageByPreset')->assert('pathToResource', '(?P<path>[^*]*)\/\p:(?P<preset>[^\/]+)\/?$');
        $controllers->get('/{pathToResource}', 'STHImage\ImageController::getImageByPath')->assert('pathToResource', '(?P<path>[^*]*)\/?$');
        return $controllers;
    }


    public function getImageByWidthPresetDensity($path, $width, $preset, $screenDensity, Application $app)
    {
        return $app['image_renderer']->getImage($path, $width, $preset, $screenDensity, $app);
    }


    public function getImageByWidthDensity($path, $width, $screenDensity, Application $app)
    {
        return $app['image_renderer']->getImage($path, $width, 'original', $screenDensity, $app);
    }

    public function getImageByPresetDensity($path, $preset, $screenDensity, Application $app)
    {
        return $app['image_renderer']->getImage($path, 'same', $preset, $screenDensity, $app);
    }

    public function getImageByPathDensity($path, $screenDensity, Application $app)
    {
        return $app['image_renderer']->getImage($path, 'same', 'original', $screenDensity, $app);
    }

    public function getImageByWidthPreset($path, $width, $preset, Application $app)
    {
        return $app['image_renderer']->getImage($path, $width, $preset, 1, $app);
    }


    public function getImageByWidth($path, $width, Application $app)
    {
        return $app['image_renderer']->getImage($path, $width, 'original', 1, $app);
    }

    public function getImageByPreset($path, $preset, Application $app)
    {
        return $app['image_renderer']->getImage($path, 'same', $preset, 1, $app);
    }

    public function getImageByPath($path, Application $app)
    {
        return $app['image_renderer']->getImage($path, 'same', 'original', 1, $app);
    }


}
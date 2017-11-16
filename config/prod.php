<?php

use WyriHaximus\SliFly\FlysystemServiceProvider;
use STHImage\ImageRenderer;

// configure your app for the production environment

$app['twig.path'] = array(__DIR__ . '/../templates');
$app['twig.options'] = array('cache' => __DIR__ . '/../var/cache/twig');
//phpinfo();

$app['image_renderer'] = function () {
    return new ImageRenderer();
};

$app->register(new WyriHaximus\SliFly\FlysystemServiceProvider(), [
    'flysystem.filesystems' => [
        'local__DIR__' => [
            'adapter' => 'League\Flysystem\Adapter\Local',
            'args' => [
                '/var/www/optmiz/image/repo1/files', //__DIR__,
            ],
            'config' => [
                // Config array passed in as second argument for the Filesystem instance
            ],
        ],
    ],
]);

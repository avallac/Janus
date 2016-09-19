<?php

$app = new Silex\Application();
$app['debug'] = 1;
$app->register(new \Janus\Silex\Provider\SystemConfigProvider(__DIR__.'/../etc/config.yml'));
$app->register(new \Janus\Silex\Provider\DataBaseServiceProvider());
$app->get('/{service}', function ($service) use ($app) {
    $chooser = new \Janus\Model\Chooser($app);
    return json_encode($chooser->getProxy($service));
});

return $app;

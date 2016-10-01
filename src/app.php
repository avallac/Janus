<?php

$app = new Silex\Application();
$app['debug'] = 1;
$app->register(new \Janus\Silex\Provider\SystemConfigProvider(__DIR__.'/../etc/config.yml'));
$app->register(new \Janus\Silex\Provider\DataBaseServiceProvider());
$app->register(new \Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/view/',
    'twig.options' => ['cache' => __DIR__.'/cache/']
));

$app->get('/{service}', function ($service) use ($app) {
    $chooser = new \Janus\Model\Chooser($app);
    return json_encode($chooser->getProxy($service));
});

$app->post('/{service}', function (\Symfony\Component\HttpFoundation\Request $r, $service) use ($app) {
    $chooser = new \Janus\Model\Chooser($app);
    $chooser->markBad($service, $r->get('auth'));
    return $r->get('auth');
});

$app->match('/', function (\Symfony\Component\HttpFoundation\Request $r) use ($app) {
    $used = [];
    if($r->get('proxyList')) {
        $lines = explode("\n", $r->get('proxyList'));
        $app['db']->beginTransaction();
        try {
            $app['db']->executeQuery('LOCK TABLE status IN ACCESS EXCLUSIVE MODE');
            $app['db']->executeQuery('LOCK TABLE proxy IN ACCESS EXCLUSIVE MODE');
            foreach ($lines as $proxy) {
                if (preg_match('/(\d+\.\d+\.\d+\.\d+)\s*:(\d+)[|@](\S+):(\S+)/', $proxy, $m)) {
                    if (!isset($used[$m[1].':'.$m[2]])) {
                        $app['db']->insert('proxy', [
                            'hostname' => $m[1],
                            'port' => $m[2],
                            'username' => $m[3],
                            'password' => $m[4],
                        ]);
                        $used[$m[1].':'.$m[2]] = 1;
                    }
                } else {
                    var_dump($proxy, $m);exit;
                }
            }
            $app['db']->commit();
        } catch (\Exception $e) {
            $app['db']->rollBack();
            throw $e;
        }
    }
    $sql = 'SELECT id, extract(epoch FROM now() - lastused), hostname, port FROM proxy LEFT JOIN status ON id = proxy_id  order by date_part desc';
    $status = $app['db']->fetchAll($sql);
    return $app['twig']->render('index.twig', ['status' => $status]);
});

return $app;

<?php

namespace Janus\Silex\Provider;

use Doctrine\DBAL\Connection;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Provider\DoctrineServiceProvider;

class DataBaseServiceProvider extends BaseServiceProvider implements ServiceProviderInterface
{
    public function register(Container $app)
    {
        $config = [
            'driver' => 'pdo_pgsql',
            'dbhost' => $app['system.config']['db']['dbhost'],
            'dbname' => $app['system.config']['db']['dbname'],
            'user' => $app['system.config']['db']['user'],
            'password' => $app['system.config']['db']['password']
        ];
        if (isset($app['system.config']['db']['port'])) {
            $config ['port'] = $app['system.config']['db']['port'];
        }
        $app->register(new DoctrineServiceProvider(), ['db.options' => $config]);
        $app['db']->setTransactionIsolation(Connection::TRANSACTION_SERIALIZABLE);
    }
}
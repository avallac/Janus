<?php

namespace Janus\Silex\Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\Yaml\Yaml;

class SystemConfigProvider implements ServiceProviderInterface
{
    protected $file;
    public function __construct($file)
    {
        if (file_exists($file)) {
            $this->file = $file;
        } else {
            if (file_exists($file . '.default')) {
                $this->file = $file . '.default';
            } else {
                throw new \Exception("Can't read config file: " . $file);
            }
        }
    }

    public function register(Container $app)
    {
        $filename = $this->file;
        $app['system.config'] = function () use ($filename) {
            return Yaml::parse(file_get_contents($filename));
        };
    }
}
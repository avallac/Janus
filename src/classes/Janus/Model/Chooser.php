<?php

namespace Janus\Model;

class Chooser
{
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function markBad($service, $auth)
    {
        $this->app['db']->beginTransaction();
        try {
            $this->app['db']->executeQuery('LOCK TABLE status IN ACCESS EXCLUSIVE MODE');
            preg_match('/(\d+\.\d+\.\d+\.\d+)\:(\d+)/', $auth, $m);
            $sql = 'SELECT id FROM proxy WHERE hostname = ? AND port = ?';
            $status = $this->app['db']->fetchAll($sql, [$m[1], $m[2]]);
            $sql = "UPDATE status SET lastused = now() + INTERVAL '1 hour' WHERE proxy_id = ? AND service = ?";
            $this->app['db']->executeQuery($sql, [$status[0]['id'], $service]);
            $this->app['db']->commit();
        } catch (\Exception $e) {
            $this->app['db']->rollBack();
            throw $e;
        }
    }

    public function updateStatistic($service, $auth, $time)
    {
        $this->app['db']->beginTransaction();
        try {
            $this->app['db']->executeQuery('LOCK TABLE status IN ACCESS EXCLUSIVE MODE');
            preg_match('/(\d+\.\d+\.\d+\.\d+)\:(\d+)/', $auth, $m);
            $sql = 'SELECT id FROM proxy WHERE hostname = ? AND port = ?';
            $status = $this->app['db']->fetchAll($sql, [$m[1], $m[2]]);
            $sql = "UPDATE status SET statistic = (statistic*0.999 + ".$time."*0.001) WHERE proxy_id = ? AND service = ?";
            $this->app['db']->executeQuery($sql, [$status[0]['id'], $service]);
            $this->app['db']->commit();
        } catch (\Exception $e) {
            $this->app['db']->rollBack();
            throw $e;
        }
    }

    public function getProxy($service)
    {
        $return = null;
        $this->app['db']->beginTransaction();
        $this->app['db']->executeQuery('LOCK TABLE status IN ACCESS EXCLUSIVE MODE');
        try {
            $wait = $this->app['db']->fetchColumn('SELECT wait FROM service WHERE name = ?', [$service]);
            $minWait = $wait;
            if ($wait === false) {
                throw new \Exception("Can't find service.");
            }
            $sql = 'SELECT 
                        id, extract(epoch FROM now() - lastused), username, password, hostname, port
                    FROM proxy 
                    LEFT JOIN status ON id = proxy_id AND service = ? order by statistic';
            $status = $this->app['db']->fetchAll($sql, [$service]);
            foreach ($status as $item) {
                if (isset($item['date_part'])) {
                    $minWait = min($minWait, $wait - $item['date_part']);
                    if ($item['date_part'] > $wait) {
                        $this->app['db']->update('status', ['lastused' => 'now()',], [
                            'proxy_id' => $item['id'],
                            'service' => $service
                        ]);
                        $return = $item;
                        break;
                    }
                } else {
                    $minWait = -1;
                    $this->app['db']->insert('status', [
                        'proxy_id' => $item['id'],
                        'lastused' => 'now()',
                        'service' => $service
                    ]);
                    $return = $item;
                    break;
                }
            }
            $this->app['db']->commit();
        } catch (\Exception $e) {
            $this->app['db']->rollBack();
            throw $e;
        }
        return [$return, $minWait];
    }
}

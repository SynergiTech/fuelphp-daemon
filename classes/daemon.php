<?php

namespace Daemon;

class Daemon
{
    private $config;
    private $log;
    /**
     * @var Worker
     */
    private $worker;

    public function __construct($config = [])
    {
        \Config::load('daemon');

        $this->config['name'] = isset($config['name']) ? $config['name'] : \Config::get('daemon.name', \Config::get('application.name', 'Application'));
        $this->config['respawn'] = isset($config['respawn']) ? $config['respawn'] : \Config::get('daemon.respawn', true);
        $this->config['slowSpawn'] = isset($config['slowSpawn']) ? $config['slowSpawn'] : \Config::get('daemon.slowSpawn', 5);
        $this->config['supervisorPidFile'] = isset($config['supervisorPidFile']) ? $config['supervisorPidFile'] : \Config::get('daemon.supervisorPidFile', APPPATH.'/tmp/'.$this->config['name'].'.pid');
        $this->config['workerPidPath'] = isset($config['workerPidPath']) ? $config['workerPidPath'] : \Config::get('daemon.workerPidPath', APPPATH.'/tmp/');
        $this->config['ttlHeartbeat'] = isset($config['ttlHeartbeat']) ? $config['ttlHeartbeat'] : \Config::get('daemon.ttlHeartbeat', 30);

        $this->config['name'] = $this->config['name'] . '-' . uniqid());

        $this->worker = new Worker($this, $this->config['name'], $config['callback'], $config);
        $this->log = new Log();
    }

    public function start()
    {
        $this->worker->start();
    }

    public function log()
    {
        $args = func_get_args();
        if (count($args) == 0) {
            return $this->log;
        }

        if ($this->log == null) {
            echo implode(" ", $args);
        } else {
            call_user_func_array($this->log, $args);
        }
    }

    public function getConfig($opt = null, $default = null)
    {
        if ($opt !== null) {
            if (isset($this->config[$opt])) {
                return $this->config[$opt];
            } else {
                return $default;
            }
        } else {
            return $this->config;
        }
    }
}

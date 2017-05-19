<?php

namespace Daemon;

class Daemon
{
    private $daemonize;
    private $respawn;
    private $workers;
    private $_log;
    private $supervisor;

    public function __construct($config = [])
    {
        \Config::load('daemon');

        $this->name = isset($config['name']) ? $config['name'] : \Config::get('daemon.name', \Config::get('application.name', 'Application'));
        $this->daemonize = isset($config['daemonize']) ? $config['daemonize'] : \Config::get('daemon.daemonize', true);
        $this->respawn = isset($config['respawn']) ? $config['respawn'] : \Config::get('daemon.respawn', true);
        $this->workers = isset($config['workers']) ? $config['workers'] : \Config::get('daemon.workers', true);
        $this->slowSpawn = isset($config['slowSpawn']) ? $config['slowSpawn'] : \Config::get('daemon.slowSpawn', 5);
        $this->pidFile = isset($config['pidFile']) ? $config['pidFile'] : \Config::get('daemon.pidFile', APPPATH.'/tmp/'.$this->name.'.pid');

        $this->_log = new Log();

        if (!function_exists('pcntl_fork') and $this->daemonize) {
            $this->log("warn", "Config set to daemonize but pcntl_fork() is not available!");
            $this->daemonize = false;
        }
    }

    public function start()
    {
        $this->log("Starting supervisor for ".$this->name);
        $this->startSupervisor();
    }

    private function startSupervisor()
    {
        $this->supervisor = new Supervisor($this);
        $this->supervisor->start();
    }

    public function stop()
    {
        if (!$this->isRunning()) {
            $this->log("warn", "Could not stop daemon: not running");
            return false;
        }

        $this->log("Stopping daemon...");
        if ($this->killSupervisor()) {
            $this->log("Successfully stopped daemon");
            return true;
        } else {
            $this->log("warn", "Could not stop daemon: stop unsuccessful");
            return false;
        }
    }

    public function restart()
    {
        $this->stop();
        $this->start();
    }

    public function log()
    {
        $args = func_get_args();
        if (count($args) == 0) {
            return $this->_log;
        }

        if ($this->_log == null) {
            echo implode(" ", $args);
        } else {
            call_user_func_array($this->_log, $args);
        }
    }

    public function isRunning()
    {
        return true;
    }

    public function killSupervisor()
    {
    }

    public function daemonize()
    {
        return $this->daemonize;
    }
}

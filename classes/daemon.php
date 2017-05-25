<?php

namespace Daemon;

class DaemonException extends \RuntimeException
{
}

class Daemon
{
    private $config;
    private $workers = [];
    private $_log;
    private $supervisor;
    private $isVirtual = true;

    public function __construct($config = [])
    {
        \Config::load('daemon');

        $this->config['name'] = isset($config['name']) ? $config['name'] : \Config::get('daemon.name', \Config::get('application.name', 'Application'));
        $this->config['daemonize'] = isset($config['daemonize']) ? $config['daemonize'] : \Config::get('daemon.daemonize', true);
        $this->config['respawn'] = isset($config['respawn']) ? $config['respawn'] : \Config::get('daemon.respawn', true);
        $this->config['slowSpawn'] = isset($config['slowSpawn']) ? $config['slowSpawn'] : \Config::get('daemon.slowSpawn', 5);
        $this->config['supervisorPidFile'] = isset($config['supervisorPidFile']) ? $config['supervisorPidFile'] : \Config::get('daemon.supervisorPidFile', APPPATH.'/tmp/'.$this->config['name'].'.pid');
        $this->config['workerPidPath'] = isset($config['workerPidPath']) ? $config['workerPidPath'] : \Config::get('daemon.workerPidPath', APPPATH.'/tmp/');
        $this->config['ttlHeartbeat'] = isset($config['ttlHeartbeat']) ? $config['ttlHeartbeat'] : \Config::get('daemon.ttlHeartbeat', 30);

        $this->_log = new Log();

        if (!function_exists('pcntl_fork') and $this->config['daemonize']) {
            $this->log("warn", "Config set to daemonize but pcntl_fork() is not available!");
            $this->config['daemonize'] = false;
        }
        $this->supervisor = new Supervisor($this);
    }

    public function addWorker($name, $callback, $opts = [])
    {
        if (!isset($opts['concurrent'])) {
            $opts['concurrent'] = 1;
        }
        for ($i=0; $i<$opts['concurrent']; $i++) {
            $iname = $name.'-'.$i;

            $worker = new Worker($this, $iname, $callback, $opts);
            $this->workers[$iname] = $worker;
        }

        return $this->workers;
    }

    public function getWorkers()
    {
        return $this->workers;
    }

    public function getWorker($name)
    {
        if (isset($this->workers[$name])) {
            return $this->workers[$name];
        }

        return null;
    }

    public function getChild()
    {
        if ($this->isVirtual) {
            return $this;
        } elseif (!$this->getSupervisor()->isVirtual()) {
            return $this->getSupervisor();
        } else {
            $workers = $this->getWorkers();
            foreach ($workers as $worker) {
                if (!$worker->isVirtual()) {
                    return $worker;
                }
            }
        }
    }

    public function start()
    {
        if ($this->isRunning()) {
            throw new DaemonException("Daemon cannot be started, already running", 3);
        }
        $this->isVirtual = false;
        $this->startSupervisor($this->getWorkers());
    }

    private function startSupervisor()
    {
        $this->supervisor->start();
    }

    public function stop()
    {
        if (!$this->isRunning()) {
            $this->log("warn", "Could not stop: daemon not running");
            return false;
        }

        $this->log("Stopping...");
        if ($this->supervisor->stop()) {
            $this->log("Successfully stopped");
            return true;
        } else {
            $this->log("warn", "Could not stop: stop unsuccessful");
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
        return $this->supervisor->isRunning();
    }

    public function daemonize()
    {
        return $this->config['daemonize'];
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

    public function getStatus()
    {
        $results = [];

        $results["supervisor"] = ['running' => $this->supervisor->isRunning(), 'pid' => $this->supervisor->getPid()];
        foreach ($this->workers as $worker) {
            $results[$worker->getName()] = ['running' => $worker->isRunning(), 'pid' => $worker->getPid()];
        }

        return $results;
    }

    public function printStatus()
    {
        $status = $this->getStatus();
        foreach ($status as $name => $info) {
            $colors = $this->_log->getColors();

            $string = $name.'... '."\t"."\t";
            if ($info['running']) {
                $status = $colors->apply('green', 'running');
                $string .= $status;
                $string .= " (".$info['pid'].")";
            } else {
                $status = $colors->apply('red', 'stopped');
                $string .= $status;
            }

            echo $string.PHP_EOL;
        }
    }

    public function getSupervisor()
    {
        return $this->supervisor;
    }
}

<?php

namespace Daemon;

class Worker
{
    private $virtual = true;
    private $pidFile = false;
    private $name;
    private $running = false;

    public function __construct($daemon, $name, $callback)
    {
        $this->daemon = $daemon;
        $this->name = $name;
        $this->callback = $callback;

        $pidfile = $this->daemon->getConfig('workerPidPath').DS.$name.'.pid';
        $this->pidFile = $pidfile;
    }

    public function start()
    {
        $this->log('Starting worker');

        if ($this->daemon->daemonize()) {
            $this->log('Daemonizing worker ...');
            $fork = pcntl_fork();

            if ($fork === -1) {
                throw new DaemonException("Could not successfully background the worker");
            } elseif ($fork !== 0) {
                return false;
            } else {
                $this->daemon->getSupervisor()->setVirtual(true);
                $this->log()->setName($this->name);
                $this->log("success", "Successfully backgrounded the worker");
            }
        }

        $this->virtual = false;
        $this->running = true;
        $this->writePID();

        while ($this->running) {
            // check signals
            $this->daemon->signal();
            if (!$this->daemon->isRunning()) {
                $this->log("error", "My supervisor has died!");
                $this->stop();
            }
            // check again after signal() call
            if ($this->running === false) {
                break;
            }

            $this->running = call_user_func($this->callback) !== false;
        }

        $this->deletePID();
        exit;
    }

    public function stop()
    {
        $this->running = false;

        if ($this->virtual) {
            $pid = $this->getPid();
            if ($pid !== false) {
                posix_kill($pid, SIGTERM);
            } else {
                return false;
            }
        }
    }

    public function log()
    {
        return call_user_func_array(array($this->daemon, "log"), func_get_args());
    }

    public function writePID()
    {
        file_put_contents($this->pidFile, posix_getpid());
    }

    public function deletePID()
    {
        unlink($this->pidFile);
    }

    public function getPidFile()
    {
        return file_exists($this->pidFile) ? $this->pidFile : false;
    }

    public function getPid()
    {
        if ($this->getPidFile() == false) {
            return false;
        }

        $pid = file_get_contents($this->getPidFile());
        return $pid;
    }

    public function isRunning()
    {
        if (!$this->virtual) {
            return $this->running;
        }

        $pid = $this->getPid();
        if ($pid === false) {
            return false;
        }

        $code = posix_getpgid($pid);
        return $code !== false;
    }

    public function getName()
    {
        return $this->name;
    }
}

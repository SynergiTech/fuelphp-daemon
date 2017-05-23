<?php

namespace Daemon;

class Supervisor
{
    private $workers = [];
    private $virtual = true;
    private $pidFile = false;
    private $running = false;

    public function __construct($daemon)
    {
        $this->daemon = $daemon;
        $this->log()->setName("supervisor");

        $pidfile = $this->daemon->getConfig('supervisorPidFile');
        $this->pidFile = $pidfile;
    }

    public function start($workers = [])
    {
        $this->log('Starting supervisor ...');
        if ($this->daemon->daemonize()) {
            $this->log('Daemonizing supervisor ...');
            $fork = pcntl_fork();

            if ($fork === -1) {
                throw new DaemonException("Could not successfully background the supervisor");
            } elseif ($fork !== 0) {
                return true;
            } else {
                $this->log("success", "Successfully backgrounded the supervisor");
            }
        }

        $this->virtual = false;
        $this->running = true;
        $this->writePID();

        $this->log("success", "Successfully started the supervisor");

        $this->workers = $workers;
        while ($this->running) {
            // check signals
            $this->daemon->signal();

            $this->checkWorkers();

            sleep(1);
        }

        $this->deletePID();
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
        } else {
            foreach ($this->workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }

            foreach ($this->workers as $worker) {
                while ($worker->isRunning()) {
                    sleep(1);
                }
            }
        }

        return true;
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
        return $this->pidFile;
    }

    public function getPid()
    {
        if ($this->getPidFile() === false) {
            return false;
        }

        return file_get_contents($this->getPidFile());
    }

    public function setVirtual($virtual = true)
    {
        $this->virtual = $virtual;
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

    private function checkWorkers()
    {
        $workers = $this->workers;
        foreach ($workers as $name => $worker) {
            if ($worker->isRunning() === false) {
                unset($this->workers[$name]);
            }
        }

        $parentWorkers = $this->daemon->getWorkers();
        foreach ($parentWorkers as $name => $worker) {
            if (!isset($this->workers[$name])) {
                $this->workers[$name] = $worker;
                $worker->start();

                sleep($this->daemon->getConfig('slowSpawn'));
                break;
            }
        }
    }
}

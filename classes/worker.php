<?php

namespace Daemon;

class Worker
{
    private $virtual = true;
    private $pidFile = false;
    private $name;
    private $running = false;
    private $daemon;
    private $worker = null;

    public function __construct($daemon, $name, $callback, $opts)
    {
        $this->daemon = $daemon;
        $this->name = $name;
        $this->callback = $callback;
        $this->opts = $opts;

        $pidfile = $this->daemon->getConfig('workerPidPath').DS.$name.'.pid';
        $this->pidFile = $pidfile;
    }

    public function start()
    {
        $this->log('Starting worker');

        if (function_exists('pcntl_fork')) {
            $this->log('Daemonizing worker ...');
            \Database_Connection::instance()->disconnect();
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
        $this->createWorker();
        $this->registerSignalHandlers();

        $this->startCallback();

        while ($this->running) {
            // check signals
            $this->signal();
            if (!$this->daemon->isRunning()) {
                $this->log("error", "My supervisor has died!");
                $this->stop();
            }
            // check again after signal() call
            $this->heartbeat();
            if ($this->running === false) {
                break;
            }

            $this->running = call_user_func($this->callback, $this) !== false;
        }

        $this->stopCallback();

        $this->deletePID();
        $this->deleteWorker();
        $this->log($this->getName()." exited");
        exit;
    }

    private function startCallback()
    {
        if (isset($this->opts['startCallback']) and is_callable($this->opts['startCallback'])) {
            return call_user_func($this->opts['startCallback'], $this);
        }
        return false;
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

    private function stopCallback()
    {
        if (isset($this->opts['stopCallback']) and is_callable($this->opts['stopCallback'])) {
            return call_user_func($this->opts['stopCallback'], $this);
        }
        return false;
    }

    public function signal($signal = null)
    {
        if ($signal === null) {
            pcntl_signal_dispatch();
        } elseif ($signal === SIGTERM or $signal === SIGINT) {
            $this->log("SIGTERM received");
            $this->stop();
        }
    }

    public function registerSignalHandlers()
    {
        $signals = [SIGTERM, SIGINT];
        foreach ($signals as $signal) {
            pcntl_signal($signal, array($this, 'signal'));
        }
        $this->log("debug", "Installed signal handlers");
    }

    public function log()
    {
        return call_user_func_array(array($this->daemon, "log"), func_get_args());
    }

    public function createWorker()
    {
        $this->worker = new Model\Daemon\Worker();
        $this->worker->name = $this->getName();
        $this->worker->type = 'worker';
        $this->worker->status = 'started';
        $this->worker->save();
    }

    public function deleteWorker()
    {
        if ($this->worker !== null) {
            $this->worker->delete();
        }
    }

    public function heartbeat()
    {
        if (time()-$this->worker->last_heartbeat < $this->daemon->getConfig('ttlHeartbeat')) {
            return true;
        }

        $this->worker = Model\Daemon\Worker::query()
            ->where('id', $this->worker->id)
            ->from_cache(false)
            ->get_one();

        if (in_array($this->worker->status, ['terminating', 'terminated'])) {
            $this->stop();
        }
        $this->worker->last_heartbeat = time();
        $this->worker->status = 'running';
        $this->worker->save();
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

    public function isVirtual()
    {
        return $this->virtual;
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

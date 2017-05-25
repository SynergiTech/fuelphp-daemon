<?php

namespace Daemon;

class Supervisor
{
    private $workers = [];
    private $virtual = true;
    private $pidFile = false;
    private $running = false;
    private $supervisor = null;

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
            \Database_Connection::instance()->disconnect();
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
        $this->registerSignalHandlers();
        $this->createSupervisor();

        $this->log("success", "Successfully started the supervisor");

        $this->workers = $workers;
        while ($this->running) {
            // check signals
            $this->heartbeat();
            $this->signal();
            if (!$this->running) {
                break;
            }

            $this->checkWorkers();
            sleep(1);
        }

        $this->deletePID();
        $this->deleteSupervisor();
        $this->log("Supervisor exited");
        exit;
    }

    public function createSupervisor()
    {
        $this->supervisor = new Model\Daemon\Worker();
        $this->supervisor->name = $this->daemon->getConfig('name');
        $this->supervisor->type = 'supervisor';
        $this->supervisor->status = 'started';
        $this->supervisor->save();
    }

    public function deleteSupervisor()
    {
        if ($this->supervisor !== null) {
            $this->supervisor->delete();
        }
    }

    public function heartbeat()
    {
        if (time()-$this->supervisor->last_heartbeat < $this->daemon->getConfig('ttlHeartbeat')) {
            return true;
        }

        $this->supervisor = Model\Daemon\Worker::query()
            ->where('id', $this->supervisor->id)
            ->from_cache(false)
            ->get_one();

        if (in_array($this->supervisor->status, ['terminating', 'terminated'])) {
            $this->stop();
            return;
        }
        $this->supervisor->last_heartbeat = time();
        $this->supervisor->status = 'running';
        $this->supervisor->save();
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


    public function stop()
    {
        $this->running = false;

        // if it's virtual, send a kill signal to the real one
        // and kill any workers that may not be virtual and belong to us
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
        if ($this->getPidFile() === false or !file_exists($this->getPidFile())) {
            return false;
        }

        return file_get_contents($this->getPidFile());
    }

    public function setVirtual($virtual = true)
    {
        $this->log("Set virtual to: ".($virtual == true ? 'true' : 'false'));
        $this->virtual = $virtual;
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

    public function getName()
    {
        return 'supervisor';
    }
}

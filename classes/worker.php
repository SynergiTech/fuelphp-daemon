<?php

namespace Daemon;

class Worker
{
    private $name;
    private $running = false;
    private $daemon;
    private $worker = null;
    private $opts = [];
    private $startTime = null;

    public function __construct($daemon, $name, $callback, $opts)
    {
        $this->daemon = $daemon;
        $this->name = $name;
        $this->callback = $callback;
        $this->opts = $opts;
    }

    public function start()
    {
        $this->log('Starting worker');
        $this->startTime = time();
        $this->running = true;
        $this->registerSignalHandlers();

        $this->startCallback();

        while ($this->running) {
            // check signals
            $this->signal();
            if ($this->running === false) {
                break;
            }

            $this->running = call_user_func($this->callback, $this) !== false;
            if (isset($this->opts['clock']) and is_callable($this->opts['clock'])) {
                call_user_func($this->opts['clock']);
            }
            if (isset($this->opts['maxTime']) and time()-$this->startTime >= $this->opts['maxTime']) {
                $this->log("Maximum uptime reached... restarting");
                break;
            }
        }

        $this->stopCallback();

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

    private function stopCallback()
    {
        if (isset($this->opts['stopCallback']) and is_callable($this->opts['stopCallback'])) {
            return call_user_func($this->opts['stopCallback'], $this);
        }
        return false;
    }

    public function stop()
    {
        $this->running = false;
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

    public function getName()
    {
        return $this->name;
    }
}

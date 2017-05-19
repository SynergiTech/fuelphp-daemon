<?php

namespace Daemon;

class Supervisor
{
    private $workers = [];

    public function __construct($daemon)
    {
        $this->daemon = $daemon;
        $this->log()->setName("supervisor");
    }

    public function start()
    {
        if ($this->daemon->daemonize()) {
            $this->log('Daemonizing supervisor...');
            $fork = pcntl_fork();

            if ($fork === -1) {
                throw new DaemonException("Could not successfully background the supervisor");
            } elseif ($fork !== 0) {
                return true;
            } else {
                $this->log("success", "Successfully backgrounded the supervisor");
            }
        }
    }

    public function log()
    {
        return call_user_func_array(array($this->daemon, "log"), func_get_args());
    }
}

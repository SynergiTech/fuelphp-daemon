<?php

namespace Daemon;

class Log
{
    private $colors;
    private $styles = [
        'error' => ['red'],
        'warn' => ['yellow'],
        'success' => ['green'],
    ];
    private $name = 'app';
    private $transports = [
        'fuel', 'console'
    ];

    public function __construct()
    {
        $this->colors = new \JakubOnderka\PhpConsoleColor\ConsoleColor();
    }

    public function __invoke()
    {
        $level = 'info';

        $args = func_get_args();
        if (count($args) == 2) {
            $level = array_shift($args);
        }

        $msg = array_shift($args);

        $this->write($level, $msg);
    }

    public function write($level, $msg)
    {
        $string = "[".date("H:i:s")."] [".$level."] [".$this->name."] "."\t".$msg.PHP_EOL;

        if (isset($this->styles[$level])) {
            foreach ($this->styles[$level] as $style) {
                $string = $this->colors->apply($style, $string);
            }
        }

        foreach ($this->transports as $transport) {
            if ($transport == 'console') {
                echo $string;
            } elseif ($transport == 'fuel') {
                \Log::info($string);
            }
        }
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getColors()
    {
        return $this->colors;
    }
}

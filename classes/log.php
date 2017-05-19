<?php

namespace Daemon;

class Log
{
    private $colors;
    private $styles = [
        'warn' => ['red'],
        'success' => ['green'],
    ];
    private $name = 'boot';

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

        echo $string;
    }

    public function setName($name)
    {
        $this->name = $name;
    }
}

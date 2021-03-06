<?php
/**
 * Fuel
 *
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.8
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2016 Fuel Development Team
 * @link       http://fuelphp.com
 */

\Autoloader::add_classes(array(
    'Daemon\\Daemon'                        => __DIR__.'/classes/daemon.php',
    'Daemon\\Log'                        => __DIR__.'/classes/log.php',
    'Daemon\\Supervisor'                        => __DIR__.'/classes/supervisor.php',
    'Daemon\\Worker'                        => __DIR__.'/classes/worker.php',

    'Daemon\\Model\\Daemon\\Worker'            => __DIR__.'/classes/model/daemon/worker.php',
));

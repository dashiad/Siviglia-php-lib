<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 28/06/2017
 * Time: 9:52
 */

namespace lib\storage\Base\Connectors;


abstract class Connector extends \lib\php\ArrayMappedParameters
{
    abstract function disconnect();
    abstract function connect();
    abstract function isConnected();
}
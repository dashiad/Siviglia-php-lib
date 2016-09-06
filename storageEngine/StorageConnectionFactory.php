<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 15/09/15
 * Time: 9:48
 */

namespace lib\storageEngine;

use lib\model\BaseException;

class StorageConnectionFactoryException extends BaseException
{
    const ERR_UNKNONW_CONNECTION = 1;
}

class StorageConnectionFactory
{
    static $definitions = array();
    static $connectionCache = array();

    static function getConnectionByName($name, $createNew = false)
    {
        if (!isset(StorageConnectionFactory::$definitions[$name])) {
            if (isset(StorageConnectionFactory::$connectionCache[$name]))
                return StorageConnectionFactory::$connectionCache[$name];

            throw new StorageConnectionFactoryException(StorageConnectionFactoryException::ERR_UNKNONW_CONNECTION, array("name" => $name));
        }
        $cached = false;
        if (isset(StorageConnectionFactory::$connectionCache[$name]))
            $cached = StorageConnectionFactory::$connectionCache[$name];

        if ($createNew == false && $cached !== false)
            return $cached;
        $def = StorageConnectionFactory::$definitions[$name];
        $type = $def["type"];
        include_once("Connection/".$type.".php");
        $connClass = "\\lib\\storageEngine\\Connection\\" . $type;
        $connParamsClass = $connClass . "Params";
        $connParams = new $connParamsClass($def["params"]);
        $conn = new $connClass($connParams);
        if ($cached === false)
            StorageConnectionFactory::$connectionCache[$name] = $conn;
        return $conn;
    }

    // Si el segundo parametro es un objeto, se supone que es la instancia ya creada de la conexion.
    static function addConnection($name, $type, $definition = null)
    {
        if (is_object($type)) {
            StorageConnectionFactory::$connectionCache[$name] = $type;
            return;
        }
        StorageConnectionFactory::$definitions[$name] = array("type" => $type, "params" => $definition);
    }
} 
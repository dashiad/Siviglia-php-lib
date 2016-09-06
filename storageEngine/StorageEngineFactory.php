<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 15/09/15
 * Time: 9:49
 */

namespace lib\storageEngine;

use lib\model\BaseException;

class StorageEngineFactoryException extends BaseException
{
    const ERR_UNKNOWN_ENGINE = 1;
    const ERR_UNKNOWN_NAMED_ENGINE = 2;
}

class StorageEngineFactory
{
    static $engineCache = array();
    static $addedEngines = array();

    static function getEngineByType($name, $params = null)
    {
        if (is_array($name)) {
            $params = $name["params"];
            $name = $name["name"];
        }
        if (isset(StorageEngineFactory::$addedEngines[$name]))
            $engineClass = StorageEngineFactory::$addedEngines[$name];
        else {
            $prefix = __NAMESPACE__ . "\\" . ucfirst($name) . "Storage";
            $engineClass = $prefix . "Engine";
            $paramClass = $prefix . "Params";
        }
        if (!class_exists($engineClass)) {
            throw new StorageEngineFactoryException(StorageEngineFactoryException::ERR_UNKNOWN_ENGINE, array("name" => $engineClass));
        }
        return new $engineClass(new $paramClass($params));
    }

    static function addStorageEngine($name, $managerClass)
    {
        StorageEngineFactory::$engineCache[$name] = $managerClass;
    }

    static function addNamedEngine($name, $type, $params)
    {
        if (isset(StorageEngineFactory::$engineCache[$name]))
            return StorageEngineFactory::$engineCache[$name];

        $namedEngine = StorageEngineFactory::getEngineByType($type, $params);
        StorageEngineFactory::$engineCache[$name] = $namedEngine;
        return $namedEngine;
    }
    static function setNamedEngine($name,$instance)
    {
        StorageEngineFactory::$engineCache[$name] = $instance;
    }

    static function getNamedEngine($name)
    {
        if (!isset(StorageEngineFactory::$engineCache[$name]))
            throw new StorageEngineFactoryException(StorageEngineFactoryException::ERR_UNKNOWN_NAMED_ENGINE, array("name" => $name));
        return StorageEngineFactory::$engineCache[$name];
    }
    static function clearCache()
    {
        StorageEngineFactory::$engineCache=array();
    }
}

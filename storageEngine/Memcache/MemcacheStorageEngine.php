<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 15/09/15
 * Time: 9:31
 */

namespace lib\storageEngine\Memcache;

use lib\php\ArrayMappedParameters;
use lib\php\ParametrizableString;
use lib\model\BaseException;
use lib\storageEngine\StorageEngineGetParams;
use lib\storageEngine\StorageEngine;
use lib\storageEngine\WritableStorageEngine;
use lib\storageEngine\StorageEngineException;
use lib\storageEngine\StorageEngineSetParams;
use lib\storageEngine\StorageEngineResult;
use lib\storageEngine\IKeyedStorageEngine;
use lib\storageEngine\ICleanableStorageEngine;
use lib\storageEngine\StorageConnectionFactory;


class MemCacheStorageParams extends ArrayMappedParameters
{
    var $connectionName;
    var $queries;
    var $context = array();
}

class MemCacheStorageEngine extends WritableStorageEngine implements ICleanableStorageEngine, IKeyedStorageEngine
{
    var $connection;
    var $definition;
    var $params;

    function __construct(MemCacheStorageParams $definition)
    {
        $this->definition = $definition;
        $this->connection = StorageConnectionFactory::getConnectionByName($definition->connectionName);
        $this->definition = $definition;
        $this->queries = $definition->queries;
    }

    function get(StorageEngineGetParams $spec)
    {


        $identifier = $this->getIdentifierFor($spec);

        try {
            $val = $this->connection->get($identifier);
            if ($val === false)
                throw new StorageEngineException(StorageEngineException::ERR_OBJECT_NOT_FOUND);
        } catch (MemcacheConnectionException $m) {
            throw new StorageEngineException(StorageEngineException::ERR_CONNECT_EXCEPTION, array("name" => $this->name, "type" => "Memcache"), $m);
        }
        return new StorageEngineResult(array("query" => $spec->query, "result" => $val, "source" => $this, "params" => $spec));
    }

    function set(StorageEngineSetParams $spec)
    {
        $query = $this->getQuery($spec->query);
        $identifier = $this->getIdentifierFor($spec);
        try {
            return $this->connection->set($identifier, $spec->values, isset($query["timeout"]) ? $query["timeout"] : null);
        } catch (MemcacheConnectionException $m) {
            throw new StorageEngineException(StorageEngineException::ERR_CONNECT_EXCEPTION, array("name" => $this->name, "type" => "Memcache"), $m);
        }
    }

    function remove(StorageEngineGetParams $spec)
    {
        $this->connection->delete($this->getIdentifierFor($spec));
    }

    function clean()
    {
    }

    function getIdentifierFor(StorageEngineGetParams $spec)
    {
        $spec->merge($this->definition->context, "context");
        $curQuery = $this->getQuery($spec->query);
        return ParametrizableString::getParametrizedString($curQuery["baseIdentifier"], $spec->params);
    }
}
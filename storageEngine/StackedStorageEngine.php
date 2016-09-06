<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 15/09/15
 * Time: 9:36
 */

namespace lib\storageEngine;

use lib\php\ArrayMappedParameters;
use lib\php\ParametrizableString;
use lib\model\BaseException;

class StackedStorageEngineException extends BaseException
{
    const ERR_UNKNOWN_ENGINE = 1;
}

class StackedStorageParams extends ArrayMappedParameters
{
    var $engines = array();
    var $queries = array();
}

// El valor $paramStack contiene un array de ("name"=> //nombre de Engine, "params"=>// una especificacion de parametros get para engine).

class StackedStorageEngine extends StorageEngine implements ICleanableStorageEngine
{
    var $engines;
    var $lastResultSource;
    var $lastResultEngine;
    var $lastResultRole;

    function __construct(StackedStorageParams $params)
    {
        foreach ($params->engines as $key => $value) {
            if (is_string($value)) {
                // No es una instancia de engine, sino una especificacion.Se obtiene de la factoria.
                $this->engines[$key] = StorageEngineFactory::getNamedEngine($value);
            } else
                $this->engines[$key] = $value;
        }
        $this->queries = $params->queries;
    }

    function getEngine($key)
    {
        return $this->engines[$key];
    }

    function get(StorageEngineGetParams $spec)
    {
        $this->lastResultSource = null;
        $stack = $this->getQuery($spec->query);
        $evListeners = array();
        for ($k = 0; $k < count($stack); $k++) {
            $current = $stack[$k];
            $key = $current["engine"];
            $engineParam = array();
            try {
                $engineParam = $this->transformParameters($spec, $key, $spec->query);
                if (!isset($this->engines[$key])) {
                    throw new StackedStorageEngineException(StackedStorageEngineException::ERR_UNKNOWN_ENGINE, array("name" => $key));
                }
                $currentEngine = $this->engines[$key];
                $result = $currentEngine->get($engineParam);
                $seResult = new StorageEngineResult(array(
                    "name" => $key,
                    "query" => $spec->query,
                    "result" => $result->result,
                    "source" => $this,
                    "params" => $spec,
                    "subSource" => $this->engines[$key],
                    "sourceRole" => isset($current["role"]) ? $current["role"] : 0));
                $seResult->setEventListeners($evListeners);
                return $seResult;
            } catch (StorageEngineException $e) {
                if ($e->getCode() == StorageEngineException::ERR_OBJECT_NOT_FOUND) {
                    // Si el objeto actual tiene un role,y el role es > 0, lo aniadimos a los listeners de cambio
                    if ($current["role"]) {
                        $evListeners[$current["role"]][] = array($this->engines[$key], $engineParam);
                    }
                    continue;
                } else
                    throw $e;
            }
        }
        throw new StorageEngineException(StorageEngineException::ERR_OBJECT_NOT_FOUND);
    }

    function transformParameters(StorageEngineParams $getParams, $source, $query)
    {
        $stack = $this->getQuery($query);
        $class = get_class($getParams);

        for ($k = 0; $k < count($stack); $k++) {
            $current = $stack[$k];
            if ($current["engine"] == $source) {
                $params = $getParams->asArray();
                $params = (isset($stack[$k]["params"])) ? $stack[$k]["params"] : array();
                $params["query"] = isset($stack[$k]["query"]) ? $stack[$k]["query"] : $query;

                if (isset($stack[$k]["mapping"]))
                    $params["mapping"] = $stack[$k]["mapping"];


                $params["params"] = $getParams->params;

                $params["context"] = (isset($getParams->context) ? $getParams->context : null);
                if (is_a($getParams, __NAMESPACE__ . "\\StorageEngineSetParams"))
                    $params["values"] = $getParams->values;

                return new $class($params);
            }
        }
        // TODO : throw exception.
        return new $class(array("query" => $getParams->query));
    }

    function __applyToAll($method, $interface, $spec)
    {
        foreach ($this->engines as $key => $value) {

            if (is_a($value, $interface)) {
                try {
                    if ($spec) {
                        $transformed = $this->transformParameters($spec, $key, $spec->query);

                        $value->{$method}($transformed);
                    } else
                        $value->{$method}();

                } catch (StorageEngineException $e) {
                    // TODO : log,, error, etc.
                }
            }
        }
    }

    function set(StorageEngineSetParams $spec)
    {
        $this->__applyToAll("set", __NAMESPACE__ . "\\WritableStorageEngine", $spec);
    }

    function remove(StorageEngineGetParams $spec)
    {
        $this->__applyToAll("remove", "WritableStorageEngine", $spec);
    }

    function clean()
    {
        $this->__applyToAll("clean", "ICleanableStorageEngine", null);
    }
}

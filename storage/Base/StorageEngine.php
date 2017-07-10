<?php
namespace lib\storage\Base;

use lib\php\ArrayMappedParametersException;
use lib\php\ParametrizableString;
use lib\model\BaseException;
use lib\php\ArrayMappedParameters;

class StorageEngineException extends BaseException
{
    const ERR_CONNECT_EXCEPTION = 1;
    const ERR_OBJECT_NOT_FOUND = 2;
    const ERR_UNKNOWN_QUERY = 3;

    const TXT_ERR_CONNECT_EXCEPTION = "Connection error";
    const TXT_ERR_OBJECT_NOT_FOUND = "Object not found";
    const TXT_ERR_UNKNOWN_QUERY = "Query doesnt exist";
}

class WritableStorageException extends StorageEngineException
{
    const ERR_CANT_WRITE_OBJECT = 3;
    const TXT_ERR_CANT_WRITE_OBJECT = "Write error";
}

class GetParam extends ArrayMappedParameters
{
    var $field;
    var $aggregation=null;
    var $altName=null;
    static $__definition=array(
        "fields"=>array(
        "aggregation"=>array("required"=>false),
        "altName"=>array("required"=>false)
        )
    );
}

/*
 *   params es un simple array de tipo clave => valor.
 *
 *   filter funciona de distinta forma.En caso de que exista, lo que hace es sustituir la declaracion de
 *   CONDITIONS del StorageEngine.
 *   filter es un objeto del tipo:
 *   field: <nombre del campo>
 *   operator: <nombre del operador>
 *   value: <valor del operador>
 *   condition: AND | OR // como se relaciona esta condicion, con las contenidas en el campo fields
 *   fields: array de condiciones iguales a esta.
 *
 */

class StorageEngineParams extends ArrayMappedParameters
{
    var $mapping = array();
    var $params = array();
    var $filter = null;
    var $pageStart = null;
    var $nElems = null;
    var $defaults = array();
    var $context = null;

    static $__definition = array(
        "fields" => array(
            "mapping" => array("required" => false),
            "defaults" => array("required" => false),
            "context" => array("required" => false),
            "filter"=>array("required"=>false),
            "pageStart"=>array("required"=>false),
            "nElems"=>array("required"=>false)
        )
    );

    function __construct($arr)
    {
        parent::__construct($arr);
        $this->initialize();

    }
    function initialize()
    {
        if (isset($this->defaults)) {
            foreach ($this->defaults as $key => $value) {
                if (!isset($this->params[$key]))
                    $this->params[$key] = $value;
            }
        }
        if (isset($this->mapping)) {
            $newParams = array();
            foreach ($this->params as $key => $value) {
                if (isset($this->mapping[$key])) {
                    $parts = explode("/", $this->mapping[$key]);
                    $pointer =& $newParams;
                    foreach ($parts as $current) {
                        $pointer =& $pointer[$current];
                    }
                    $pointer = $value;
                } else
                    $newParams[$key] = $value;
            }
            $this->params = $newParams;
        }
        if (isset($this->context)) {
            $this->merge($this->context, "context");
        }
    }

    function merge($variables, $prefix)
    {
        foreach ($variables as $key => $value) {
            $this->params[$prefix . "." . $key] = $value;
        }
    }
}


class StorageEngineGetParams extends StorageEngineParams
{
    const AGG_SUM="SUM";
    const AGG_MAX="MAX";
    const AGG_MIN="MIN";
    const AGG_AVG="AVG";
    const AGG_COUNT="COUNT";
    const AGG_STDDEV="STDDEV";

    const GROUP_MONTH="MONTH";
    const GROUP_DAY="DAY";
    const GROUP_WEEKDAY="GROUP_WEEKDAY";
    const GROUP_HOUR="HOUR";
    const GROUP_WEEK="WEEK";
    const GROUP_RANGE="RANGE";

    var $requestedFields=null;
    var $sorting = null; // sorting es un array campo=>direccion
    var $grouping = null;

    static $__definition=array(
        "fields"=>array(
            "requestedFields"=>array("required"=>false,"relation"=>'\lib\storageEngine\GetParamTransform'),
            "grouping"=>array("required"=>false, "relation"=>'\lib\storageEngine\GetParamTransform'),
            "sorting"=>array("required"=>false)
        )
    );
}

abstract class StorageEngine
{
    var $name;
    // Atencion! esta variable estatica debe ser sobreescrita por los engines.
    var $queries;

    abstract function get(StorageEngineGetParams $spec);

    function setName($name)
    {
        $this->name = $name;
    }

    function getFromArray($arr)
    {
        return $this->get(new StorageEngineGetParams($arr));
    }
}

class StorageEngineSetParams extends StorageEngineGetParams
{
    const INSERT=1;
    const UPDATE=2;
    const DELETE=3;
    const UPSERT=4;
    var $values = null;
    var $type;
    static $__definition=array(
        "fields"=>array(
            "values"=>array("required"=>false)
        )
    );
}

abstract class WritableStorageEngine extends StorageEngine
{
    abstract function set(StorageEngineSetParams $spec);

    abstract function remove(StorageEngineGetParams $spec);

    function setFromArray($arr)
    {
        return $this->set(new StorageEngineSetParams($arr));
    }
}

interface ICleanableStorageEngine
{
    function clean();
}

interface IKeyedStorageEngine
{
    function getIdentifierFor(StorageEngineGetParams $request);
}


class StorageEngineResult extends ArrayMappedParameters
{
    var $source;
    var $query;
    var $subSource = null;
    var $params;
    var $result;
    var $parentResult = null;
    var $sourceRole = null;
    var $dataStart;
    var $dataEnd;
    var $dataCount;
    var $eventListeners = array();
    static $__definition = array(
        "fields" => array(
            "dataStart" => array("required" => false),
            "dataEnd" => array("required" => false),
            "dataCount" => array("required" => false),
            "subSource" => array("required" => false),
            "parentResult" => array("required" => false),
            "sourceRole" => array("required" => false))
    );

    function setEventListeners($def)
    {
        $this->eventListeners = $def;
    }

    function addEventListener($event, $source, $parameters)
    {
        $this->eventListeners[$event] = array($source, $parameters);
    }

    function on($state, $value)
    {
        if (isset($this->eventListeners[$state])) {
            foreach ($this->eventListeners[$state] as $k => $v) {
                $setParamsArray = $v[1]->asArray();
                $setParamsArray["values"] = $value;
                $setParams = new StorageEngineSetParams($setParamsArray);
                $v[0]->set($setParams);
            }
        }
        if ($this->parentResult) {
            $this->parentResult->on($state, $value);
        }
    }

    function getValue()
    {
        return $this->result;
    }
}
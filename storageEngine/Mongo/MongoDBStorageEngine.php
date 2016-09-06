<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 15/09/15
 * Time: 9:31
 */

namespace lib\storageEngine\Mongo;
include_once(__DIR__."/../StorageEngine.php");
use lib\php\ArrayMappedParameters;
use lib\php\ArrayMappedParametersException;
use lib\php\ParametrizableString;
use lib\model\BaseException;
use lib\storageEngine\StorageEngineGetParams;
use lib\storageEngine\StorageEngine;
use lib\storageEngine\WritableStorageEngine;
use lib\storageEngine\StorageEngineException;
use lib\storageEngine\StorageEngineSetParams;
use lib\storageEngine\StorageEngineResult;
use lib\storageEngine\StorageEngineQuery;
use lib\storageEngine\QueryConditionConstants;
use lib\storageEngine\StorageConnectionFactory;
use lib\storageEngine\ICleanableStorageEngine;

class MongoDBStorageEngineException extends BaseException{
    const ERR_SET_EXCEPTION=1;
}

/*
 *  Ver documentacion de MysqlQuery
 *  Lo importante aqui es:
 *  En mongo, tenemos queries y commands. Es por eso que existe una variable para cada uno de ellos en la query.
 *  No existe en este momento una subclase para cada uno, pero ambos (query y command), pueden tener base y filters.
 *  Ver los tests para ejemplos.
 *
 *
 *
 *
 */
class MongoDBQuery extends StorageEngineQuery
{
    // Si no esta definida en la query, se tomara la definida en el los parametros del StorageEngine

    var $type;
    var $query;
    var $command;
    var $options=array();
    var $typemap;
    var $values;
    var $mapAllValues;
    var $__mongoQuery;
    var $__mongoCommand;
    static $__definition=array(
        "fields"=>array(
            "query"=>array("required"=>false),
            "command"=>array("required"=>false),
            "mapAllValues"=>array("default"=>true),
            "values"=>array("required"=>false),
            "typemap"=>array("required"=>false)
        )
    );

    function __construct($arr)
    {
        parent::__construct($arr);
        if(!isset($this->query) && !isset($this->command))
        {
            throw new ArrayMappedParametersException(ArrayMappedParametersException::ERR_REQUIRED_PARAMETER,array("field"=>"query/command"));
        }
        $this->__mongoQuery=null;
        $this->__mongoCommand=null;
    }
    function parse(StorageEngineGetParams $params)
    {
        $filter=null;
        if($params->filter)
            $filter=$this->parseFilter($params->filter);
        else {
            if (isset($this->query)) {
                $filter = $this->query["base"]["filter"];
            } else {
                if (isset($this->command) && isset($this->command["base"]))
                    $filter=$this->command["base"]["filter"];
            }
        }
        // Se sustituye el uso de parametros dentro de los filtros.
        if($filter)
        {
            $newParams=$this->getBaseQueryParameters($params);
            $filter=$this->getConditionFilter($filter,$newParams);
        }
        if(isset($this->query))
        {
            $d=$this->query;
            unset($d["base"]);
            // Establecemos la base como entrada de "filter"
            $d["filter"]=$filter;
            if($params->sorting)
            {
                foreach($params->sorting as $key=>$value)
                {
                    $d["options"]["sort"][$key]=($value=="ASC"?1:-1);
                }
            }
            if($params->pageStart!==null)
            {
                $d["options"]["skip"]=$params->pageStart;
            }
            if($params->nElems)
            {
                $d["options"]["limit"]=$params->nElems;
            }
            $this->__mongoQuery=$d;
            $this->collection=$d["collection"];
            //$this->__mongoQuery=new \MongoDB\Driver\Query($q["filter"],$q["options"]);
        }
        else
        {
            $q=$this->command;
            $this->collection=$q["collection"];
            if($filter)
                $q["query"]=$filter;
            unset($q["base"]);
            ParametrizableString::applyRecursive($q,$params->params,1);
            $this->__mongoCommand=$q;
            //$this->__mongoCommand=new \MongoDB\Driver\Command($q);
        }
    }
    function getMongoQuery()
    {
        return $this->__mongoQuery;
    }
    function getMongoCommand()
    {
        return $this->__mongoCommand;
    }
    function isCommand()
    {
        return $this->__mongoCommand!=null;
    }
    function getCollection()
    {
        return $this->collection;
    }
    function getUnusedParameterReplacement()
    {
        return array("_id"=>array('$ne'=>-1));
    }
    function parseFilter($cond)
    {
        $partial=null;
        $field=$cond["field"];
        $operator=$cond["operator"];
        $value=$cond["value"];
        // No nos metemos aqui en obtener cada uno de los tipos, etc, etc.
        switch($cond["operator"])
        {
            case QueryConditionConstants::COND_EQUALS:{
                $partial=array($field=>array('$eq'=>$value));
            }break;
            case QueryConditionConstants::COND_GREATER:{
                $partial=array($field=>array('$gt'=>$value));
            }break;
            case QueryConditionConstants::COND_SMALLER:{
                $partial=array($field=>array('$lt'=>$value));
            }break;
            case QueryConditionConstants::COND_NOTNULL:{
                $partial=array($field=>array('$exists'=>true));
            }break;
            case QueryConditionConstants::COND_NULL:{
                $partial=array($field=>array('$exists'=>false));
            }break;
            case QueryConditionConstants::COND_STARTS_WITH:{
                $partial=array($field=>array('$regex'=>'^'.$value));
            }break;
            case QueryConditionConstants::COND_ENDS_WITH:{
                $partial=array($field=>array('$regex'=>'.*'.$value.'$'));
            }break;
            case QueryConditionConstants::COND_LIKE:{
                $partial=array($field=>array('$regex'=>'.*'.$value.'.*'));
            }break;
            case QueryConditionConstants::FROM_LAST_WEEK:{

            }break;
            case QueryConditionConstants::FROM_LAST_MONTH:{

            }break;
            case QueryConditionConstants::FROM_LAST_DAY:{

            }break;
        }
        if(isset($cond["fields"]))
        {
            $subFields=array($partial);
            for($k=0;$k<count($cond["fields"]);$k++)
            {
                $subFields[]=$this->parseFilter($cond["fields"][$k]);
            }
            switch($cond["condition"])
            {
                case "AND":{return array('$and'=>$subFields);}break;
                case "OR":{return array('$or'=>$subFields);}break;
            }
        }
        return $partial;
    }
}

class MongoDBStorageParams extends ArrayMappedParameters
{
    var $connectionName;
    var $queries;
    var $context = array();
    static $__definition=array(
        "fields"=>array(
            "queries"=>array("dictionary"=>'\lib\storageEngine\Mongo\MongoDBQuery')
        )
    );
}

class MongoDBStorageEngine extends WritableStorageEngine implements ICleanableStorageEngine
{
    var $connection;
    var $definition;
    var $params;

    function __construct(MongoDBStorageParams $definition)
    {
        $this->definition = $definition;
        \lib\php\ArrayTools::flattenArray($definition->asArray(),$d2);
        $this->connection = StorageConnectionFactory::getConnectionByName(
            ParametrizableString::getParametrizedString($definition->connectionName,$d2)
        );
        $this->queries = $definition->queries;
    }

    function get(StorageEngineGetParams $spec)
    {
        $q=$this->parseQuery($spec);
        $data=$this->connection->get($q);
        return new StorageEngineResult(array("query" => $spec->query, "result" => $data, "source" => $this, "params" => $spec));
    }

    function set(StorageEngineSetParams $spec)
    {
        $queryObj=$this->getQuery($spec->query);
        $queryObj->parse($spec);
        $q=$queryObj->getMongoQuery();
        if(!$q)
        {
            // Es un command
            $data=$this->connection->get($queryObj->getMongoCommand());
            return new StorageEngineResult(array("query" => $spec->query, "result" => $data, "source" => $this, "params" => $spec));
        }
        $qValues=array();
        $qParams=array();
        if($queryObj->type!=MongoDBQuery::INSERT && isset($q["filter"])) {
            $qParams = $q["filter"];
            $p = $spec->params;
            ParametrizableString::applyRecursive($qParams, $p);
        }
        if($queryObj->type!=MongoDBQuery::DELETE) {
            if(!isset($queryObj->mapAllValues) || $queryObj->mapAllValues==false) {
                $qValues = $queryObj->values;
                $v = $spec->values;
                ParametrizableString::applyRecursive($qValues, $v);
            }
            else
            {
                $qValues=$spec->values;
            }
        }
        try {
            switch ($queryObj->type) {
                case MongoDBQuery::INSERT: {
                    $result=$this->connection->insert($queryObj->query["collection"], $qValues);
                }break;
                case MongoDBQuery::UPDATE: {
                    $result=$this->connection->update($queryObj->query["collection"], $qParams, $qValues, false);
                }break;
                case MongoDBQuery::UPSERT: {
                    $result=$this->connection->update($queryObj->query["collection"], $qParams, $qValues, true);
                }break;
                case MongoDBQuery::DELETE: {
                    $filter=$q["filter"]?$q["filter"]:array();
                    $deleteOptions=array();
                    if($spec->nElems > 0)
                        $deleteOptions["limit"]=$spec->nElems;
                    $result=$this->connection->delete($queryObj->query["collection"], $filter,$deleteOptions);
                }break;
            }
        }catch( \Exception $e)
        {

            throw new MongoDBStorageEngineException(MongoDBStorageEngineException::ERR_SET_EXCEPTION);
        }
        /*
         * $result contiene : nInserted,nMatched,nModified,nRemoved,nUpserted, upsertedIds
         */
    }

    function remove(StorageEngineGetParams $spec)
    {
        $this->set($spec);
    }
    function clean()
    {
    }
    function parseQuery(StorageEngineGetParams $spec)
    {
        $spec->merge($this->definition->context, "context");
        $curQuery = $this->getQuery($spec->query);
        $curQuery->parse($spec);
        return $curQuery;
    }
}
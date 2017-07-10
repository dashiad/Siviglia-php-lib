<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 15/09/15
 * Time: 9:31
 */

namespace lib\storage\Mysql;
include_once(LIBPATH."/storage/Base/StorageEngine.php");
include_once(LIBPATH."/storage/Base/Query/Query.php");
include_once(__DIR__."/Types.php");
use lib\php\ArrayMappedParameters;
use lib\php\ArrayMappedParametersException;
use lib\php\ParametrizableString;
use lib\model\BaseException;
use lib\storage\Base\StorageEngineGetParams;
use lib\storage\Base\StorageEngine;
use lib\storage\Base\Query\QueryException;
use lib\storage\Base\WritableStorageEngine;
use lib\storage\Base\StorageConnectionFactory;
use lib\storage\Base\StorageEngineSetParams;
use lib\storage\Base\StorageEngineResult;
use lib\storage\Base\Query\Query;
use lib\storage\Base\Query\QueryConditionConstants;
use lib\storage\Base\ICleanableStorageEngine;

class MysqlStorageEngineException extends BaseException{
    const ERR_SET_EXCEPTION=1;
    const ERR_UNDEFINED_PARAMETER=2;

    const TXT_SET_EXCEPTION="Excepcion en Mysql SET";
    const TXT_UNDEFINED_PARAMETER="Parametro no definido : [%param_name%]";
}


class MysqlStorageParams extends ArrayMappedParameters
{
    var $connectionName;
    var $queries;
    var $context = array();
    static $__definition=array(
        "fields"=>array(
            "queries"=>array("dictionary"=>'\lib\storage\Base\Mysql\MysqlQuery')
        )
    );
}

class MysqlStorageEngine extends WritableStorageEngine implements ICleanableStorageEngine
{
    var $connection;
    var $definition;
    var $params;

    function __construct(MysqlStorageParams $definition)
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
        $data=$this->connection->query($q);
        return new StorageEngineResult(array("query" => $spec->query, "result" => $data, "source" => $this, "params" => $spec));
    }

    function set(StorageEngineSetParams $spec)
    {
        $q=$this->parseQuery($spec);
        $this->connection->query($q);
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
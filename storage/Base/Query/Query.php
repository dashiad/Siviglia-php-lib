<?php
namespace lib\storage\Base\Query;
include_once(LIBPATH."/storage/Base/StorageEngine.php");

use \lib\storage\Base\StorageEngineParams;
class QueryConditionConstants
{
    const COND_EQUALS="EQUALS";
    const COND_GREATER="GREATER";
    const COND_SMALLER="SMALLER";
    const COND_NOTNULL="NOTNULL";
    const COND_NULL="NULL";
    const COND_STARTS_WITH="STARTS_WITH";
    const COND_ENDS_WITH="ENDS_WITH";
    const COND_LIKE="LIKE";
    const FROM_LAST_WEEK="FROM_LAST_WEEK";
    const FROM_LAST_MONTH="FROM_LAST_MONTH";
    const FROM_LAST_DAY="FROM_LAST_DAY";
}

class QueryException extends \lib\model\BaseException
{
    const ERR_UNKNOWN_OPERATOR=1;
    const ERR_MISSING_FILTER_VALUE=2;
    const ERR_UNKNOWN_PARAMETER=3;
    const ERR_UNKNOWN_FIELD=4;

    const TXT_UNKNOWN_OPERATOR="Operador desconocido en filtro : [%operator%]";
    const TXT_MISSING_FILTER_VALUE="Falta valor para el campo [%field%] en el filtro";
    const TXT_UNKNOWN_PARAMETER="Parametro desconocido en filtro: [%field%]";
    const TXT_UNKNOWN_FIELD="Campo [%field%] no encontrado en tabla [%table%]";
}

abstract class Query extends \lib\php\ArrayMappedParameters
{
    var $parameters=null;
    var $conditions=null;
    var $cursorTree;
    static $__definition=array(
        "fields"=>array(
            "parameters"=>array("required"=>false),
            "conditions"=>array("required"=>false),
            "cursorTree"=>array("required"=>false,"default"=>null)
        )
    );

    function getBaseQueryParameters(StorageEngineParams $params)
    {
        if(!$this->parameters)
            return array();
        $result=array();
        $receivedParams=$params->params;
        foreach($this->conditions as $key=>$value)
        {
            $trigger=$value["TRIGGER_VAR"];
            if(!isset($receivedParams[$trigger]))
            {
                $result[$key]=$this->getUnusedParameterReplacement();
                continue;
            }
            $cVal=$receivedParams[$trigger];
            if(isset($value["DISABLE_IF"]) && $cVal===$value["DISABLE_IF"])
            {
                $result[$key]=$this->getUnusedParameterReplacement();
                continue;
            }
            $val=$this->getConditionFilter($value["FILTER"],$receivedParams);
            $result[$key]=$val;
        }
        return $result;
    }

    abstract function getUnusedParameterReplacement();

    // $keyBuilder es un callback para construir la key de los parametros.Por defecto, la key es el nombre dado en el array.
    // MysqlQuery requiere que sea [%key%]
    function getConditionFilter($filter,$params)
    {
        $isArray=false;
        if(is_array($filter))
        {
            $isArray=true;
            $filter=json_encode($filter);
        }
        // En caso de que en params se pasen arrays, hay que convertirlos a json
        $parsedParams=array();
        foreach($params as $key=>$value)
        {

            if(is_array($value))
                $parsedParams[$key]=json_encode($value);
            else
                $parsedParams[$key]=$value;
        }

        $result=\lib\php\ParametrizableString::getParametrizedString($filter,$parsedParams);
        if($isArray)
            return json_decode($result,true);
        return $result;
    }

    abstract function setConnection(\lib\storage\Base\Connectors\Connector $conn);
    function execute(StorageEngineParams $params)
    {
        $newQuery=$this->parse($params);
        $mainCursor=$this->getCursor($newQuery,$params);
        if(!$this->cursorTree)
            return $mainCursor;
        $lastCursor=\lib\storage\Cursor\CursorFactory::getCursor($this->cursorTree,$mainCursor);
        return $lastCursor;
    }
    abstract function parse(StorageEngineParams $params);
    abstract function getCursor($newQuery,StorageEngineParams $params);
}

abstract class QueryFactory
{
    abstract function getInstance($arr);
}
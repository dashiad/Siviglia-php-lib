<?php
/*
     Siviglia Framework
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
use lib\storage\Base\StorageEngineSetParams;
use lib\storage\Base\StorageEngineResult;
use lib\storage\Base\Query\Query;
use lib\storage\Base\Query\QueryConditionConstants;
use lib\storage\Base\ICleanableStorageEngine;

abstract class BaseMysqlQuery extends Query
{
    // Si no esta definida en la query, se tomara la definida en el los parametros del StorageEngine
    /*
     *   Hay 2 formas de filtrar las queries:
     *   conditions (clase base): ciertos parametros tienen ciertos valores.En la query, debe existir una referencia a ese parametro
     *   en la forma [%<paramName>%],  y sera sustituido por el valor calculado.
     *
     *
     *   filter (especificado en los parametros) : es una expresion booleana "free-form", es decir, contiene una estructura anidada de condiciones
     *   logicas, especificadas en un array asociativo.En esa estructura se pueden usar los parametros repetidamente.
     *   En la query se especifica con la forma [[%filter%]].
     *   La estructura de condiciones es:
     *   {
     *      condition:'AND' | 'OR'
     *      field: <campo>
     *      operator: <operador>
     *      value: <valor>
     *      fields: [] <-- array de subcondiciones
     *   }
     *
     *   Aparte de estos, hay 3 valores especiales:
     *   [[%group%]] [[%limit%]] y [[%sort%]] , que utilizan los valores especificados en la query.
     *
     *   A diferencia de MongoDB, donde hay queries y commands, aqui solo hay queries, por lo que
     *   no existe un primer nivel de query/command. Directamente, se define base y filter.
     *   base se puede especificar como una string, o como un array.
     *   Si es una string, es la query directamente,
     *      con sus placeholders.
     *   Si es un array, tiene las entradas "TABLE"  y "FIELDS", y la query se compone a partir de ahi.
     *
     *   Lo mismo, si en vez de filtros se calculan condiciones anidadas.
     */
    var $base;
    var $_composedQuery;
    var $_calculatedFields;
    var $fields;
    static $__definition=array(
        "fields"=>array(
            "fields"=>array("required"=>false),
            "base"=>array("required"=>true),
            "filter"=>array("required"=>false)
        )
    );

    function __construct($arr)
    {
        parent::__construct($arr);
    }
    function setConnection($connection)
    {
        BaseType::$mysqlLink=$connection;
    }
    function getConnection()
    {
        return BaseType::$mysqlLink;
    }
    function parse(StorageEngineGetParams $params)
    {
        $filter = null;

        // Si se nos han especificado condiciones,
        // llamamos a parseFilter, para resolver la estructura anidada.

        if ($params->filter) {
            $filter["[[%filter%]]"] = $this->parseFilter($params->filter);
        } else {
            // Si no hay filtro, hay que meter un reemplazo
            $filter["[[%filter%]]"] = $this->getUnusedParameterReplacement();
            // Si no, hay que ver que parametros son los que existen y se han recibido como parametros,
            // (getBaseQueryParameters), y luego, mirar en los filtros de la query, cual es la expresion
            // de filtro asociada.
            if (isset($this->conditions)) {
                $f1 = $this->getBaseQueryParameters($params);
                $filter = array();
                foreach ($f1 as $key => $value) {
                    $filter["[%" . $key . "%]"] = $value;
                }
            }
        }
        // Ahora en filter tenemos que tener un array asociativo de key=>value, que son las cosas que hay que sustituir en la
        // cadena final.
        // Pero hay que aniadir los valores "especiales", como agrupacion, limite y ordering.
        // [[%group%]] [[%limit%]] [[%sort%]]


        if ($params->nElems !== null) {
            if ($params->pageStart !== null)
                $filter["[[%limit%]]"] = "LIMIT " . $params->pageStart . "," . $params->nElems;
            else
                $filter["[[%limit%]]"] = "LIMIT " . $params->nElems;
        } else
            $filter["[[%limit%]]"] = "";

        $extraFields = array();
        if ($params->grouping) {
            // Al gruping se le pasa el sorting, ya que el criterio de ordenacion se convierte en el criterio de agrupado.
            $filter["[[%group%]]"] = "GROUP BY " . $this->parseGrouping($params->grouping, $params->sorting, $extraFields);
        } else
            $filter["[[%group%]]"] = "";

        if ($params->sorting) {
            $sortParts = [];
            foreach ($params->sorting as $key => $value)
                $sortParts[] = $key . " " . $value;
            $filter["[[%sort%]]"] = "ORDER BY " . implode(",", $sortParts);
        } else
            $filter["[[%sort%]]"] = "";


        return $this->getBaseQuery($params, $filter, $extraFields);
    }
    abstract function getBaseQuery($params,$filter,$extraFields);


    function getUnusedParameterReplacement()
    {
        return "TRUE";
    }

    function parseFilter($cond)
    {
        $partial=null;
        $fieldReference=null;
        $field=$cond["FIELD"];
        $operator=$cond["OPERATOR"];

        if(!isset($this->parameters[$field]))
            throw new QueryException(QueryException::ERR_UNKNOWN_PARAMETER, array("field" => $field));
        if(isset($cond["VALUE"])) {
            $value = $cond["VALUE"];
            if (!is_a($value, '\lib\model\types\BaseType'))
                $value = \lib\model\types\TypeFactory::getType(null, $this->parameters[$field], $value);
            $serializer = \lib\storage\Mysql\BaseType::getSerializerFor($value);
            $value = $serializer::serialize($value);
        }
        // No nos metemos aqui en obtener cada uno de los tipos, etc, etc.

        switch($operator)
        {
            case QueryConditionConstants::COND_EQUALS:{
                if(!isset($value))
                    throw new QueryException(QueryException::ERR_MISSING_FILTER_VALUE, array("field" => $field));
                $partial=$field." = ".$value;
            }break;
            case QueryConditionConstants::COND_GREATER:{
                $partial=$field." > ".$value;
            }break;
            case QueryConditionConstants::COND_SMALLER:{
                $partial=$field." < ".$value;
            }break;
            case QueryConditionConstants::COND_NOTNULL:{
                $partial=$field." IS NOT NULL";
            }break;
            case QueryConditionConstants::COND_NULL:{
                $partial=$field." IS NULL";
            }break;
            case QueryConditionConstants::COND_STARTS_WITH:{
                $partial=$field." LIKE '".trim($value,"'")."%'";
            }break;
            case QueryConditionConstants::COND_ENDS_WITH:{
                $partial=$field." LIKE '%".trim($value,"''")."'";
            }break;
            case QueryConditionConstants::COND_LIKE:{
                $partial=$field." LIKE '%".trim($value,"'")."%'";
            }break;
            case QueryConditionConstants::FROM_LAST_WEEK:{

            }break;
            case QueryConditionConstants::FROM_LAST_MONTH:{

            }break;
            case QueryConditionConstants::FROM_LAST_DAY:{

            }break;
            default:
            {
                throw new QueryException(QueryException::ERR_UNKNOWN_OPERATOR,array("operator"=>$operator));
            }
        }

        if(isset($cond["FIELDS"]))
        {
            $subFields=array($partial);
            for($k=0;$k<count($cond["FIELDS"]);$k++)
            {
                $subFields[]="(".$this->parseFilter($cond["FIELDS"][$k]).")";
            }
            switch($cond["CONDITION"])
            {
                case "AND":{return implode(" AND ",$subFields);}break;
                case "OR":{return implode(" OR ",$subFields);}break;
            }
        }
        return $partial;
    }

    function getBaseQueryParameters(StorageEngineGetParams $params)
    {
        if(!$this->parameters)
            return array();
        $result=array();
        $receivedParams=$params->params;
        $serialized=array();
        // Primero, se convierten todos los parametros a sus tipos
        foreach($receivedParams as $key=>$value)
        {
            if(!is_a($value,'\lib\model\types\BaseType'))
            {
                if(isset($this->parameters[$key]))
                    $receivedParams[$key] =\lib\model\types\TypeFactory::getType(null, $this->parameters[$key], $value);
                else
                    throw new MysqlStorageEngineException(MysqlStorageEngineException::ERR_UNDEFINED_PARAMETER,array("param_name"=>$key));
            }
            $serializer=BaseType::getSerializerFor($receivedParams[$key]);
            $serialized[$key]=$serializer::serialize($receivedParams[$key],array("raw"=>true));

        }

        foreach($this->conditions as $key=>$value)
        {
            $trigger=$value["TRIGGER_VAR"];
            if(!isset($receivedParams[$trigger]))
            {
                $result[$key]=$this->getUnusedParameterReplacement();
                continue;
            }
            $cVal=$receivedParams[$trigger]->getValue();

            if(isset($value["DISABLE_IF"]) && $cVal===$value["DISABLE_IF"])
            {
                $result[$key]=$this->getUnusedParameterReplacement();
                continue;
            }
            $result[$key]=ParametrizableString::getParametrizedString($value["FILTER"],$serialized);
        }
        return $result;
    }



    function getQueryText()
    {
        return $this->_composedQuery;
    }

    protected function parseFieldAggregation($reqFields)
    {
        $fieldList=array();
        $exp="";
        for($k=0;$k<count($reqFields);$k++)
        {
            $c=$reqFields[$k];
            $field=$c->field;
            switch($c->aggregation)
            {
                case StorageEngineGetParams::AGG_SUM:{$exp="SUM($field)";}break;
                case StorageEngineGetParams::AGG_MAX:{$exp="MAX($field)";}break;
                case StorageEngineGetParams::AGG_MIN:{$exp="MIN($field)";}break;
                case StorageEngineGetParams::AGG_AVG:{$exp="AVG($field)";}break;
                case StorageEngineGetParams::AGG_COUNT:{$exp="COUNT($field)";}break;
                case StorageEngineGetParams::AGG_STDDEV:{$exp="STDDEV($field)";}break;
                default:{
                    $exp=$c->field;
                }break;
            }

            if($c->altName) {
                $this->_calculatedFields[$c->altName]=array("TYPE"=>"Decimal","CALCULATED"=>array("SOURCE"=>"FIELDAGGREGATION","FIELD"=>$field,"AGGREGATION"=>$c->aggregation));
                $exp .= " " . $c->altName;
            }
            else
                $this->_calculatedFields[$exp]=array("TYPE"=>"Decimal","CALCULATED"=>array("SOURCE"=>"FIELDAGGREGATION","FIELD"=>$field,"AGGREGATION"=>$c->aggregation));

            $fieldList[]=$exp;
        }
        return implode(",",$fieldList);
    }

    protected function parseGrouping($grouping,& $sorting,& $fields)
    {
        $fieldList=array();
        $fields=array();
        for($k=0;$k<count($grouping);$k++)
        {
            $c=$grouping[$k];
            $field=$c->field;
            $calculated=array("SOURCE"=>"GROUPING","FIELD"=>$field,"AGGREGATION"=>$c->aggregation);
            switch($c->aggregation)
            {
                case StorageEngineGetParams::GROUP_MONTH:{
                    $exp="YEAR($field),MONTH($field)";
                    $fields[]="YEAR($field) as g_year";
                    $fields[]="MONTH($field) as g_month";
                    if(!isset($sorting["g_year_$field"]))
                        $sorting["g_year_$field"]="ASC";
                    if(!isset($sorting["g_month_$field"]))
                        $sorting["g_month_$field"]="ASC";
                    $this->_calculatedFields["g_year"]=array("TYPE"=>"Integer","CALCULATED"=>array("SOURCE"=>"GROUPING","FIELD"=>$field,"AGGREGATION"=>$c->aggregation,"PARAM"=>"Year"));
                    $this->_calculatedFields["g_month"]=array("TYPE"=>"Integer","CALCULATED"=>array("SOURCE"=>"GROUPING","FIELD"=>$field,"AGGREGATION"=>$c->aggregation,"PARAM"=>"Month"));
                }break;

                case StorageEngineGetParams::GROUP_DAY:{
                    $exp="DATE($field)";
                    $fields[]=$exp." g_day_$field";
                    if(!isset($sorting["g_day_$field"]))
                        $sorting["g_day_$field"]="ASC";
                    $this->_calculatedFields["g_$field"]=array("TYPE"=>"Integer","CALCULATED"=>$calculated);
                }break;
                case StorageEngineGetParams::GROUP_WEEKDAY:{
                    $exp="DAYOFWEEK($field)";
                    $fields[]=$exp." g_dweek_$field";
                    if(!isset($sorting["g_dweek_$field"]))
                        $sorting["g_dweek_$field"]="ASC";
                    $this->_calculatedFields["g_dweek_$field"]=array("REFERENCES"=>$field,"CALCULATED"=>$calculated);
                }break;
                case StorageEngineGetParams::GROUP_HOUR:{
                    $exp="HOUR($field)";
                    $fields[]=$exp." g_hour_$field";
                    if(!isset($sorting["g_hour_$field"]))
                        $sorting["g_hour_$field"]="DESC";
                    $this->_calculatedFields["g_hour_$field"]=array("TYPE"=>"Integer","CALCULATED"=>$calculated);
                }break;
                case StorageEngineGetParams::GROUP_WEEK:{
                    $exp="WEEK($field)";
                    $fields[]=$exp." g_week_$field";
                    if(!isset($sorting["g_week_$field"]))
                        $sorting["g_week_$field"]="DESC";
                    $this->_calculatedFields["g_week_$field"]=array("TYPE"=>"Integer","CALCULATED"=>$calculated);
                }break;
                case StorageEngineGetParams::GROUP_RANGE:{

                }break;
                default:{
                    $exp=$field;
                    $fields[]=$field;
                    if(!isset($sorting[$field]))
                        $sorting[$field]="DESC";
                }break;
            }
            $fieldList[]=$exp;
        }
        return implode(",",$fieldList);
    }

    function getCalculatedFields()
    {
        return $this->_calculatedFields;
    }
}

// Una FixedMysqlQuery , tiene como base una cadena de texto, es decir, una query prefijada.Su estructura es fija

class FixedMysqlQuery extends BaseMysqlQuery
{
    function getBaseQuery($params,$filter,$extraFields)
    {
        if(is_a($params,'\lib\storage\Base\StorageEngineSetParams'))
        {
            // Si los parametros son de tipo SET, en una query FIXED, se tratan commo si fueran simples parametros.
        }
        $base=$this->base;
        // Se reemplazan las keys de $filter por sus valores.
        $this->_composedQuery=str_replace(array_keys($filter),array_values($filter),$this->base);
        return $this->_composedQuery;
    }
}
class DynamicMysqlQuery extends BaseMysqlQuery
{
    function getBaseQuery($params,$filter,$extraFields)
    {

        if(is_a($params,'\lib\storage\Base\StorageEngineSetParams'))
            $base=$this->composeSetQuery($params,$filter,$extraFields);
        else
            $base=$this->composeSelectQuery($params,$filter,$extraFields);

        if(isset($this->filter))
        {
            $nKeys=array();
            foreach($this->filter as $key=>$value)
                $nKeys[]="[%".$key."%]";
            $base.=" WHERE ".implode(" AND ".$nKeys);
        }
        $base.=" [[%group%]] [[%sort%]] [[%limit%]]";

        // Se reemplazan las keys de $filter por sus valores.
        $this->_composedQuery=str_replace(array_keys($filter),array_values($filter),$base);
        return $this->_composedQuery;
    }
    protected function composeSelectQuery($params,$filter,$extraFields)
    {
        if($params->requestedFields!=null)
        {
            $selectedFields=$this->parseFieldAggregation($params->requestedFields);
        }
        else
        {
            $selectedFields=implode(",",$this->base["FIELDS"]);
        }
        // Los campos extra son los que se han autoincluido debido a la agrupacion
        if($extraFields)
        {
            $selectedFields=implode(",",$extraFields).($selectedFields!=""?",".$selectedFields:"");
        }

        return "SELECT ".$selectedFields." FROM ".$this->base["TABLE"];
    }

    protected function composeSetQuery($params,$filter,$extraFields)
    {
        $type=$params->type;
        $newParams=array();
        // Se usan los filtros definidos, usando "AND"
        if(isset($filter))
        {

            foreach($filter as $key=>$value)
            {
                if($value!="")
                    $newParams[]=$value;
            }
        }

        switch($type)
        {
            case StorageEngineSetParams::DELETE:
            {
                // Las condiciones las pondra la funcion getBaseQuery
                $base="DELETE FROM ".$this->base["TABLE"];
                if(count($newParams))
                    $base.=" WHERE ".implode(" AND ",$newParams);
                return $base;
            }break;
            case StorageEngineSetParams::INSERT:
            case StorageEngineSetParams::UPDATE:
            case StorageEngineSetParams::UPSERT:
            {
                $meta=null;
                $serialized=array();
                $parts=[];
                foreach($params->values as $k=>$v) {
                    $def=null;

                    if (!is_a($v, '\lib\model\types\BaseType')) {
                        if (isset($this->fields[$k]))
                            $def = $this->fields[$k];
                        else {
                            if ($meta == null) {
                                $meta = $this->getConnection()->discoverTableFields($this->base["TABLE"]);
                            }
                            if(!isset($meta[$k]))
                                throw new QueryException(QueryException::ERR_UNKNOWN_FIELD,array("field"=>$k,"table"=>$this->base["TABLE"]));
                            $def=$meta[$k];
                        }

                        $v = \lib\model\types\TypeFactory::getType(null, $def, $v);
                    }
                    $serializer = \lib\storage\Mysql\BaseType::getSerializerFor($v);
                    $serialized[$k] = $serializer::serialize($v);
                    $parts[]=$k."=".$serialized[$k];
                }
                if($type==StorageEngineSetParams::INSERT)
                    return "INSERT INTO ".$this->base["TABLE"]." (".implode(",",array_keys($serialized)).") VALUES (".implode(",",array_values($serialized)).")";
                if($type==StorageEngineSetParams::UPDATE)
                {
                    $base="UPDATE ".$this->base["TABLE"]." SET ".implode(",",$parts);
                    if(count($newParams)>0)
                        $base.=" WHERE ".implode(" AND ",$newParams);

                    return $base;
                }

            }
        }
    }
}


class MysqlQueryFactory extends \lib\storage\Base\Query\QueryFactory
{
    function getInstance($arr)
    {
        if(is_array($arr["base"]))
        {
            return new DynamicMysqlQuery($arr);
        }
        return new FixedMysqlQuery($arr);
    }
}
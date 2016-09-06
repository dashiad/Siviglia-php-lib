<?php
namespace lib\storage\Dictionary;

class DictionaryDataSource extends \lib\datasource\ArrayDataSource
{
    protected $serializer;
    protected $data;
    protected $nRows;
    protected $iterator;
    protected $parameters;

    function __construct($objName,$dsName,$definition,$serializer,$serializerDefinition=null)
    {
        parent::__construct($objName, $dsName, $definition);

        if($serializerDefinition)
            $this->serializerDefinition=$serializerDefinition;
        $this->serializer=$serializer;
    }

    function setParameters($obj)
    {
        $remFields=$obj->__getFields();
        if(!$remFields)
            return;

        foreach($remFields as $key=>$value) {
            $types=$value->getTypes();
            foreach($types as $tKey=>$tValue) {
                $this->parameters[$tKey] = $tValue->getValue();
            }
        }
    }

    function getPagingParameters()
    {
        //Do not use pagingParameters, if needed use it in the calling method as normal parameters
    }

    function fetchAll()
    {
        $def = $this->getOriginalDefinition();
        $definition = $def['STORAGE']['Dictionary']['DEFINITION'];

        if (!isset($definition['MODEL']) || !isset($definition['METHOD'])) {
            throw new \lib\datasource\DataSourceException(\lib\datasource\DataSourceException::ERR_NO_MODEL_OR_METHOD);
        }

        $mdl = \getModel($definition['MODEL']);
        $method = $definition['METHOD'];
        $params = $this->parameters;
        $data = $mdl->{$method}($params);

        foreach($data as $key=>$value) {
            $j = 0;
            foreach($def['FIELDS'] as $fieldName=>$fieldDef) {
                $this->data[$key][$fieldName] = $value[$j];
                $j++;
            }
        }
        $this->nRows = count($this->data);

        $this->iterator=new \lib\model\types\DataSet(array("FIELDS"=>$def['FIELDS']),$this->data,$this->nRows,$this->nRows,$this,array());
        return $this->iterator;
    }

    function count()
    {
        return $this->nRows;
    }

    function getStartingRow()
    {
        return 0;
    }
}

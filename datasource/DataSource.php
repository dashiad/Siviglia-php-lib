<?php
namespace lib\datasource;
class DataSourceException extends \lib\model\BaseException
{
    const ERR_DATASOURCE_IS_GROUPED=5;
    const ERR_NO_SUCH_DATASOURCE=1;
    const ERR_INVALID_DATASOURCE_PARAM=2;
    const ERR_PARAM_REQUIRED=3;
    const ERR_UNKNOWN_CHILD_DATASOURCE=4;
    const ERR_NO_MODEL_OR_METHOD=5;
}
abstract class DataSource extends \lib\model\BaseTypedObject
{
        
    protected $isLoaded=false;
    protected $__returnedFields;
    protected $objName;
    protected $dsName;
    protected $originalDefinition=null;

    function __construct($objName,$dsName,$definition)
    {
        $this->originalDefinition=$definition;
        $localFields=array_merge(isset($definition["INDEXFIELDS"])?$definition["INDEXFIELDS"]:array(),
            isset($definition["PARAMS"])?$definition["PARAMS"]:array());

        foreach($this->originalDefinition["PARAMS"] as $key=>& $value)
        {
            if(!isset($value["DEFAULT"]))
                $value["DEFAULT"]=null;
        }

        $this->__returnedFields=$definition["FIELDS"];
        $definition["FIELDS"]=$localFields;
        parent::__construct($definition);
        $this->objName=$objName;
        $this->dsName=$dsName;
    }
    abstract function fetchAll();
    abstract function getIterator($rowInfo=null);
    function getName()
    {
        return $this->dsName;
    }
    function getObjectName()
    {
        return $this->objName;
    }
    function count()
    {
        $it=$this->getIterator();
        return $it->count();
    }
    function isLoaded()
    {
        return $this->isLoaded;
    }
    function setParameters($obj)
    {
        $remFields=$obj->__getFields();
        foreach($remFields as $key=>$value)
        {
            if(!isset($this->__fieldDef[$key]))
                continue;
            $types=$value->getTypes();
            foreach($types as $tKey=>$tValue)
            {
                try{
                    $field=$this->__getField($tKey);
                    $field->copyField($tValue);
                }
                catch(\lib\model\BaseTypedException $e)
                {
                    if($e->getCode()==\lib\model\BaseTypedException::ERR_NOT_A_FIELD)
                    {
                        // El campo no existe.No se copia, pero se continua.
                        continue;
                    } // En cualquier otro caso, excepcion.
                    else
                        throw $e;
                }
            }
        }
    }
    function getStartingRow()
    {
        return 0;
    }
    function getPagingParameters()
    {
        return null;
    }
}

abstract class TableDataSource extends DataSource {
    abstract function countColumns();
    abstract function getMetaData();
}


?>

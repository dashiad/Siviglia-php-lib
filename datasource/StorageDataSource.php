<?php
namespace lib\datasource;
include_once(LIBPATH."/datasource/DataSource.php");
include_once(LIBPATH."/datasource/DataSourceFactory.php");
abstract class StorageDataSource extends TableDataSource
{
    protected $serializerDefinition;
    protected $parameters;
    protected $parentDs;
    protected $joinBy;
    protected $joinType;
    protected $childDataSources;
    protected $serializer;
    protected $serializerType;

    protected $enabledFilters=array();
    protected $iterator=null;
    protected $mapField=null;
    protected $parentField=null;
    protected $pagingParameters=null;
    //protected $paramsModel;

    protected $data;

    protected $grouped=false;
    function __construct($objName,$dsName,$definition,$serializer)
    {
        parent::__construct($objName,$dsName,$definition);
        // Se hace un merge de indices y params.Esto deberia ser cambiado.

        $pagingParams=array(
            "__start"=>array("TYPE"=>"Integer"),
            "__count"=>array("TYPE"=>"Integer"),
            "__sort"=>array("TYPE"=>"String"),
            "__sortDir"=>array("TYPE"=>"Enum","VALUES"=>array("ASC","DESC"),"DEFAULT"=>"ASC"),
            "__sort1"=>array("TYPE"=>"String"),
            "__sortDir1"=>array("TYPE"=>"Enum","VALUES"=>array("ASC","DESC"),"DEFAULT"=>"ASC"),
            "__group"=>array("TYPE"=>"String","MAXLENGTH"=>30),
            "__groupParam"=>array("TYPE"=>"String","MAXLENGTH"=>30),
            "__groupMin"=>array("TYPE"=>"String","MAXLENGTH"=>30),
            "__groupMax"=>array("TYPE"=>"String","MAXLENGTH"=>30),
            "__accumulated"=>array("TYPE"=>"Boolean"),
            "__partialAccumul"=>array("TYPE"=>"Boolean"),
            "__autoInclude"=>array("TYPE"=>"String")

        );
        if(!isset($this->originalDefinition["PARAMS"]))
            $this->originalDefinition["PARAMS"]=$pagingParams;
        else
            $this->originalDefinition["PARAMS"]=array_merge_recursive($this->originalDefinition["PARAMS"],$pagingParams);

        foreach($this->originalDefinition["PARAMS"] as $key=>& $value)
        {
            if(!isset($value["DEFAULT"]))
                $value["DEFAULT"]=null;
        }
        $this->pagingParameters=new \lib\model\BaseTypedObject(array(
            "FIELDS"=>$pagingParams
        ));
        // Se necesita el serializador propio para el objeto que nos han pasado, y no el serializador que
        // hemos recibido como parametro, ya que el objeto padre podria ser "web", y esta accediendo a un 
        // datasource de un objeto de tipo "app".
        if(!$serializer)
        {
            $serializer=$this->getSerializer();
        }
        else
            $this->serializer=$serializer;
        $this->serializerType=$this->serializer->getSerializerType();
        //if(\Registry::$registry["currentPage"])
        //    $this->setParameters(\Registry::$registry["currentPage"]);
    }

    function initialize(& $parentDs=null,$joinBy=null,$parentField=null,$joinType=null)
    {                    
        $this->parentDs=$parentDs;
        //$this->joinBy=$joinBy;
        if($joinBy!=null)
        {
            $k=array_keys($joinBy);
            $this->mapField=$k[0];
            if(isset($this->__objectDef["INDEXFIELDS"][$parentField]["MAPS_TO"]))
                $this->mapField=$this->__objectDef["INDEXFIELDS"][$parentField]["MAPS_TO"];
            $this->parentField=$parentField;
        }
        $this->joinType=$joinType;
    //    $this->validate();
    }
    function getStartingRow()
    {
        $f=$this->pagingParameters->__getField("__start");
        if($f->hasOwnValue())
            return $f->get();
        return 0;
    }
    function getOriginalDefinition()
    {
        return $this->originalDefinition;
    }
    function getPagingParameters()
    {
        return $this->pagingParameters;
    }
    function setParameters($obj)
    {
        $remFields=$obj->__getFields();
        $pagingFields=$this->pagingParameters->__getFields();
        $pagingKeys=array_keys($pagingFields);
        if(!$remFields)
            return;
        foreach($remFields as $key=>$value)
        {
            if(in_array($key,$pagingKeys))
            {
                $types=$value->getTypes();

                foreach($types as $tKey=>$tValue)
                {
                    $this->pagingParameters->__getField($key)->copyField($tValue);
                }
                continue;
            }
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

    function getSerializer()
    {
        if($this->serializer)
            return $this->serializer;

        if($this->__objectDef["SERIALIZER"])
        {
            if(is_array($this->__objectDef["SERIALIZER"]))
                $this->serializer=\lib\storage\StorageFactory::getSerializer($this->__objectDef["SERIALIZER"]);
            else
                $this->serializer=\lib\storage\StorageFactory::getSerializerByName($this->__objectDef["SERIALIZER"]);
        }
        else
            $this->serializer= \lib\storage\StorageFactory::getDefaultSerializer($this->objName);
        return $this->serializer;
    }
    

    function fetchAll()
    {
        if($this->pagingParameters->{"*__accumulated"}->hasOwnValue() || $this->pagingParameters->{"*__group"}->hasOwnValue())
        {
            $group=$this->pagingParameters->__group;
            if(!$group)
                return $this->fetchGrouped();
            return $this->fetchGrouped($group,$this->pagingParameters->__groupParam);
        }
        return $this->doFetch();
    }
    abstract function doFetch();

    function getSubDataSources()
    {
        if(isset($this->__objectDef["INCLUDE"]))
        {
            return array_keys($this->__objectDef["INCLUDE"]);
        }
    }
    function getSubDataSourceInstance($name)
    {
        $include=$this->__objectDef["INCLUDE"][$name];
        if(!$include)
        {
            throw new DataSourceException(DataSourceException::ERR_UNKNOWN_CHILD_DATASOURCE,array("object"=>$this->objName,
                "ds"=>$this->dsName,
                "subDs"=>$name));
        }
        return DataSourceFactory::getDataSource($include["OBJECT"],$include["DATASOURCE"]);
    }
    function getSubDataSource($name)
    {
        if($this->grouped)
            throw new \lib\datasource\DataSourceException(\lib\datasource\DataSourceException::ERR_DATASOURCE_IS_GROUPED);
        $include=$this->__objectDef["INCLUDE"][$name];
        $subDs=$this->getSubDataSourceInstance($name);
                        
        // TODO : El siguiente codigo solo permite 1 campo JOIN.Se nota en que la variable parentField, que es la que se le va a 
        // pasar al subdatasource, es sobreescrita por cada paso por el bucle.
        // Tambien hay que fijarse en que los campos por los que se hace join, son columnas, pero sus valores se estan asignando a
        // parametros del datasource hijo, por lo que los nombres deben coincidir.

        $nJoins=0;
        foreach($include["JOIN"] as $key=>$value)
        {
            $field=$subDs->__getField($key);
            $parentField=$value;
            $arrayType=array("TYPE"=>"ArrayType","ELEMENTS"=>$field->getDefinition());
            $newField=$subDs->__addField($key,$arrayType);
            $val=$this->iterator->getColumn($value);
            if(!(count($val)==1 && $val[0]==null))
            {
                $newField->setValue($this->iterator->getColumn($value));
                $nJoins++;
            }
        }

        $subDs->initialize($this,$include["JOIN"],$parentField,$include["JOINTYPE"]);
        if($nJoins == 0)
        {
            $subDs->setEmpty();
        }
        if(isset($include["CONDITIONS"]))
        {
            $subDs->addConditions($include["CONDITIONS"]);
        }
        if(isset($include["SORT"]))
        {
            $subDs->sortBy($include["SORT"]["FIELD"],isset($include["SORT"]["DIRECTION"])?$include["SORT"]["DIRECTION"]:'ASC');
        }
        return $subDs;       
    }
    // Se sobreescribe este metodo de BaseTypedObject
    function isRequired($fieldName)
    {
        $fieldDef=$this->__getField($fieldName)->getDefinition();
        return isset($fieldDef["REQUIRED"])?$fieldDef["REQUIRED"]:false;
    }
    function __set($varName,$value)
    {
        try
        {
            parent::__set($varName,$value);
        }catch(\Exception $e)
        {
            $this->pagingParameters->{$varName}=$value;
        }
    }
    function sortBy($sortField,$sortDirection="ASC")
    {
        $this->pagingParameters->__sort=$sortField;
        $this->pagingParameters->__sortDir=$sortDirection;
    }


    abstract function addConditions($conds);
}

?>

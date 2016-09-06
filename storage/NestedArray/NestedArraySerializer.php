<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 10/08/15
 * Time: 9:50
 */

namespace platform\storage\NestedArray;
class NesteArraySerializerException extends \lib\model\BaseModelException{
    const ERR_NO_SUCH_OBJECT = 4;
    const CANT_SERIALIZE_OBJECT=5;

}

class NestedArraySerializer extends \lib\storage\StorageSerializer {
    var $fieldMapping;
    var $isArray;
    var $source;
    var $indexable;
    var $positionalIds;
    var $indexParam;
    var $hashFields;
    var $hashedIds;
    var $value;
    function __construct($definition,$type=null)
    {
        $this->targetModel=$definition["MODEL"];

        $varParams=array(
                "indexable"=>"INDEXABLE",
                "positionalIds"=>"POSITIONAL_IDS",
                "hashedIds"=>"HASHED_IDS",

        );

        foreach($varParams as $key=>$value)
            $this->{$key}=(isset($definition["VALUE"]) && !empty($definition["VALUE"]));

        if($this->indexable)
            $this->indexParam=$definition["INDEXPARAM"];

        if($this->hashedIds)
            $this->hashFields=$definition["HASH_FIELDS"];
        if(isset($definition["FIELDMAP"]))
            $this->fieldMapping=$definition["FIELDMAP"];
        parent::__construct($definition,$type?$type:"NESTEDARRAY");
    }
    function destroyStorage($obj){}
    function useDataSpace($ds){}
    function existsDataSpace($ds){return true;}
    function destroyDataSpace($spaceDef){return true;}
    function createDataSpace($spaceDef){return true;}
    function subLoad($definition,& $relationColumn){
        $position=$definition["STARTINGROW"];
        $objectName = $relationColumn->getRemoteObject();
        $model=$relationColumn->getLocalModel();
        $remoteModel=\getModel($objectName);
        $name=$relationColumn->getName();
        if($this->fieldMapping)
        {
            if(isset($this->fieldMapping[$name]))
                $name=$this->fieldMapping[$name];
        }
        $serializerInfo=$model->__getSerializerFilters($this->getSerializerType());
        if(!$serializerInfo)
        {
            $source=& $serializerInfo["source"];
            $definition=& $serializerInfo["definition"];
        }
        else
        {
            $source=& $this->data;
            $definition=isset($this->definition[$name])?$this->definition[$name]:array();
            $model->__setSerializerFilters($this->getSerializerType(),array("source"=>& $source,"definition"=>$definition,"root"=>$this->value));
        }
        if(!isset($source[$position]))
        {
            throw new NesteArraySerializerException(NesteArraySerializerException::ERR_NO_SUCH_OBJECT,array("position"=>$position));
        }
        return array($this->unserialize($remoteModel,array("source"=>& $source[$position],"definition"=>$definition)));

    }
    function _store($obj,$isNew,$dirtyFields)
    {
        $objName=$obj->__getObjectNameObj();
        $localName=new \lib\reflection\model\ObjectDefinition($this->definition["MODEL"]);
        if($objName->getNormalizedName()!=$localName->getNormalizedName())
        {
            throw new NesteArraySerializerException(NesteArraySerializerException::CANT_SERIALIZE_OBJECT,array("requires"=>$localName->getNormalizedName(),"got"=>$objName->getNormalizedName()));
        }

    }
    function count($defintion,&$model)
    {

    }
    function createStorage($modelDef,$extraDef=null){return true;}
    function unserialize($object,$queryDef=null,$filterValues=null){
        // Se obtienen todas aquellas propiedades que NO sean a su vez un array
        $result=array();
        $definition=$queryDef["definition"];
        $map=$definition["FIELDMAP"];
        foreach($queryDef["source"] as $key=>$value)
        {
            if(is_array($value))
            {
                if($key==="@attributes")
                {
                    foreach($value as $attK=>$attV)
                    {
                        $fName=$attK;
                        if(isset($map["@attributes"][$attK]))
                            $fName=$map["@attributes"][$attK];
                        $result[$fName]=$attV;
                    }
                }
                continue;
            }
            $result[$key]=$value;
        }
        $fieldList = $object->__getFields();
        $type=$this->getSerializerType();
        foreach ($fieldList as $key => $value)
        {
            $value->unserialize($result,$type);
        }
    }
} 
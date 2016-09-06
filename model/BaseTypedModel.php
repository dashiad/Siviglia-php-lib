<?php

namespace lib\model;

class BaseTypedModel extends MultipleModel
{
    protected $typeField;
    protected $typeInstance;
    function __construct($serializer = null, $definition = null)
    {
        parent::__construct($serializer,$definition);
        $this->typeField=$this->__objectDef["TYPEFIELD"];
        $this->__fieldDef[$this->typeField]["REQUIRED"]=true;
        
    }
    function __getRelatedModel()
    {
        if($this->relatedModel)
            return $this->relatedModel;

        $field=BaseModel::__getField($this->typeField);
        if(!$field->is_set())        
            throw new BaseModelException(BaseModelException::ERR_CANT_LOAD_EMPTY_OBJECT);
        $value=$field->getType()->getLabel();
        // TODO: Lanzar excepciones.
        $this->__setRelatedModelName($value);
        return parent::__getRelatedModel();
    }
    function setModelType($type)
    {
        $this->{$this->typeField}=$type;        
    }
    function & __getFieldDefinition($fieldName)
    {
            if(isset($this->__fieldDef[$fieldName]))
                return $this->__fieldDef[$fieldName];
            else
            {        
                if ($this->__aliasDef && isset($this->__aliasDef[$fieldName]))
                    return $this->__aliasDef[$fieldName];
            }            
            include_once(PROJECTPATH."/lib/model/BaseModel.php");
            throw new BaseModelException(BaseModelException::ERR_NOT_A_FIELD,array("name"=>$varName));            
   }

    /*function __getSerializer()
    {
        if (!$this->__serializer)
        {
            global $SERIALIZERS;
            $layer = $this->__objName->layer;
            $serializer = \lib\storage\StorageFactory::getSerializer($SERIALIZERS[$layer]);
            $serializer->useDataSpace($SERIALIZERS[$layer]["ADDRESS"]["database"]["NAME"]);
            $this->__serializer = $serializer;
        }

        if (!$this->__serializer)
            throw new BaseModelException(BaseModelException::ERR_NO_SERIALIZER);
        return $this->__serializer;
    }
    function __setSerializerFilters($serType,$data)
    {
        $this->__filters[$serType]=$data;
    }
    function __getSerializerFilters($serType,$data)
    {
        return $this->__filters[$serType];
    }*/
            
}


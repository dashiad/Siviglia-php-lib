<?php

namespace lib\model;

class RecursiveRelation extends ModelBaseRelation
{

    // Normaliza el formato de relacion de campos.
    // El formato es <campo remoto> => <campo local>

    function __construct($name, & $model, $definition, $value = null)
    {
        ModelBaseRelation::__construct($name, $model, $definition, $value);
    }

    function createRelationValues()
    {
        return new RelationValues($this, $this->definition["LOAD"] ? $this->definition["LOAD"] : "LAZY");
    }
    function getRelationValues()
    {
        return $this->relationValues;
    }

    function set($value)
    {        
        $this->relation->set($value);
        $this->relationValues->reset();
        if (is_subclass_of($value, "\\lib\\model\\BaseModel"))
        {
            $this->relationValues->load(array($value), 1);
            $dummy = $this->relationValues[0]; // Se marca como accedido
        }
    }

    function get()
    {
        return $this;
    }

    function __get($varName)
    {
        return $this->relationValues[0]->{$varName};
    }

    function __set($varName, $value)
    {
        return $this->relationValues[0]->{$varName} = $value;
    }

    function save()
    {
        $nSaved = $this->relationValues->save();        
        if ($nSaved == 1)
        {
            $this->relation->setFromModel($this->relationValues[0]);            
        }
    }

    function count()
    {
        return $this->relationValues->count();
    }

    function loadCount()
    {

        if ($this->relationValues->isLoaded())
            return $this->relationValues->count();
        if ($this->relation->state == ModelBaseRelation::UN_SET)
            return 0;
        if ($this->definition["LOAD"] == "LAZY")
            $this->relationValues->setCount($this->getSerializer()->count($this->getRelationQueryConditions(), $this->model));

        else
            $this->loadRemote();
    }

    function __toString()
    {
        return $this->relation->__toString();
    }

    function onModelSaved()
    {
        $this->relation->cleanState();
    }

    function isDirty()
    {
        if ($this->relation->isDirty())
        {
            return true;
        }

        if ($this->relationValues->isLoaded())
        {
            return $this->relationValues->isDirty();
        }
        return false;
    }

    function serialize($serializer)
    {

        return $this->relation->serialize($serializer);

    }

    function copyField($type)
    {    
        $this->relation->copyField($type);
        
     }
}


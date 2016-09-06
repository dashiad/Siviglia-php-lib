<?php
namespace lib\reflection\base;
class SimpleModelDefinition extends ClassFileGenerator
{
    var $fields=array();
    var $hasDefinition=false;
    var $baseDir;        
    var $config;
    var $definition;
        
    function load()
    {
        $this->initialize();
    }
    function initialize($definition=null)
    {
        $this->hasDefinition=false;
        if(!$definition)
        {
            if(!is_file($this->filePath))
                return;
            include_once($this->filePath);
            $className=$this->getNamespaced(); 

            $definition=$className::$definition;
            $this->definition=$definition;
        }        
        if(!$definition)
            return;
        
        
        $this->definition=$definition;
        $this->hasDefinition=true;
        $this->indexFields=$this->definition["INDEXFIELDS"];
        if(isset($this->definition["PARAMS"]))
            $this->loadParams();
        $this->loadFields();        
    }

    function hasDefinition()
    {
        return $this->hasDefinition;
    }
    function hasParams()
    {
        return isset($this->definition["PARAMS"]) && count(array_keys($this->definition["PARAMS"]))>0;
    }
    function loadParams()
    {
        foreach($this->definition["PARAMS"] as $key=>$value)
            $this->params[$key]=\lib\reflection\model\types\TypeReflectionFactory::getReflectionType($value);        
    }
        
    function getLabel()
    {
        $def=$this->getDefinition();
        return isset($def["LABEL"])?$def["LABEL"]:$this->className;
    }

    function loadFields()
    {            
            $this->fields=array();
            if(!$this->definition["FIELDS"])
                return;
                            
            foreach($this->definition["FIELDS"] as $key=>$value)
            {
                if($value["TYPE"]=="Relationship")
                    $this->fields[$key]=new \lib\reflection\model\RelationshipDefinition($key,$this,$value);
                else
                    $this->fields[$key]=new \lib\reflection\model\FieldDefinition($key,$this,$value);           
            }
     }

    function getDefinition()
    {
        return $this->definition;
    }

    function getField($name)
    {
        if(isset($this->fields[$name]))
            return $this->fields[$name];
        // Se mira si es un campo que atraviesa relaciones.
        $parts=explode("/",$name);
        if($parts[0]=="")
            array_splice($parts,0,1);
        $curField=array_splice($parts,0,1);
        if(!isset($this->fields[$curField[0]]))
        {
//            var_dump($this);
            return null; // Podria ser un alias.Esto es capturado en ModelDefinition
           // die("Campo $name desconocido en ".$this->name);
        }
        if($this->fields[$curField[0]]->isRelation())
        {
            return $this->fields[$curField[0]]->getRelated(implode("/",$parts));
        }
        else
            die("No es posible resolver el path $name en el objeto ".$this->name);

    }
    function getFields()
    {
        return $this->fields;
    }
    function addField($name,$fieldInstance)
    {
        $this->fields[$name]=$fieldInstance;
    }

    function getIndexFields()
    {
        if(!is_array($this->indexFields))
            return array($this->indexFields=>$this->fields[$this->indexFields]);
        $results=array();        
        foreach($this->indexFields as $key=>$value)
        {
            
            $results[$value]=$this->fields[$value];
        }
        return $results;
    }

    function __getRequiredFields($required)
    {
        $indexf=array_keys($this->getIndexFields());
        foreach($this->fields as $key=>$value)
        {
            // Las keys no se consideran campos "requeridos" u "opcionales"
            if(in_array($key,$indexf))
                    continue;
            if($value->isRequired()==$required)
                $results[$key]=$value;
        }
        return $results;
    }

    function getRequiredFields()
    {
        return $this->__getRequiredFields(true);
    }

    function getOptionalFields()
    {
        return $this->__getRequiredFields(false);
    }

}

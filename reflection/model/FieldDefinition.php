<?php
namespace lib\reflection\model;
class FieldDefinition extends ModelComponent
{
    var $targetRelation="";
        function __construct($name,$parentModel,$definition)
        {
            parent::__construct($name,$parentModel,$definition);              
            $this->shortlabel=$definition["SHORTLABEL"]?$definition["SHORTLABEL"]:$name;
            $this->label=$definition["LABEL"]?$definition["LABEL"]:$name;                                             
            $this->type=$this->createType($this->definition);
            if(isset($definition["TARGET_RELATION"]))
                $this->targetRelation=$definition["TARGET_RELATION"];
        }        
        function isDescriptive()
        {
            return $this->definition["DESCRIPTIVE"]==true;
        }
        function isLabel()
        {
            return $this->definition["ISLABEL"]===true ||$this->definition["ISLABEL"]==="true"||$this->definition["ISLABEL"]===1;
        }
        function isState()
        {
            return $this->definition["TYPE"]=="State";
        }
        function setStates($states)
        {
            $this->definition["VALUES"]=$states;
        }
        function isRelation($definition=null)
        {
            if(!$definition)
                $definition=$this->definition;
            return $definition["TYPE"]=="Relationship";
        }
        function isUnique()
        {
            return $this->definition["UNIQUE"];
        }

        static function createField($name,$parentModel,$definition=null)
        {
           return new FieldDefinition($name,$parentModel,$definition);            
        }

		function getType()
		{
			return array($this->name=>\lib\reflection\model\types\TypeReflectionFactory::getReflectionType($this->definition));
		}
        function getRawType()
        {
            $type=$this->getType();
            foreach($type as $key=>$value)
            {
                $res[$key]=\lib\model\types\TypeFactory::getType(null,$value->getDefinition());
            }
            return $res;
        }

        function isAlias()
        {
            return false;
        }
        function isSearchable()
        {
             if(!isset($this->definition["SEARCHABLE"]))
                 return false;
            return $this->definition["SEARCHABLE"];
        }

        function getTypeSerializer($serializerType)
        {
            $def=array($this->name=>\lib\model\types\TypeFactory::getSerializer($definition["TYPE"],$serializerType));
            return $def;
        }

        function getDefaultInputName($definition=null)
        {
            $fullclass=get_class($this->type->getInstance());
            $parts=explode('\\',$fullclass);
            $className=$parts[count($parts)-1];
            return $className;
        }

        function getDefinition()
        {
            $rawT=$this->getRawType();
            $fieldNames=array_keys($rawT);
            $def=$rawT[$fieldNames[0]]->getDefinition();
            if($this->isRequired())
                $def["REQUIRED"]=true;            
            $def["LABEL"]=$this->label;
            $def["SHORTLABEL"]=$this->shortlabel;
            $def["DESCRIPTIVE"]=$this->isDescriptive()?"true":"false";
            $def["ISLABEL"]=$this->isLabel()?"true":"false";
            $targetRelation=$this->getTargetRelation();
            if($targetRelation!="")
                $def["TARGET_RELATION"]=$targetRelation;
            if($this->definition["UNIQUE"])
                $this->definition["UNIQUE"]="true";
            return $def;
        }
        function getRawDefinition()
        {
            return $this->definition;
        }
        function getTargetRelation()
        {
            return $this->targetRelation;
        }

        
}
 

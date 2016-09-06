<?php
namespace lib\reflection\classes;
class FieldDefinition extends BaseDefinition
{
        function __construct($name,$parentModel,$definition)
        {

               $this->name=$name;
               $this->shortlabel=$definition["SHORTLABEL"]?$definition["SHORTLABEL"]:$name;
               $this->label=$definition["LABEL"]?$definition["LABEL"]:$name;
               
               $this->definition=$definition;
               $this->parentModel=$parentModel;
               $this->required=$definition["REQUIRED"];
               $this->type=$this->createType($parentModel,$definition["TYPE"],"types",$definition);
        }
        function isRequired()
        {
                return $this->definition["REQUIRED"];
        }
        function isDescriptive()
        {
            return $this->definition["DESCRIPTIVE"]==true;
        }
        function isLabel()
        {
            return $this->definition["ISLABEL"]==true;
        }
        function isState()
        {
            return $this->definition["TYPE"]=="State";
        }
        function isRelation($definition=null)
        {
            if(!$definition)
                $definition=$this->definition;
            return $definition["TYPE"]=="Relationship";
        }
        static function createField($name,$parentModel,$definition=null)
        {
           return new FieldDefinition($name,$parentModel,$definition);
            
        }
		function getType()
		{
			return array($this->name=>\lib\model\types\TypeFactory::getType($this->getDefinition()));
		}
        function getTypeSerializer($serializerType)
        {
            $def=array($this->name=>\lib\model\types\TypeFactory::getSerializer($definition["TYPE"],$serializerType));
            return $def;
        }
        function getDefinition()
        {
            $def=$this->type->getDefinition();
            if($this->isRequired())
                $def["REQUIRED"]=true;            
            $def["LABEL"]=$this->label;
            $def["SHORTLABEL"]=$this->shortlabel;
            $def["DESCRIPTIVE"]=$this->isDescriptive()?"true":"false";
            $def["ISLABEL"]=$this->isLabel()?"true":"false";
            return $def;
        }
        function getLabel()
        {
            return $this->label;
        }
        function getShortLabel()
        {
            return $this->shortLabel;
        }
}
 

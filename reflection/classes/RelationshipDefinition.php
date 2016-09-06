<?php
namespace lib\reflection\classes;
class RelationshipDefinition
{
    var $fields;
        function __construct($name,$parentModel,$definition)
        {
            if(is_string($definition))
                debug_trace();
            $definition["MULTIPLICITY"]=$definition["MULTIPLICITY"]?$definition["MULTIPLICITY"]:"1:N";
            $this->name=$name;
               $this->definition=$definition;
               $this->parentModel=$parentModel;
               $this->required=$definition["REQUIRED"];
               $this->targetObject=$definition["OBJECT"];
               $this->fields=$definition["FIELD"]?$definition["FIELD"]:$definition["FIELDS"];
        }
        function isRequired()
        {
                return $this->definition["REQUIRED"];
        }
        function isRelation($definition=null)
        {
            return true;
        }
        function isState()
        {
            return false;
        }
        function isDescriptive()
        {
            return $this->definition["DESCRIPTIVE"]==true;
        }
        function isLabel()
        {
            return $this->definition["ISLABEL"]==true;
        }
        function getRelation()
        {
            return $this->fields;
        }
        function isLocalMultiple()
        {
            $mult=$this->definition["MULTIPLICITY"];
            if($mult=="1:N" || $mult=="M:N")
                return true;
            return false;
        }
        function isRemoteMultiple()
        {
            $mult=$this->definition["MULTIPLICITY"];
            if($mult=="1:N" || $mult=="M:N")
                return true;
            return false;
        }
        static function createRelation($name,$parentModel,$targetObject,$targetField,$relationName=null)
        {
            
            $def["TYPE"]="Relationship";

            if($relationName==null)
                $relationName=$targetObject;

            if(is_array($targetField))
            {
                if(\lib\php\ArrayTools::isAssociative($targetField))
                {
                    $def["FIELDS"]=$targetField;
                }
                else
                {
                    foreach($targetField as $key=>$value)
                        $def["FIELDS"][$relationName."_".$value]=$value;
                }
            }

            $objNameClass=new \lib\reflection\classes\ObjectDefinition($targetObject);
            $objLayer=$objName->layer;
            $objName=$objName->className;
            $def["OBJECT"]=$objNameClass->getNamespaced("compiled");            
            return new RelationshipDefinition($name,$parentModel,$def);

        }

        function getDefinition()
        {
             return $this->definition;
        }
        function getTargetObject()
        {
            return $this->definition["OBJECT"];
        }
        
		function getType()
		{
			if(!is_array($this->fields))
			{
				$fDef=array($this->name=>$this->fields);
			}
			else
				$fDef=$this->fields;
			
			$targetObj=new ObjectDefinition($this->getTargetObject());
			
			foreach($fDef as $key=>$value)
            {         				
                $results[$key]=\lib\model\types\TypeFactory::getRelationFieldTypeInstance($targetObj->className,$value);                
            }
			return $results;
		}
				
}
 

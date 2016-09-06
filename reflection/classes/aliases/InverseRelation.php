<?php
  namespace lib\reflection\classes\aliases;
  class InverseRelation
  {
        function __construct($parentModel,$definition)
        {
                $this->definition=$definition;
        }
        function pointsTo($objName,$fieldName)
        {
            $o2=new \lib\reflection\classes\ObjectDefinition($this->definition["OBJECT"]);
            if($o2->className==$objName &&
               $this->definition["FIELD"]==$fieldName)
                return true;
            return false;
        }
        function createInverseRelation($parentModel,$targetObject,$relName)
        {
            return new InverseRelation(parentModel,array(
                "TYPE"=>"InverseRelation",
                "OBJECT"=>$targetObject,
                "FIELD"=>$relName));
        }
        function getDefinition()
        {
            return $this->definition;
        }
        function isRelation()
        {
            // Aunque este objeto representa una relacion, no debe ser tenida como tal 
            return false;
        }
  }
  

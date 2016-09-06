<?php
namespace lib\reflection\classes;
class StateRequirementDefinition
{
   var $stateList;
   function __construct($definition,$subInstanceClass)
   {
        foreach($definition as $key=>$value)
        {
                $this->stateList[$key]=new $subInstanceClass($value);
        }
   }
   function getDefinition()
   {
        $res=array();
        foreach($this->stateList as $key=>$value)
        {
                $res["STATES"][$key]=$value->getDefinition();
        }
        return $res;
   }
   function getObjectForState($state)
   {
       return $this->stateList($state);
   }
}

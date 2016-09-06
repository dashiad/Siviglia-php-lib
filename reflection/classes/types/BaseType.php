<?php
namespace lib\reflection\classes\types;
class BaseType
{
        function __construct($definition)
        {
            
            if(is_object($definition))
            {
                debug_trace();
                exit();
            }
             $this->definition=$definition;
        }
        function setTypeName($typeName)
        {
            $this->typeName=$typeName;
        }
        function getDefinition()
        {
            if($this->definition)
            {
                $def=$this->definition;
                $def["TYPE"]=$this->typeName;
            }
            else
            {
                $def=array("TYPE"=>$this->typeName);
            }
            return $def;
        }
        function getInstance()
        {
            return \lib\model\types\TypeFactory::getType($this->definition);
        }
}

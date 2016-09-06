<?php
namespace lib\reflection\model\types;
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
                if(!$def["TYPE"])
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
            return \lib\model\types\TypeFactory::getType(null,$this->definition);
        }
        function setName($name)
        {
            $this->definition["NAME"]=$name;
        }
        function isEditable()
        {
            return true;
        }
    function getDefaultInputName()
    {
        $type=$this->getInstance();
        // Se obtiene el tipo basico de este campo
        $fullclass=get_class($type);
        $parts=explode('\\',$fullclass);
        $className=$parts[count($parts)-1];
        return "/types/inputs/".$className."Input";
    }
    function getTypeErrors()
    {
        $type=$this->getInstance();

        // Se da prioridad a las constantes definidas en las clases base.
        $typeList=array_flip(array_merge(array(get_class($type)),array_values(class_parents($type))));

        $usedErrors=array();
        $errors=array();
        foreach($typeList as $key=>$value)
        {
            $parts=explode("\\",$value);
            $className=$parts[count($parts)-1];

            $exceptionClass=$value."Exception";
            if( !class_exists($exceptionClass) )
                continue;
            return $exceptionClass::getPrintableErrors();
        }
        return $errors;
    }
}

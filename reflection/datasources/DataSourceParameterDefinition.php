<?php
namespace lib\reflection\datasources;
class DataSourceParameterDefinition
{
        function __construct($name,$definition)
        {
            $this->name=$name;
                $this->definition=$definition;
        }
        static function create($name,$typeDef,$triggerVar,$disableIf=null,$enableIf=null)
        {                        
            $def=$typeDef;
                if($triggerVar)
                        $def["TRIGGER_VAR"]=$triggerVar;
                else
                {

                        $def["REQUIRED"]="true";
                }
                if($disableIf)
                        $def["DISABLE_IF"]=$disableIf;
                if($enableIf)
                        $def["ENABLE_IF"]=$enableIf;
                return new DataSourceParameterDefinition($name,$def);
        }
        function getDefinition()
        {
                return $this->definition;
        }
}


?>

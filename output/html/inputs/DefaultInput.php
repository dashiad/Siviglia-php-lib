<?php
namespace lib\output\html\inputs;
class DefaultInput
{
        var $value;
        var $name;
        var $isSet;
        function __construct($name,$fieldDef,$inputDef)
        {
                $this->name=$name;
                $this->fieldDef=$fieldDef;
                $this->inputDef=$inputDef;
                $this->isSet=false;
        }
        function unserialize($val)
        {            
            if( isset($val) && $val!=="" )
            {
                $this->isSet=true;
                $this->value=$val;
            }
        }
        function is_set()
        {
                return $this->isSet;
        }
        function getValue()
        {
            return $this->value;
        }
}

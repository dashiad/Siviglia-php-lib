<?php
namespace lib\model\types;
class StateType extends EnumType
{
    function __construct(& $definition,$value=null)
    {
        EnumType::__construct($definition,$value);
    }

    function getDefaultState()
    {
        return $this->definition["DEFAULT"];
    }
}

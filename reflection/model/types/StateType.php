<?php
namespace lib\reflection\model\types;
class StateType extends BaseType
{
        function getDefaultState()
        {
            return $this->definition["DEFAULT"];
        }
}

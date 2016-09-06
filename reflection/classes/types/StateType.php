<?php
namespace lib\reflection\classes\types;
class StateType extends BaseType
{
        function getDefaultState()
        {
            return $this->definition["DEFAULT"];
        }
}

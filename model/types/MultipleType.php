<?php
namespace lib\model\types;
class MultipleType extends BaseType
{
    public $deffield;

    function setDeffield($value)
    {
        $this->deffield = $value;
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 16/06/14
 * Time: 23:15
 */
namespace lib\model\types;


class PHPVariableType extends \lib\model\types\BaseType {

    function setValue($val)
    {
        parent::setValue($val);
    }

    function getUnserializedValue()
    {
        if($this->valueSet)
            return unserialize($this->value);
        if($this->hasDefaultValue())
            return unserialize($this->getDefaultValue());
        return null;
    }
}

class PHPVariableTypeHTMLSerializer extends BaseTypeHTMLSerializer
{
    function serialize($type)
    {
        if($type->hasValue())
            return json_encode($type->value);
        return '';
    }
}
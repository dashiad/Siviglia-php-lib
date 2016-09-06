<?php namespace lib\model\types;
class DecimalType extends BaseType
{
    function setValue($val)
    {
        if($val===null || !isset($val))
        {
            $this->valueSet=false;
            $this->value=null;
        }
        else
        {
            $this->valueSet=true;
            $this->value=$val;
        }
    }
}

class DecimalTypeHTMLSerializer extends BaseTypeHTMLSerializer{}

?>
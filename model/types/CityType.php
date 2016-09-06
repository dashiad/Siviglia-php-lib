<?php namespace lib\model\types;
class CityType extends StringType {
    function __construct($definition,$value=null)
    {
		$definition['MINLENGTH']=2;
		$definition['MAXLENGTH']=128;
        StringType::__construct($definition,$value);
    }
}

<?php namespace lib\model\types;
class PhoneType extends StringType {
    function __construct(& $definition,$value)
    {
		$definition['MINLENGTH']=7;
		$definition['MAXLENGTH']=12;
		$definition['REGEXP']='/[0-9\\-]{7,12}/';

        StringType::__construct($definition,$value);
    }

}

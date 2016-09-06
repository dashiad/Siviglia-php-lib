<?php namespace lib\model\types;
class MoneyType extends DecimalType {
    function __construct($definition,$value=null)
    {
		$definition['NINTEGERS']=20;
		$definition['NDECIMALS']=3;
        // Deberia aniadirse currency en la definicion.
        DecimalType::__construct($definition,$value);
    }
    static function getFormatted($value)
    {
        return money_format('%=*(#10.2n', $value);
    }
}

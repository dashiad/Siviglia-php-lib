<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Usuario
 * Date: 30/10/13
 * Time: 14:57
 * To change this template use File | Settings | File Templates.
 */

namespace lib\model\types;


class NIFType extends StringType{
    function __construct(& $definition,$value)
    {
        $definition['TYPE']='NIF';
        $definition['MINLENGTH']=9;
        $definition['MAXLENGTH']=9;
        StringType::__construct($definition,$value);
    }

    function validate($val)
    {
        parent::validate($val);
        $val=strtoupper($val);
        if(substr("TRWAGMYFPDXBNJZSQVHLCKE",strtr(substr($val,0,-1),"XYZ","012")%23,1)!=substr($val,-1))
            throw new \lib\model\types\BaseTypeException(\lib\model\types\BaseTypeException::ERR_INVALID);
        return true;
    }
}

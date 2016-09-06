<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 14/06/14
 * Time: 19:58
 */

namespace lib\action;
include_once(LIBPATH."/model/BaseTypedObject.php");

class DataSourceAction extends \lib\model\BaseTypedObject{
    function __construct($definition)
    {
        $this->originalDefinition=$definition;
        $newDef=$definition;
        $newDef["FIELDS"]=isset($definition["PARAMS"])?$definition["PARAMS"]:array();
        $newDef["FIELDS"]=array_merge($newDef["FIELDS"],$newDef["SOURCE"]);
        parent::__construct($newDef);
    }
} 
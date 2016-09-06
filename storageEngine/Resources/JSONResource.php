<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 31/08/15
 * Time: 9:14
 */

namespace lib\storageEngine\Resources;
use lib\storageEngine\StorageEngineResult;


class JSONResource extends Resource implements ConvertibleToArray{
    function normalizeValue(StorageEngineResult $val)
    {
        return $this->toArray($val->result);
    }
    function toArray($val)
    {
        $d=json_decode($val,true);
        return $d;
    }
    function validateRaw(StorageEngineResult $res)
    {
        return true;
    }
    function isOk()
    {
        return true;
    }
    function toJson()
    {
        return $this->getSourceValue();
    }
}

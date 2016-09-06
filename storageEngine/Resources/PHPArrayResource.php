<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 18/05/2016
 * Time: 11:30
 */

namespace lib\storageEngine\Resources;
use lib\storageEngine;
use lib\storageEngine\StorageEngineResult;


class PHPArrayResource extends Resource implements ConvertibleToArray{
    function normalizeValue(StorageEngineResult $val)
    {
        return $val->result;
    }
    function toArray($val)
    {
        return $val;
    }
    function validateRaw(\lib\storageEngine\StorageEngineResult $res)
    {
        return true;
    }
    function isOk()
    {
        return true;
    }
}
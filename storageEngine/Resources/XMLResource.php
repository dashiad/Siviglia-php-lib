<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 31/08/15
 * Time: 9:13
 */

namespace lib\storageEngine\Resources;
use lib\storageEngine;
use lib\storageEngine\StorageEngineResult;
use lib\storageEngine\Resources\Utils\XMLArrayConverter;

class XMLResource extends Resource implements ConvertibleToArray{
    function normalizeValue(StorageEngineResult $val)
    {
        return $this->toArray($val->result);
    }
    function toArray($val)
    {
        if($val)
        {
            return XMLArrayConverter::createArray($val);
        }
        return null;
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

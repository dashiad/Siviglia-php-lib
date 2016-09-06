<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 31/08/15
 * Time: 9:14
 */

namespace lib\storageEngine\Resources;
use lib\storageEngine\StorageEngineResult;


class HTMLResource extends Resource {
    function normalizeValue(StorageEngineResult $val)
    {
        return $val->result;
    }
    function validateRaw(StorageEngineResult $res)
    {
        return true;
    }
    function isOk()
    {
        return true;
    }
}

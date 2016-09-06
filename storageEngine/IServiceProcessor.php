<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 5/10/15
 * Time: 9:51
 */

namespace lib\storageEngine;
use  lib\storageEngine\Resources\Resource;


interface IServiceProcessor {
    function process(Resource $resource);
} 
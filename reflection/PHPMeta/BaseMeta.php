<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 24/07/15
 * Time: 19:04
 */

namespace lib\reflection\PHPMeta;
include_once(LIBPATH."/php/PHP-Parser/lib/bootstrap.php");


abstract class BaseMeta {
    function __construct($className,$classPath)
    {
        $this->className=$className;
        $this->classPath=$classPath;
    }
    abstract function getSpecification();
    abstract function generateSourceCode();
} 
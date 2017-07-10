<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 07/10/2016
 * Time: 12:37
 */

namespace scLib\data\Cursor;


class CompressedFileCursor  extends ReaderCursor
{
    var $op;
    function __construct($fileName,$endCallback=null)
    {
        $this->op = gzopen($fileName, 'r');
        parent::__construct($endCallback);
    }
    function produce()
    {
        $buffer = gzgets($this->op, 1000000);
        if(!$buffer) {
            gzclose($this->op);
            return false;
        }
        $this->push($buffer);
        return true;
    }
}
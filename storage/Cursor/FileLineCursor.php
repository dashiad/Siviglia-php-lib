<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 07/10/2016
 * Time: 12:37
 */

namespace scLib\data\Cursor;


class FileLineCursor  extends ReaderCursor
{
    var $op;
    function __construct($fileName,$endCallback=null)
    {
        $this->op = fopen($fileName, 'r');
        parent::__construct($endCallback);
    }
    function produce()
    {
        $buffer = fgets($this->op);
        if(!$buffer) {
            fclose($this->op);
            return false;
        }
        $this->push($buffer);
        return true;
    }
}
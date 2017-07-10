<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 28/09/2016
 * Time: 9:42
 */

namespace scLib\data\Cursor;


class ArrayReaderCursor extends ReaderCursor
{
    var $arr;
    var $curIndex;
    var $nRows=0;
    function __construct($arr,$endCallback=null)
    {
        $this->arr=$arr;
        $this->nRows=count($arr);
        $this->curIndex=0;
        parent::__construct($endCallback);
    }
    function produce()
    {
        if($this->curIndex >= $this->nRows)
            return false;
        $this->push($this->arr[$this->curIndex]);
        $this->curIndex++;
        return true;
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 28/09/2016
 * Time: 9:49
 */

namespace scLib\data\Cursor;


class CSVArrayReaderCursor extends ArrayReaderCursor
{
    var $headers;
    function __construct($headers,$rows,$endCallback=null)
    {
        $this->headers=$headers;
        parent::__construct($rows,$endCallback);
    }
    function produce()
    {
        if($this->curIndex < $this->nRows)
            $this->arr[$this->curIndex]=array_combine($this->headers,$this->arr[$this->curIndex]);
        return parent::produce();
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 07/10/2016
 * Time: 13:01
 */

namespace scLib\data\Cursor;


class ChainedReaderCursor extends ReaderCursor
{
    var $producer;
    var $lastCursor;
    function __construct($producer, $lastCursor,$endCallback=null)
    {
        $this->producer=$producer;
        $this->lastCursor=$lastCursor;
        $this->endCallback=$endCallback;
    }
    function produce()
    {
        return $this->producer->produce();
    }
    function addCursor($c)
    {
        $this->lastCursor->addCursor($c);
    }
    function end()
    {
        $this->producer->end();
        if($this->endCallback)
            call_user_func($this->endCallback);
    }
}
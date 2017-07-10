<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 27/09/2016
 * Time: 15:49
 */

namespace scLib\data\Cursor;


class BaseCursor
{
    var $subCursors=array();
    var $endCallback;
    function __construct($endCallback=null)
    {
        $this->endCallback=$endCallback;
    }
    function addCursor($c)
    {
        $this->subCursors[]=$c;
    }
    function setEndCallback($cbk)
    {
        $this->endCallback=$cbk;
    }
    function end()
    {
        if($this->endCallback)
            call_user_func($this->endCallback);

        for($j=0;$j<count($this->subCursors);$j++)
            $this->subCursors[$j]->end();
    }

}
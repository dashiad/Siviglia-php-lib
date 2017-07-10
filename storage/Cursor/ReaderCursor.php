<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 28/09/2016
 * Time: 0:30
 */

namespace scLib\data\Cursor;


abstract class ReaderCursor extends BaseCursor
{
    function __construct($endCallback=null)
    {
        parent::__construct($endCallback);
    }
    function process()
    {
        while($this->produce());
        $this->end();
    }
    abstract function produce();
    function push($row)
    {
        for($j=0;$j<count($this->subCursors);$j++)
            $this->subCursors[$j]->push($row);
    }
    function end()
    {
        for($j=0;$j<count($this->subCursors);$j++)
            $this->subCursors[$j]->end();
        if($this->endCallback)
            call_user_func($this->endCallback);
    }
}
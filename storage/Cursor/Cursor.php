<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 27/09/2016
 * Time: 15:49
 */

namespace scLib\data\Cursor;


class Cursor extends BaseCursor
{
    var $rows=array();
    var $nRows=0;
    var $callback;
    var $currentData;

    function __construct($callback,$nRows=1,$endCallback=null)
    {
        $this->callback=$callback;

        $this->nRows=$nRows;
        if($endCallback)
            $this->setEndCallback($endCallback);
    }
    function push($data)
    {
        $this->currentData[]=$data;
        if($this->nRows==1 || count($this->currentData) >= $this->nRows)
            $this->process();
    }
    function setData($data)
    {
        for($k=0;$k<count($data);$k++)
            $this->push($data[$k]);
        $this->end();
    }
    function process()
    {
        $n=count($this->currentData);
        if($n==0)
            return;
        $newRows = call_user_func($this->callback,$this->currentData);
        for($k=0;$k<count($newRows);$k++)
        {
            for($j=0;$j<count($this->subCursors);$j++)
                $this->subCursors[$j]->push($newRows[$k]);
        }
        $this->currentData=[];
    }
    function end()
    {
        $this->process();
        parent::end();
    }
}
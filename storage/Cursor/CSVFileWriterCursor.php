<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 28/09/2016
 * Time: 0:12
 */

namespace scLib\data\Cursor;


class CSVFileWriterCursor extends Cursor
{
    var $op;
    var $isFirst=true;
    function __construct($fileName,$endCallback=null)
    {
        $this->op=fopen($fileName,"w");
        $v=$this;
        parent::__construct(function($rows) use ($v){
            if ($this->isFirst) {
                fputcsv($v->op, array_keys($rows[0]));
                $v->isFirst = false;
            }
            for($k=0;$k<count($rows);$k++) {
                fputcsv($v->op, array_values($rows[$k]));
            }
            return $rows;
        },1,function() use ($v,$endCallback){fclose($v->op);if($endCallback){call_user_func($endCallback);}});
    }
}
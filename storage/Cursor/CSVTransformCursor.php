<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 28/09/2016
 * Time: 0:24
 */

namespace scLib\data\Cursor;


class CSVTransformCursor extends Cursor
{
    var $op;
    var $isFirst=0;
    var $keys;
    function __construct($processHeadersCallback=null)
    {
        $me=$this;
        $this->headers=null;
        $this->processHeadersCallback=$processHeadersCallback;

        parent::__construct(
            function($line) use ($me) {
                if ($me->headers == null) {
                    $this->headers = str_getcsv($line[0]);
                    if ($me->processHeadersCallback) {
                        $me->headers = call_user_func($me->processHeadersCallback, $me->headers);
                    }
                    return array();
                }
                return array(array_combine($me->headers, str_getcsv($line[0])));
            }
        );
    }
}
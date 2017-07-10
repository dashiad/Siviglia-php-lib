<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 28/06/2017
 * Time: 15:21
 */

namespace lib\storage\Cursor;

class CursorFactory
{
    static function getCursor($definition,$parameters=null,$producer=null)
    {
        $type=$definition["TYPE"];
        $className='\lib\storage\Cursor\\'.$type;
        $curCursor=new $className($definition,$parameters);
        if($producer)
        {

        }


    }
}
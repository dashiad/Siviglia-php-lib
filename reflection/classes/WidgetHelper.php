<?php
namespace lib\reflection\classes;
class WidgetHelper
{
    static function findTypeWidget($typeDef,$nameOrPath)
    {
        $type=\lib\model\types\TypeFactory::getType($typeDef);
        $typeClass=get_class($type);
        // Se supone que el widgetPath es un objeto global
        global $WIDGETPATH;


        foreach($WIDGETPATH as $index=>$path)
        {
            
        }
    }
}

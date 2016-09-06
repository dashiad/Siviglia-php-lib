<?php
namespace lib\model\types;
class TreePathType extends StringType
{
   function __construct($def,$value=null)
   {
       $def["MAXLENGTH"]=255;
       $def["TYPE"]="TreePath";
            BaseType::__construct($def,$value);
   }
}
?>

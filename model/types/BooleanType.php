<?php namespace lib\model\types;
  class BooleanType extends BaseType{}

   class BooleanTypeHTMLSerializer extends BaseTypeHTMLSerializer
   {
      function serialize($type)
      {
          if($type->getValue())
              return "on";
          return "off";
      }
       function unserialize($type,$value)
       {
           if($value===true || $value===false)
               return $type->setValue($value);
           $v=strtolower($value);
           if($v==="true" || $v==="on" || $v==="1")
               return $type->setValue(true);
           $type->setValue(false);
       }
   }
?>

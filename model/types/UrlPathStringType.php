<?php
namespace lib\model\types;

// Modela un tipo de dato que es la transformacion de una string, a otra que debe ser unica, y modificada para 
// aparecer en links.

class UrlPathStringType extends StringType
{    
    function __construct($def,$value=null)
    {
        parent::__construct(
            array("ALLOWHTML"=>false,"TRIM"=>true,"MINLENGTH"=>1,"MAXLENGTH"=>100),
            $value);

    }
}

class UrlPathStringHTMLSerializer extends StringHTMLSerializer
{
      function serialize($type)
      {
          // Aqui habria que meter escapeado si la definition lo indica.
          if($type->valueSet)
              return htmlentities($type->getValue(),ENT_NOQUOTES,"UTF-8");
          return "";
      }
      function unserialize($type,$value)
      {          
          // Habria que ver tambien si esta en UTF-8 
           if($type->definition["TRIM"])
               $value=trim($value);
          // Escapeado -- Anti-Xss?
           $type->validate($value);
           $type->setValue($value);
      }
   }

?>

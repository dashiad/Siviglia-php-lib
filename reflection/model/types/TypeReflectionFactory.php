<?php
namespace lib\reflection\model\types;
class TypeReflectionFactory
{
        static function getReflectionType($typeDef)
        {
           if(!$typeDef["TYPE"] && (($typeDef["MODEL"] && $typeDef["FIELD"]) || $typeDef["REFERENCES"]))
                   return new ModelReferenceType($typeDef);
              $type=$typeDef["TYPE"]."Type";
              
              $cName='\lib\reflection\model\types\\'.$type;
              
              if(is_file(LIBPATH."/reflection/model/types/".$type.".php"))
              {
                  include_once(LIBPATH."/reflection/model/types/".$type.".php");
              }
              else
              {              
                  $cName='\app\model\types\reflection\\'.$type;
                  if(!class_exists($cName))
                  {                      
                      return new BaseType($typeDef);
                  }          
              }
              return new $cName($typeDef);
        }
}

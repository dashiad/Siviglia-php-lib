<?php
namespace lib\reflection\classes\types;
class TypeReflectionFactory
{
        static function getReflectionType($typeDef)
        {
           if(!$typeDef["TYPE"] && $typeDef["MODEL"] && $typeDef["FIELD"])
                   return new ModelReferenceType($typeDef);
           
              $cName='\lib\reflection\classes\types\\'.$type;

              if(is_file(LIBPATH."/model/types/".$type.".php"))
              {
                  include_once(LIBPATH."/model/types/".$type.".php");
              }
              else
              {              
                  $cName='\app\model\types\reflection\\'.$type;
                  if(!class_exists($cName))
                  {
                      return new BaseType($definition);
                  }          
              }
              return new $cName($typeDef);
        }
}

<?php
namespace lib\reflection\model;
class AliasFactory
{
        static function getAlias($parentModel,$name,$definition)
        {
                switch($definition["TYPE"])
                {
                case "InverseRelation":
                    {
                        $type="InverseRelation";
                    }break;
                case "RelationMxN":
                    {
                        $type="Relationship";
                    }break;
                case "TreeAlias":
                    {
                        $type="TreeAlias";
                    }break;
                }
                
                $typeReflectionFile=LIBPATH."/reflection/model/aliases/$type".".php";
                $dtype=$type;
                $className='\lib\reflection\model\aliases\\'.$dtype;
                include_once($typeReflectionFile);

                 // Los "alias" siempre tienen un parentModel.Los "types", no necesariamente.
                 // Por eso, los constructores de alias tienen $parentModel como primer parametro del constructor.
                 return new $className($name,$parentModel,$definition);
        }
}

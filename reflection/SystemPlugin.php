<?php
namespace lib\reflection;
class SystemPlugin {

    function __filterFieldsBy($tableName,$attrName,$attrValue)
    {
        global $objectDefinitions;
        $results=array();
        $fNames=$this->getFieldNames($tableName);
        for($k=0;$k<count($fNames);$k++)
        {
            
            if($objectDefinitions[$tableName]["FIELDS"][$fNames[$k]][$attrName]==$attrValue)
                $results[]=$fNames[$k];
        }
        return $results;
    }
    function iterateOnModels($method)
    {
        global $APP_NAMESPACES;
        foreach($APP_NAMESPACES as $layer)
        {
            $objects=ReflectorFactory::getObjectsByLayer($layer);
            foreach($objects as $name=>$model)
            {
                $this->{$method}($layer,$name,$model);                
            }
        }
    }
    function getLayer($layer)
    {
        return ReflectorFactory::getLayer($layer);
    }
    
    // Metodo para capturar eventos que no usamos
    function __call($method,$args)
    {

    }


}
?>

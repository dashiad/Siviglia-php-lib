<?php
namespace lib\reflection\base;
class Layer extends \lib\reflection\base\ClassFileGenerator
{
    var $layer;
    var $configInstance;
    var $serializer;
    function __construct($layer)
    {
        $this->layer=$layer;
        if(is_file(PROJECTPATH."/".$layer."/config/Config.php"))
        {
            include_once(PROJECTPATH."/".$layer."/config/Config.php");
              $className='\\'.$layer.'\config\Config';
              $this->configInstance=new $className();
        }
    }

    function getSerializer()
    {
       if(!$this->serializer)
       {
          global $SERIALIZERS;
          $this->serializer=\lib\storage\StorageFactory::getSerializer($SERIALIZERS[$this->layer]);
          $dS=$SERIALIZERS[$this->layer]["ADDRESS"]["database"];
          $this->serializer->useDataSpace($dS["NAME"]);          
       }
       return $this->serializer;
    }    
    function rebuildStorage()
    {
        
        if(!$this->configInstance->definition["DONT_REBUILD_DATASPACE"])
        {
            
            global $SERIALIZERS;
            $dS=$SERIALIZERS[$this->layer]["ADDRESS"]["database"];
            $ser=$this->getSerializer();
            
            if($this->serializer->existsDataSpace($dS))
                  $this->serializer->destroyDataSpace($dS);
            
            $this->serializer->createDataSpace($dS);
            
            $this->serializer->useDataSpace($dS["NAME"]);          
            return true;
         }
        return false;
    }
    function shouldRebuildStorage()
    {
        return !$this->configInstance->definition["DONT_REBUILD_DATASPACE"];
    }
    function getObjects()
    {
        $objects=\lib\reflection\ReflectorFactory::getObjectsByLayer($this->layer);
        return $objects;
        
    }
    function getQuickDefinitions()
    {
           return $this->configInstance->definition["QuickDef"];
    }
    function getPermissionsDefinition()
    {     
            return $this->configInstance->permissions;
    }

}

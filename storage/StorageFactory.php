<?php

namespace lib\storage;

class StorageFactoryException extends \lib\model\BaseException {
    const ERR_SERIALIZER_NOT_FOUND=1;
}

class StorageFactory
{
    static $defaultSerializer;
    static $defaultSerializers=array();

    static function setDefaultSerializer($definition)
    {
        $ser=StorageFactory::getSerializer($definition);
        StorageFactory::$defaultSerializer=$ser;
        return $ser;
    }

    static function addSerializer($definition)
    {
        
        $ser=StorageFactory::getSerializer($definition);
        if($definition["NAME"])
            StorageFactory::$defaultSerializers[$definition["NAME"]]=$ser;
        return $ser;
    }
    static function getDefaultSerializer($objName=null)
    {
        /*global $SERIALIZERS;
        if(!$SERIALIZERS[DEFAULT_SERIALIZER])
            throw new StorageFactoryException(StorageFactoryException::ERR_SERIALIZER_NOT_FOUND,array("name"=>$objName));
        */
        if(!$objName)
            return StorageFactory::getSerializerByName(DEFAULT_SERIALIZER);
        else
        {
            $objNameClass=new \lib\reflection\model\ObjectDefinition($objName);

                $Cserializer=$objNameClass->getDefaultSerializer();
            if($Cserializer)
                return \lib\storage\StorageFactory::getSerializerByName($Cserializer);
            else
                return StorageFactory::getSerializerByName(DEFAULT_SERIALIZER);
        }
    }
    static function getSerializerByName($name,$useDataSpace=true)
    {
        if(isset(StorageFactory::$defaultSerializers[$name]))
            return StorageFactory::$defaultSerializers[$name];
        global $SERIALIZERS;
        if(!isset($SERIALIZERS[$name]))
            throw new StorageFactoryException(StorageFactoryException::ERR_SERIALIZER_NOT_FOUND,array("name"=>$name));
        StorageFactory::$defaultSerializers[$name]=StorageFactory::getSerializer($SERIALIZERS[$name],$useDataSpace);
        return StorageFactory::$defaultSerializers[$name];
    }

    static function getSerializer($definition=null,$useDataSpace=true)
    {
        if($definition==null)
            return StorageFactory::getDefaultSerializer();

        $name=$definition["NAME"];
        if($name && StorageFactory::$defaultSerializers[$name])
            return StorageFactory::$defaultSerializers[$name];        
        if(!$definition["ADDRESS"])
            throw new StorageFactoryException(StorageFactoryException::ERR_SERIALIZER_NOT_FOUND,array("name"=>$name));

        $type=ucfirst(strtolower($definition["TYPE"]));
        $serClass='\lib\storage\\'.$type.'\\'.$type."Serializer";        
        $serializer = new $serClass($definition,$useDataSpace);
        if($name)
            StorageFactory::$defaultSerializers[$name]=$serializer;

        return $serializer;                
    }
   
}

?>

<?php
namespace lib\storage;
abstract class StorageSerializer
{
    private $storageManager;
    private $serializerType;
    protected $extraDefinition;
    function __construct($definition,$serType)
    {
        $this->serializerType=$serType;
        $this->definition=$definition;
    }    
    function getStorageManager()
    {
        return $this->storageManager;
    }

    function getSerializerType()
	{
		return $this->serializerType;
	}

	function serialize($object)
	{
		$object->setSerializer($this);
		$object->save();
	}
    // modeldef es una instancia de \lib\reflection\classes\ModelDefinition.
    // extradef es una instancia de \lib\reflection\classes\MysqlOptionsDefinition
    abstract function createStorage($modelDef,$extraDef=null);
    
    abstract function destroyStorage($object);
    abstract function createDataSpace($spaceDef);
    abstract function destroyDataSpace($spaceDef);

    abstract function unserialize($object,$queryDef=null,$filterValues=null);

    function loadExtraDefinition($objectName)
    {
        $this->extraDefinition=array();
        $def=\lib\model\types\TypeFactory::getObjectDefinition($objectName);
        $this->__loadExtraDefinition($def);
    }
    function __loadExtraDefinition($def)
    {
        if(isset($def["SERIALIZERS"]) && isset($def["SERIALIZERS"][$this->serializerType]))
        {
            $this->extraDefinition=$def["SERIALIZERS"][$this->serializerType];
            return $this->extraDefinition;
        }
        return null;
    }
    abstract function _store($object,$isNew,$dirtyFields);
    abstract function subLoad($definition,& $relationColumn);
    abstract function count($definition,& $model);
    abstract function useDataSpace($name);
    abstract function existsDataSpace($name);

}

?>

<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 25/03/15
 * Time: 18:15
 */

namespace lib\model;


class ModelCache {
    static $models=array();
    static function getInstance($modelName,$params=null,$nocache=false)
    {
        if($nocache)
        {
            return \lib\model\BaseModel::getModel($modelName,$params);
        }
        $instance=BaseModel::getModelInstance($modelName);
        if(!$params)
        {
            return $instance;
        }
        // La inicializacion del objeto puede hacer que se acceda a campos.No queremos esto.

        if($params)
        {
            foreach($params as $key => $value)
                $instance->{$key}=$value;
        }
        return ModelCache::storeOrLoad($instance);
    }
    static function storeOrLoad($instance)
    {
        $key=$instance->__getKeys();
        if($key->is_set())
        {
            $hash=$key->getHash();
            if(array_key_exists($hash,ModelCache::$models))
            {
                return ModelCache::$models[$hash];
            }
            else
                ModelCache::$models[$hash]=$instance;
        }
        $instance->loadFromFields();
        return $instance;

    }

    static function store($instance)
    {

        $key=$instance->__getKeys();
        if(!is_object($key))
        {
            return $instance;
        }
        $hash=$key->getHash();
        if(array_key_exists($hash,ModelCache::$models))
        {
            return ModelCache::$models[$hash];
        }
        else
            ModelCache::$models[$hash]=$instance;
        return $instance;
    }
    static function clear($instance)
    {
        $key=$instance->__getKeys();
        $hash=$key->getHash();
        if(array_key_exists($hash,ModelCache::$models))
        {
            unset(ModelCache::$models[$hash]);
        }
    }
    static function fromId($objectName,$id,$serializer=null)
    {
        $m=\getModel($objectName);
        $m->setId($id);
        return ModelCache::storeOrLoad($m);


    }



} 
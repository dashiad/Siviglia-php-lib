<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 28/08/15
 * Time: 13:15
 */

namespace lib\storageEngine\Resources;
use lib\storageEngine;
use lib\storageEngine\StorageEngineResult;
use lib\model\BaseException;
class ResourceException extends BaseException
{
    const ERR_NOT_LOADED=1;
    const ERR_NOT_NORMALIZED=2;
    const ERR_NOT_PROCESSED=3;
    const ERR_INVALID_RESPONSE=4;

}

abstract class Resource
{
    const ORIGIN_SOURCE=0;
    const ORIGIN_CACHE=1;
    const ORIGIN_NORMALIZED=2;
    const ORIGIN_PROCESSED=3;
    var $service;
    var $sourceQuery;
    var $sourceParams;
    var $processedValue=null;
    var $sourceValue=null;
    var $normalizedValue=null;
    var $value;
    var $processedCacheSource=null;
    var $isOk=false;
    var $loaded=false;
    var $processed=false;
    var $normalized=false;
    var $sourceResult=null;
    function __construct($service=null,$sourceQuery=null,$sourceParams=null)
    {
        $this->service=$service;
        $this->sourceQuery=$sourceQuery;
        $this->sourceParams=$sourceParams;
    }
    function setSourceValue(StorageEngineResult $res){

        if(!$this->validateRaw($res))
        {
            throw new ResourceException(ResourceException::ERR_INVALID_RESPONSE,array("response"=>$res->result));
        }
        if($this->isOk() && $res->sourceRole==Resource::ORIGIN_SOURCE)
        {
            $res->on(Resource::ORIGIN_CACHE,$res->result);
        }
        $this->sourceValue=$res;
        $this->sourceResult=$res;
        $this->normalize($res);
        $this->loaded=true;
    }
    function getSourceValue()
    {
        if(!$this->loaded)
        {
            throw new ResourceException(ResourceException::ERR_NOT_LOADED);
        }
        return $this->sourceValue;
    }

    // Cuando se llama a normalize, existe un sourceValue, pero no un value.Hay que establecer value a partir de SourceValue
    abstract function isOk();
    abstract function validateRaw(StorageEngineResult $res);
    function normalize(StorageEngineResult $val){
        $newRes=new StorageEngineResult($val->asArray());
        $newRes->result=$this->normalizeValue($val);
        $this->setNormalizedValue($newRes);
        $val->on(Resource::ORIGIN_NORMALIZED,$this->normalizedValue);

    }
    abstract function normalizeValue(StorageEngineResult $val);

    function isLoaded(){return $this->loaded;}
    function setNormalizedValue(StorageEngineResult $val)
    {
        $this->loaded=true;
        $this->normalizedValue=$val;
        $this->normalized=true;
        $this->sourceResult=$val;
    }
    function setProcessedValue($val,$copyToSource=true)
    {
        $this->loaded=true;
        $this->processed=true;
        $this->processedValue=$val;
        if($copyToSource && $this->sourceResult)
        {
            $this->sourceResult->on(Resource::ORIGIN_PROCESSED,$val);
        }
    }
    function unserialize(StorageEngineResult $r)
    {
        switch($r->sourceRole)
        {
            default:
                {
                $this->setSourceValue($r);
                }break;
            case Resource::ORIGIN_NORMALIZED:
            {
                $this->setNormalizedValue($r);
            }break;
            case Resource::ORIGIN_PROCESSED:
            {
                $this->setProcessedValue($r);
            }break;
        }
    }
    function getNormalizedValue()
    {
        if(!$this->normalized)
            throw new ResourceException(ResourceException::ERR_NOT_NORMALIZED);

        return $this->normalizedValue;
    }
    function getProcessedValue()
    {
        if(!$this->processed)
        {
            throw new ResourceException(ResourceException::ERR_NOT_PROCESSED);
        }
        return $this->processedValue;
    }
    function isProcessed()
    {
        return $this->processed;
    }

}

interface ConvertibleToArray
{
    function toArray($val);
}
interface ConvertibleToModel
{
    function getType();
    function getModel();
}
interface ConvertibleToIterable extends \ArrayAccess
{

}
interface RemoteIterator extends ConvertibleToIterable
{
    function getRemoteCount();
    function getCount();
    function getRemoteStart();

}


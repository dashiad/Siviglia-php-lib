<?php
namespace lib\storageEngine;

use lib\storageEngine\Service\IServiceProcessor;
use Doctrine\Common\Annotations\AnnotationRegistry;
use lib\model\BaseException;
use lib\php\ArrayMappedParameters;
use lib\storageEngine\StorageEngine;
use lib\storageEngine\StorageEngineException;
use lib\storageEngine\StorageEngineFactory;
use lib\storageEngine\StorageConnectionFactory;
use lib\storageEngine\StorageEngineGetParams;
use lib\storageEngine\StorageEngineSetParams;


class ServiceException extends BaseException
{
    const ERR_INVALID_RESULT=1;
    const ERR_FETCH_ERROR=2;
    const ERR_INVALID_PARAMS=3;
    const ERR_UNKNOWN_QUERY=4;
}
class ServiceParam extends ArrayMappedParameters{
	var $storageDefinitions=null; // Storages definidos por este servicio.
	var $queries; // array clave=>(parametros Get del Engine)
    var $context=array(); // variables globales de contexto, usadas como parametros en los StorageEngines, para no aniadir
                  // parametros a todos los servicios.
}

class Service
{
	var $definition;
	var $engines;
    var $processor=null;
	function __construct(ServiceParam $definition,IServiceProcessor $processor=null)
	{

		$this->definition=$definition;
        $this->processor=$processor;
        // Primero, hay que instalar los storages definidos dentro del servicio
        $this->createServiceStorages();
	}
    function extendDefinition($definition)
    {
        return $definition;
    }
    function createServiceStorages()
    {
        if(!$this->definition->storageDefinitions)
            return;
        foreach($this->definition->storageDefinitions as $key=>$value)
        {
           $engine=$value["engine"];
            $selfCreationMethod="create".ucfirst(strtolower($engine))."StorageEngine";
            if(method_exists($this,$selfCreationMethod)) {
                $engine = $this->{$selfCreationMethod}($value["params"]);
                StorageEngineFactory::addStorageEngine($key,$engine);
            }
            else
                StorageEngineFactory::addNamedEngine($key,$value["engine"],isset($value["params"])?$value["params"]:array());
        }
    }
		
	function query($serviceName,$srcParams,$opts=null)
	{
        $conditions=null;
        $params=null;
		if(isset($srcParams["conditions"]))
        {
            $conditions=$srcParams["conditions"];
        }
        else
        {
            if(isset($srcParams["params"]))
                $params=$srcParams["params"];
            else
                $params=$srcParams;
        }
		// Se obtienen los detalles completos de conexion al servicio.

		$details=$this->getServiceDetails($serviceName);
        // Source interno : metodo
        if($details["source"]=="method")
        {
            $method=$details["method"];
            return $this->{$method}($serviceName,$params);
        }

		if($params && !$this->validateParams($details,$params))
        {
            throw new ServiceException(ServiceException::ERR_INVALID_PARAMS,array("params"=>$params,"query"=>$serviceName));
        }

		// Se llama a un callback de "preparacion" de los parametros, por si necesitan algun tipo de preprocesamiento.
        if($params)
		    $params=$this->normalizeParams($details,$params);

		// Finalmente, se llama al servicio.
        // Los defaults y los mappings aqui, se refieren a la relacion entre el servicio, y el storage engine.

        $ssParams=new StorageEngineGetParams(array("query"=>$details["query"],
                                                    "defaults"=>isset($details["defaults"])?$details["defaults"]:null,
                                                    "mapping"=>isset($details["mapping"])?$details["mapping"]:array(),
                                                    "params"=>$params,
                                                    "conditions"=>$conditions,
                                                    "context"=>$this->definition->context));
        if($opts)
        {
            foreach($opts as $key=>$val)
                $ssParams->$key=$val;
        }
        $storageEngine=$this->getNamedEngine($details["source"]);
        try{
            $result=$storageEngine->get($ssParams);
        }catch(StorageEngineException $e)
        {
            throw new ServiceException(ServiceException::ERR_FETCH_ERROR,array("source"=>$details["source"],"query"=>$serviceName,"params"=>$params),$e);
        }
        if(!$this->validateResponse($result,$details,$params))
        {
            throw new ServiceException(ServiceException::ERR_INVALID_RESULT,array("response"=>$result->result));
        }

        $resourceClass=$details["resource"];
        $resource=new $resourceClass($this,$details["query"],$params);
        $resource->unserialize($result);

        if(!$this->processor)
            return $resource;


        if($resource->isProcessed())
            return $resource;

        $processedResult=$this->processor->process($resource);
        $resource->setProcessedValue($processedResult);
        return $resource->getProcessedValue();
	}

    function set($serviceName,$params,$values)
    {

        // Se obtienen los detalles completos de conexion al servicio.
        $details=$this->getServiceDetails($serviceName);
        // Source interno : metodo
        if($details["source"]=="method")
        {
            $method=$details["method"];
            return $this->{$method}($serviceName,$params);
        }

        if(!$this->validateParams($details,$params))
        {
            throw new ServiceException(ServiceException::ERR_INVALID_PARAMS,array("params"=>$params,"query"=>$serviceName));
        }

        // Se llama a un callback de "preparacion" de los parametros, por si necesitan algun tipo de preprocesamiento.
        $params=$this->normalizeParams($details,$params);

        // Finalmente, se llama al servicio.
        // Los defaults y los mappings aqui, se refieren a la relacion entre el servicio, y el storage engine.

        $ssParams=new StorageEngineSetParams(array("query"=>$details["query"],
            "defaults"=>isset($details["defaults"])?$details["defaults"]:null,
            "mapping"=>isset($details["mapping"])?$details["mapping"]:array(),
            "params"=>$params,
            "values"=>$values,
            "context"=>$this->definition->context));
        $storageEngine=$this->getNamedEngine($details["source"]);
        try{
            $result=$storageEngine->set($ssParams);
        }catch(StorageEngineException $e)
        {
            throw new ServiceException(ServiceException::ERR_FETCH_ERROR,array("source"=>$details["source"],"query"=>$serviceName,"params"=>$params),$e);
        }
        if(!$this->validateResponse($result,$details,$params))
        {
            throw new ServiceException(ServiceException::ERR_INVALID_RESULT,array("response"=>$result->result));
        }
        return true;
    }

    function getNamedEngine($definition)
    {
        return StorageEngineFactory::getNamedEngine($definition);
    }
    function validateParams($queryDetails,$params)
    {
        return true;
    }
	// A ser sobreescrita.
	function normalizeParams($details,$params)
	{
		return $params;
    }

	// A ser sobreescrita
	function validateResponse($response,$details,$params)
	{
		return true;
	}
	
	function getServiceDetails($serviceName)
	{
		$smap=$this->definition->queries;
		if(!isset($smap[$serviceName]))
		{
			// Excepcion 
			throw new ServiceException(ServiceException::ERR_UNKNOWN_QUERY,array("query"=>$serviceName));
		}
		
		// Los parametros se obtienen de unir a los parametros base, los especificos del servicio.
		return $smap[$serviceName];
	}

}

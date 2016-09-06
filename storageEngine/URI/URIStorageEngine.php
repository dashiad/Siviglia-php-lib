<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 15/09/15
 * Time: 9:33
 */

namespace lib\storageEngine\URI;

use lib\php\ArrayMappedParameters;
use lib\php\ParametrizableString;
use lib\model\BaseException;
use lib\storageEngine\StorageEngineGetParams;
use lib\storageEngine\StorageEngine;
use lib\storageEngine\WritableStorageEngine;
use lib\storageEngine\StorageEngineException;
use lib\storageEngine\StorageEngineSetParams;
use lib\storageEngine\StorageEngineResult;
use lib\storageEngine\IKeyedStorageEngine;

class URIStorageEngineException extends BaseException
{
    const ERR_CURL_ERROR = 1;
    const ERR_INVALID_HTTP_CODE = 2;
    const TXT_CURL_ERROR = "Error en llamada a curl, url:[%url%], errno:[%errno&], error:[%error%]";
    const TXT_INVALID_HTTP_CODE = "Codigo HTTP incorrecto, url:[%url%], codigo [%code%]";
}

class URIStorageQuery extends ArrayMappedParameters
{
    // Si no esta definida en la query, se tomara la definida en el los parametros del StorageEngine
    var $baseUrl = null;
    var $parameters = array();
    var $method = "GET";
    var $headers = array();
}

class URIStorageParams extends ArrayMappedParameters
{
    var $baseUrl;
    var $queries;
    var $timeout = 10;
    var $fixedParameters = array();
    static $__definition = array(
        "fields" => array(
            "baseUrl" => array("required" => false),
            "queries"=> array("required" => false)
            )
    );

    function __construct($arr)
    {
        parent::__construct($arr);
        if ($this->queries) {
            $a = array();
            foreach ($this->queries as $key => $value) {
                $a[$key] = new URIStorageQuery($value);
            }
            $this->queries = $a;
        }
    }
}

class URIStorageEngine extends StorageEngine
{
    var $definition;

    function __construct(URIStorageParams $definition)
    {
        $this->definition = $definition;
    }

    function get(StorageEngineGetParams $spec)
    {
        $path = $this->getDestinationUrl($spec);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $path);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->definition->timeout);

        $output = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new URIStorageEngineException(URIStorageEngineException::ERR_CURL_ERROR, array("url" => $path, "errno" => $ch, "error" => curl_error($ch)));
        }

        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code != 200) {
            throw new URIStorageEngineException(URIStorageEngineException::ERR_INVALID_HTTP_CODE, array("url" => $path, "code" => $code));
        }
        curl_close($ch);

        if ($output == "")
            throw new StorageEngineException(StorageEngineException::ERR_OBJECT_NOT_FOUND);

        return new StorageEngineResult(array("query" => $spec->query, "result" => $output, "source" => $this, "params" => $spec));
    }

    function getDestinationUrl(StorageEngineGetParams $params)
    {
        $queryName = $params->query;
        $values = $params->params;

        $query = $this->getQuery($queryName);
        $url = (isset($query->baseUrl) && !empty($query->baseUrl)) ? $query->baseUrl : $this->definition->baseUrl;

        // TODO : modificar para POST params
        $getParams = array();

        $path = ParametrizableString::getParametrizedString($url, $values);

        if (isset($this->definition->fixedParameters)) {
            foreach ($this->definition->fixedParameters as $key => $value) {
                $transformed = ParametrizableString::getParametrizedString($value, $values);
                if ($transformed !== "")
                    $getParams[] = $key . "=" . urlencode($transformed);
            }
        }

        if ($query->parameters) {
            foreach ($query->parameters as $key => $value) {
                $transformed = ParametrizableString::getParametrizedString($value, $values);
                if ($transformed !== "")
                    $getParams[] = $key . "=" . urlencode($transformed);
            }
        }

        $result = parse_url($path);
        if (!isset($result["query"]))
            $path .= "?";
        $path .= implode("&", $getParams);
        return $path;
    }

    function getQuery($name)
    {
        return $this->definition->queries[$name];
    }

}

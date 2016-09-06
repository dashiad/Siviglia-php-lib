<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 15/09/15
 * Time: 9:34
 */

namespace lib\storageEngine\File;


use lib\php\ArrayMappedParameters;
use lib\php\ParametrizableString;
use lib\model\BaseException;
use lib\storageEngine\StorageEngineGetParams;
use lib\storageEngine\WritableStorageEngine;
use lib\storageEngine\StorageEngineException;
use lib\storageEngine\StorageEngineSetParams;
use lib\storageEngine\StorageEngineResult;
use lib\storageEngine\IKeyedStorageEngine;

include_once(__DIR__ . "/../StorageEngine.php");

class FileStorageEngineException extends BaseException
{
    const ERR_CANT_CREATE_DIRECTORY = 1;
    const ERR_CANT_WRITE_FILE = 2;
    const ERR_CANT_READ_FILE = 3;
    const ERR_CANT_REMOVE_FILE = 4;
    const TXT_CANT_CREATE_DIRECTORY = "Error when creating directory [%path%]";
    const TXT_CANT_WRITE_FILE = "Error when writing to file [%path%]";
    const TXT_CANT_READ_FILE = "Error when reading file [%path%]";
    const TXT_CANT_REMOVE_FILE = "Error when removing file [%path%]";
}


class FileStorageQuery extends ArrayMappedParameters
{
    // Si no esta definida en la query, se tomara la definida en el los parametros del StorageEngine
    var $basePath = '';
}

class FileStorageParams extends ArrayMappedParameters
{
    var $basePath = "";
    var $queries;
    var $context = array();

    function __construct($arr)
    {
        parent::__construct($arr);
        if ($this->queries) {
            $a = array();
            foreach ($this->queries as $key => $value) {
                $a[$key] = new FileStorageQuery($value);
            }
            $this->queries = $a;
        }
    }
}

class FileStorageEngine extends WritableStorageEngine implements IKeyedStorageEngine
{

    const SERIALIZED_PREFIX = "@@SERIALIZED@@";

    function __construct(FileStorageParams $definition)
    {
        $this->definition = $definition;
        $this->queries = $definition->queries;
    }

    function get(StorageEngineGetParams $spec)
    {

        $path = $this->getIdentifierFor($spec);
        // Todo:  usar filemtime para caches reguladas por tiempo, etc.
        if (!is_file($path)) {
            throw new StorageEngineException(StorageEngineException::ERR_OBJECT_NOT_FOUND);
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new FileStorageEngineException(FileStorageEngineException::ERR_CANT_READ_FILE, array("path" => $path));
        }
        // Se mira si es una variable serializada
        $len = strlen(FileStorageEngine::SERIALIZED_PREFIX);
        $prefix = substr($contents, 0, $len);
        if ($prefix == FileStorageEngine::SERIALIZED_PREFIX) {
            $ser = substr($contents, $len);
            $contents = unserialize($ser);
        }
        return new StorageEngineResult(array("query" => $spec->query, "result" => $contents, "source" => $this, "params" => $spec));
    }

    function set(StorageEngineSetParams $spec)
    {

        $path = $this->getIdentifierFor($spec);

        if (!is_file($path)) {
            if (!is_dir(dirname($path))) {
                if (@mkdir(dirname($path), 0777, true) === false)
                    throw new FileStorageEngineException(FileStorageEngineException::ERR_CANT_CREATE_DIRECTORY, array("path" => $path));

            }
        }
        if (!is_string($spec->values)) {
            if(is_a($spec->values,'lib\storageEngine\StorageEngineResult'))
                $val = FileStorageEngine::SERIALIZED_PREFIX . serialize($spec->values->result);
            else
                $val = FileStorageEngine::SERIALIZED_PREFIX . serialize($spec->values);
        } else
            $val = $spec->values;
        if (@file_put_contents($path, $val) === false) {
            throw new FileStorageEngineException(FileStorageEngineException::ERR_CANT_WRITE_FILE, array("path" => $path));
        }
    }

    function remove(StorageEngineGetParams $spec)
    {
    }

    function clean(StorageEngineGetParams $spec)
    {
        $path = $this->getIdentifierFor($spec);
        if (@unlink($path) === false) {
            throw new FileStorageEngineException(FileStorageEngineException::ERR_CANT_REMOVE_FILE, array("path" => $path));
        }
    }

    function getIdentifierFor(StorageEngineGetParams $params)
    {
        $qDef = $this->getQuery($params->query);
        $path = $qDef->basePath == '' ? $this->definition->basePath : $qDef->basePath;
        $params->merge($this->definition->context, "context");
        return ParametrizableString::getParametrizedString($path, $params->params);
    }

}

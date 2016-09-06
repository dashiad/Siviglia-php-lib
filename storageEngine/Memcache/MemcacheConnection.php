<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 15/09/15
 * Time: 11:17
 */

namespace lib\storageEngine\Memcache;

use lib\php\ArrayMappedParameters;
use lib\model\BaseException;

class MemcacheConnectionException extends BaseException
{
    const ERR_CANT_CONNECT = 1;
}

class MemcacheConnectionParams extends ArrayMappedParameters
{
    var $host;
    var $port;
    var $context = array();
    var $compressed = false;
}

class MemCacheConnection
{
    var $connected = false;
    var $params;
    var $Memcache;

    function __construct(MemcacheConnectionParams $params)
    {
        $this->params = $params;
        $this->Memcache = new \Memcache();
    }

    function connect()
    {
        try {
            $result = $this->Memcache->connect($this->params->host, $this->params->port);
        } catch (\Exception $e) {
            throw new MemcacheConnectionException(MemcacheConnectionException::ERR_CANT_CONNECT, array("host" => $this->params->host, "port" => $this->params->port));
        }

        $this->connected = true;
    }

    function get($key)
    {
        if (!$this->connected)
            $this->connect();
        return $this->Memcache->get($key);
    }

    function set($key, $value, $expire = null)
    {
        if (!$this->connected)
            $this->connect();
        return $this->Memcache->set($key, $value, $this->params->compressed ? MEMCACHE_COMPRESSED : 0, $expire);
    }

    function delete($key)
    {
        if (!$this->connected)
            $this->connect();
        return $this->Memcache->delete($key);
    }
}

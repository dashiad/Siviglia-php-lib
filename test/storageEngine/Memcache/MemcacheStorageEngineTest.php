<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 21/09/15
 * Time: 15:25
 */

namespace lib\test\storageEngine\Memcache;
use lib\storageEngine\StorageConnectionFactory;
use lib\storageEngine\Memcache\MemcacheStorageEngine;
use lib\storageEngine\Memcache\MemcacheConnection;
use lib\storageEngine\Memcache\MemcacheStorageParams;
use \lib\storageEngine\StorageEngineException;
use \lib\storageEngine\StorageEngineGetParams;
use \lib\storageEngine\StorageEngineSetParams;
include_once(PROJECTPATH."/lib/storageEngine/StorageEngine.php");
include_once(PROJECTPATH."/lib/storageEngine/MemcacheStorageEngine.php");
include_once(PROJECTPATH."/lib/storageEngine/Connection/MemcacheConnection.php");


// Mock service...Mas facil que todo el lio de PHPUnit
class MemcacheFakeConnection
{
    var $storage=array();
    function __construct($params){
    }
    function connect()
    {
        return true;
    }
    function get($key)
    {

        if(isset($this->storage[$key]))
            return $this->storage[$key];
        return false;
    }
    function set($key,$value)
    {
        $this->storage[$key]=$value;
    }
    function delete($key)
    {
        unset($this->storage[$key]);
    }

}

class MemcacheStorageEngineTest extends \PHPUnit_Framework_TestCase {
    var $storage;
    var $engine;
    var $defConn;
    function SetUp()
    {
        $defConn=new MemcacheFakeConnection(array());
        StorageConnectionFactory::addConnection("MemcacheDefault",$defConn);
        $param=new MemcacheStorageParams(
            array("connectionName"=>"MemcacheDefault",
                "queries"=>array(
                    "cache1"=>array("baseIdentifier"=>"cacheId-[%param1%]")
                )
            )
        );
        $this->defConn=$defConn;
        $this->engine=new MemcacheStorageEngine($param);

    }
    function testSimpleWriteRead()
    {

        $writer=new StorageEngineSetParams(
            array("query"=>"cache1","params"=>array("param1"=>"43"),"values"=>"ProbandoMemcache"));
        $this->engine->set($writer);

        $reader=new StorageEngineGetParams(
            array("query"=>"cache1","params"=>array("param1"=>"43")));
        $result=$this->engine->get($reader);
        $this->assertEquals($result->result,"ProbandoMemcache");
    }
    function testSimpleWriteRead2()
    {
        $this->engine->setFromArray(array("query"=>"cache1","params"=>array("param1"=>"129"),"values"=>"Prueba 200"));
        $result=$this->engine->getFromArray(array("query"=>"cache1","params"=>array("param1"=>"129")));
        $this->assertEquals($result->result,"Prueba 200");
    }
    function testContextVariables()
    {
        $context=array("MYCONFIGVAR"=>"001");
        $engine=new MemcacheStorageEngine(new MemcacheStorageParams(
            array(
                "connectionName"=>"MemcacheDefault",
                "context"=>$context,
                "queries"=>array(
                    "cache1"=>array("baseIdentifier"=>"cacheId-[%param1%]-[%context.MYCONFIGVAR%]")
                )
            )
        ));
        $stdParams=array("query"=>"cache1","context"=>$context,"params"=>array("param1"=>"129"),"values"=>"Prueba 201");
// Comprobamos que , efectivamente, la generacion del identificador es correcto.
        $identifier=$engine->getIdentifierFor(new StorageEngineGetParams($stdParams));
        $this->assertEquals($identifier,'cacheId-129-001');
        $engine->setFromArray($stdParams);
        $result=$engine->getFromArray($stdParams);
        $this->assertEquals($result->result,"Prueba 201");
    }
    function testNotExistingKey()
    {
        $this->setExpectedException('\lib\storageEngine\StorageEngineException',
            '',
            StorageEngineException::ERR_OBJECT_NOT_FOUND);
        $this->engine->getFromArray(array("query"=>"cache1","params"=>array("param1"=>"wowo")));
    }
    function testNotExistingQuery()
    {
        $this->setExpectedException('\lib\storageEngine\StorageEngineException',
            '',
            StorageEngineException::ERR_UNKNOWN_QUERY);
            $result=$this->engine->getFromArray(array("query"=>"cache2"));
    }
    function testParamMapping()
    {
        $this->engine->setFromArray(array("query"=>"cache1","params"=>array("param1"=>"129"),"values"=>"Prueba 200"));
        $result=$this->engine->getFromArray(
            array(
                "query"=>"cache1",
                "mapping"=>array("incomingParam"=>"param1"),
                "params"=>array("incomingParam"=>"129")));
        $this->assertEquals($result->result,"Prueba 200");
    }

} 
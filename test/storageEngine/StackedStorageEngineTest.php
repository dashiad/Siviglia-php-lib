<?php

namespace Vocento\PublicationBundle\Tests\Lib\Base\StorageEngine;
use lib\storageEngine\StorageConnectionFactory;
use lib\storageEngine\StackedStorageEngine;
use lib\storageEngine\StackedStorageParams;
use lib\storageEngine\FileStorageEngine;
use lib\storageEngine\FileStorageParams;
use \lib\storageEngine\StorageEngineException;
use \lib\storageEngine\StorageEngineGetParams;
use \lib\storageEngine\StorageEngineSetParams;
use lib\storageEngine\MemcacheStorageEngine;
use lib\storageEngine\Connection\MemcacheConnection;
use lib\storageEngine\MemcacheStorageParams;
use lib\storageEngine\Resources\Resource;
use lib\test\storageEngine\MemcacheFakeConnection;


include_once(PROJECTPATH."/lib/storageEngine/StorageEngine.php");
include_once(PROJECTPATH."/lib/storageEngine/FileStorageEngine.php");
include_once(PROJECTPATH."/lib/storageEngine/MemcacheStorageEngine.php");
include_once(PROJECTPATH."/lib/storageEngine/Connection/MemcacheConnection.php");
include_once(PROJECTPATH."/lib/storageEngine/StackedStorageEngine.php");
include_once(PROJECTPATH."/lib/storageEngine/Resources/Resource.php");
include_once(__DIR__."/MemcacheStorageEngineTest.php");

class StackedStorageEngineTest extends \PHPUnit_Framework_TestCase{

    var $memFake;
    var $fileStorage;
    function SetUp()
    {
        if(!is_dir(__DIR__."/fileTests"))
        {
            @mkdir(__DIR__."/fileTests",0777);
        }
        // Se inicializa un fichero con algo de datos
        file_put_contents(__DIR__."/fileTests/stacked_22.txt","SampleData");
        // Se crea un storage por defecto.
        $param=new FileStorageParams(
            array("queries"=>array("cache1"=>array("basePath"=>__DIR__."/fileTests/stacked_[%param1%].txt"))
            )
        );
        $this->fileStorage=new FileStorageEngine($param);
        // Se crea una conexion Memcache
        $fakeConn=new MemcacheFakeConnection(array());
        StorageConnectionFactory::addConnection("MemcacheDefault",$fakeConn);
        $param=new MemcacheStorageParams(
            array("connectionName"=>"MemcacheDefault",
                "queries"=>array(
                    "cacheSource"=>array("baseIdentifier"=>"cacheId-[%param1%]")
                )
            )
        );

        $this->memFake=new MemcacheStorageEngine($param);

    }
    function tearDown()
    {
        if(is_dir(__DIR__."/fileTests"))
        {
            chmod(__DIR__."/fileTests",0777);
        if(is_dir(__DIR__."/fileTests/subdir"))
            chmod(__DIR__."/fileTests/subdir",0777);
        \lib\php\FileTools::delTree(__DIR__."/fileTests");
        }
    }
    function testSimpleStacked()
    {

        $stackedParams=new StackedStorageParams(
            array(
                "engines"=>array("engine1"=>$this->fileStorage),
                "queries"=>array("test1"=>array(array("engine"=>"engine1","query"=>"cache1")))
            )
        );
        $stackedStorage = new StackedStorageEngine($stackedParams);

        $result=$stackedStorage->getFromArray(array("query"=>"test1","params"=>array("param1"=>22)));
        $this->assertEquals($result->result,"SampleData");

    }
    function testFileAndMemcache()
    {
        $stackedStorage = new StackedStorageEngine(new StackedStorageParams(
            array(
                "engines"=>array("engineFile"=>$this->fileStorage,"engineMem"=>$this->memFake),
                "queries"=>array("test1"=>array(
                    array("engine"=>"engineMem","query"=>"cacheSource","role"=>Resource::ORIGIN_CACHE),
                    array("engine"=>"engineFile","query"=>"cache1","role"=>Resource::ORIGIN_SOURCE)))
            )
        ));
// El primer get viene de fichero
        $q=array("query"=>"test1","params"=>array("param1"=>22));
        $result=$stackedStorage->getFromArray($q);
        $this->assertEquals($result->sourceRole,Resource::ORIGIN_SOURCE);


// Lo escribimos sobre el Stacked. OJO: Esto lo escribe sobre todos los Stacked que sean Writables!
// Eso incluye File, asi que sobreescribiria

        $q["values"]=$result->result;
        $stackedStorage->set(new StorageEngineSetParams($q));

// Eso ya tuvo que cachearlo. El segundo get debe venir de memcache
        $result=$stackedStorage->getFromArray($q);
        $this->assertEquals($result->sourceRole,Resource::ORIGIN_CACHE);

    }
    function testStackedMapped()
    {
        $stackedStorage = new StackedStorageEngine(new StackedStorageParams(
            array(
                "engines"=>array("engineFile"=>$this->fileStorage,"engineMem"=>$this->memFake),
                "queries"=>array("test1"=>array(
                    array("engine"=>"engineMem",
                        "mapping"=>array("incomingParam"=>"param1"),
                        "query"=>"cacheSource",
                        "role"=>Resource::ORIGIN_CACHE),
                    array("engine"=>"engineFile",
                        "mapping"=>array("incomingParam"=>"param1"),
                        "query"=>"cache1","role"=>Resource::ORIGIN_SOURCE)))
            )
        ));
        $q=array("query"=>"test1","params"=>array("incomingParam"=>22));

        $result=$stackedStorage->getFromArray($q);

        $this->assertEquals($result->sourceRole,Resource::ORIGIN_SOURCE);
        $this->assertEquals($result->result,"SampleData");
    }

}


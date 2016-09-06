<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 21/09/15
 * Time: 17:21
 */

namespace lib\test\storageEngine\File;
use lib\storageEngine\File\FileStorageEngine;
use lib\storageEngine\File\FileStorageEngineException;
use lib\storageEngine\File\FileStorageParams;

use \lib\storageEngine\StorageEngineException;
use \lib\storageEngine\StorageEngineGetParams;
use \lib\storageEngine\StorageEngineSetParams;

include_once(PROJECTPATH."/lib/storageEngine/StorageEngine.php");
include_once(PROJECTPATH."/lib/storageEngine/File/FileStorageEngine.php");
class FileStorageEngineTest extends \PHPUnit_Framework_TestCase {

    function SetUp()
    {
        if(!is_dir(__DIR__."/fileTests"))
            return;
        chmod(__DIR__."/fileTests",0777);
        \lib\php\FileTools::delTree(__DIR__."/fileTests");
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
    function getDefaultEngine()
    {
        $param=new FileStorageParams(
            array(
                "queries"=>array(
                    "cache1"=>array("basePath"=>__DIR__."/fileTests/file_[%param1%].txt")
                )
            )
        );
        return new FileStorageEngine($param);
    }
    function testSimpleSet()
    {
        $engine=$this->getDefaultEngine();
        $baseParams=array("query"=>"cache1","params"=>array("param1"=>"43"),"values"=>"ProbandoFileStorage");
        $engine->setFromArray($baseParams);
        $result=$engine->getFromArray($baseParams);
        $this->assertEquals($result->result,"ProbandoFileStorage");
    }
    function testPHPSerialize()
    {
        $engine=$this->getDefaultEngine();
        $a=array("a"=>1,"b"=>2,"c"=>array("d"=>3));
        $baseParams["values"]=$a;
        $baseParams["query"]="cache1";
        $baseParams["params"]=array("param1"=>44);
        $engine->setFromArray($baseParams);
        $result=$engine->getFromArray($baseParams);
        $this->assertEquals($result->result["c"]["d"],3);
    }
    function testContext()
    {
        $context=array("MYCONFIGVAR"=>__DIR__);

        $engine=new FileStorageEngine(new FileStorageParams(
            array(
                "basePath"=>"[%context.MYCONFIGVAR%]/fileTests/file_[%param1%].txt",
                "context"=>$context,
                "queries"=>array(
                    "cache1"=>array(),
                    "cache2"=>array("basePath"=>__DIR__."/fileTests/subdir/file2_[%param1%].txt")
                )
            )
        ));
        $stdParams=array("query"=>"cache1","context"=>$context,"params"=>array("param1"=>"129"),"values"=>"Prueba 200");
        /* se reinician los permisos del fichero, por si una iteracion anterior los modifico */
        $stget=new StorageEngineGetParams($stdParams);
        $path=$engine->getIdentifierFor($stget);
        if(is_file($path))
            chmod($engine->getIdentifierFor($stget),0777);

        $engine->setFromArray($stdParams);
        $result=$engine->getFromArray($stdParams);
        $this->assertEquals($result->result,"Prueba 200");
    }
    function testNotExistingValue()
    {
        $this->setExpectedException('\lib\storageEngine\StorageEngineException',
            '',
            StorageEngineException::ERR_OBJECT_NOT_FOUND);
        $engine=$this->getDefaultEngine();
        $engine->getFromArray(array("query"=>"cache1","params"=>array("param1"=>"wowo")));
    }
    function testNotExistingQuery()
    {
        $this->setExpectedException('\lib\storageEngine\StorageEngineException',
            '',
            StorageEngineException::ERR_UNKNOWN_QUERY);
        $engine=$this->getDefaultEngine();
        $result=$engine->getFromArray(array("query"=>"cache3"));
    }
    function testParameterMapping()
    {
        $engine=$this->getDefaultEngine();
        $baseParams=array("query"=>"cache1","params"=>array("param1"=>"43"),"values"=>"ProbandoFileStorage");
        $engine->setFromArray($baseParams);
        $queryData=array(
            "query"=>"cache1",
            "mapping"=>array("incomingParam"=>"param1"),
            "params"=>array("incomingParam"=>"43"));
        $stget=new StorageEngineGetParams($queryData);
        $result=$engine->get($stget);
        $this->assertEquals($result->result,"ProbandoFileStorage");
    }
    function testReadError()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return;
        }
        $this->setExpectedException('\lib\storageEngine\FileStorageEngineException',
            '',
            FileStorageEngineException::ERR_CANT_READ_FILE);

        $engine=$this->getDefaultEngine();
        $queryData=array(
            "query"=>"cache1",
            "values"=>"Unreachable",
            "params"=>array("param1"=>"43"));
        $stget=new StorageEngineGetParams($queryData);
        $testPath=$engine->getIdentifierFor($stget);
        $engine->setFromArray($queryData);

        chmod($testPath,0000);
        $result=$engine->get($stget);
    }
    function testWriteError()
    {
        $this->setExpectedException('\lib\storageEngine\FileStorageEngineException',
            '',
            FileStorageEngineException::ERR_CANT_WRITE_FILE);

        $engine=$this->getDefaultEngine();
        $queryData=array(
            "query"=>"cache1",
            "values"=>"CantWriteTest",
            "params"=>array("param1"=>"43"));
        $stset=new StorageEngineSetParams($queryData);
        $testPath=$engine->getIdentifierFor($stset);
        // Se establece una primera vez, para que se cree el fichero.
        $engine->set($stset);

        chmod($testPath,0000);
        // Al intentar accederlo una segunda vez, debe dar una excepcion
        $engine->set($stset);
    }
    function testClean()
    {
        $engine=$this->getDefaultEngine();
        $queryData=array(
            "query"=>"cache1",
            "values"=>"CantWriteTest",
            "params"=>array("param1"=>"43"));
        $stget=new StorageEngineSetParams($queryData);
        $testPath=$engine->getIdentifierFor($stget);
        $engine->set($stget);
        $this->assertEquals(is_file($testPath),true);
        $engine->clean($stget);
        $this->assertEquals(is_file($testPath),false);
    }
    function testNotWritableDir()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return;
        }
        $this->setExpectedException('\lib\storageEngine\FileStorageEngineException',
            '',
            FileStorageEngineException::ERR_CANT_WRITE_FILE);

        $engine=$this->getDefaultEngine();

        $queryData=array(
            "query"=>"cache1",
            "values"=>"CantWriteTest",
            "params"=>array("param1"=>"43"));
        $stget=new StorageEngineSetParams($queryData);
        // Se fuerza un set para que se cree el directorio
        $engine->set($stget);
        chmod(__DIR__."/fileTests",0000);
        // El segundo set no debe poder funcionar.
        $engine->set($stget);

    }
    function testNotCreatableDir()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return;
        }
        $this->setExpectedException('\lib\storageEngine\FileStorageEngineException',
            '',
            FileStorageEngineException::ERR_CANT_CREATE_DIRECTORY);

        $context=array("MYCONFIGVAR"=>__DIR__);
        $engine=new FileStorageEngine(new FileStorageParams(
            array(
                "basePath"=>"[%context.MYCONFIGVAR%]/fileTests/file_[%param1%].txt",
                "context"=>$context,
                "queries"=>array(
                    "cache1"=>array(),
                    "cache2"=>array("basePath"=>__DIR__."/fileTests/subdir/file2_[%param1%].txt")
                )
            )
        ));
        //Primero una query para crear el directorio fileTests
        $engine->setFromArray(array(
            "query"=>"cache1",
            "values"=>"CantWriteTest",
            "params"=>array("param1"=>"43")
        ));


        chmod(__DIR__."/fileTests",0000);
        $queryData=array(
            "query"=>"cache2",
            "values"=>"CantWriteTest",
            "params"=>array("param1"=>"43"));
        $stget=new StorageEngineSetParams($queryData);
        $engine->set($stget);

    }
}

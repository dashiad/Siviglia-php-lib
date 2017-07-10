<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 26/06/2017
 * Time: 14:32
 */

namespace lib\test\storage\OracleStorage;

include_once("../../../../config/config.php");
include_once(PROJECTPATH."/lib/autoloader.php");
use lib\storage\OracleStorage\OracleStorageConnector;
use PHPUnit\Framework\TestCase;


class OracleStorageConnectorTest extends TestCase
{
    var $defaultParameters=array(
        "identityDomain"=>"a482323",
        "userName"=>"emendivil@smartclip.com",
        "password"=>"Pelochos1",
        "endPoint"=>"https://a482323.storage.oraclecloud.com",
        "PATH"=>"/unitTests"
    );
    function testCreateContainer()
    {
        $p=$this->defaultParameters;
        unset($p["PATH"]);
        $o=new OracleStorageConnector($p);
        $o->createDirectory("/unitTests");
        $l=$o->dirExists("/unitTests");
        $this->assertEquals($l,true);
    }
    function testCreateDirectory()
    {
        $o=new OracleStorageConnector($this->defaultParameters);
        $o->removeFilesByRegularExpression("/","#.*#",true);
        $o->createDirectory("/probando");
        $o->createDirectory("/probando/dos");
        $this->assertEquals($o->dirExists("/probando"),true);

        $this->assertEquals($o->dirExists("/probando/dos"),true);
    }
    function testCreateFile()
    {
        $o=new OracleStorageConnector($this->defaultParameters);
        $o->removeFilesByRegularExpression("/","#.*#",true);
        $o->saveFile("/probando/uno","aaa");
        $o->saveFile("/probando/dos","bbb");
        $o->saveFile("/probando/tres","ccc");
        $this->assertEquals($o->fileExists("/probando/tres"),true);
        $list=$o->getFileList("/probando");
        $this->assertEquals(count($list),3);
    }
    function testRemoveFile()
    {
        $o=new OracleStorageConnector($this->defaultParameters);
        $o->removeFilesByRegularExpression("/","#.*#",true);
        $o->saveFile("/probando/uno","aaa");
        $o->removeFile("/probando/uno");
        $this->assertEquals($o->fileExists("/probando/uno"),false);
    }
    function testListFiles()
    {
        $o=new OracleStorageConnector($this->defaultParameters);
        $o->removeFilesByRegularExpression("/","#.*#",true);
        $o->saveFile("/probando/uno","aaa");
        $o->saveFile("/probando/dos","bbb");
        $o->saveFile("/probando/otro/tres","ccc");
        $data=$o->getFileList("/probando","#.*o.*#",true);
        $this->assertEquals(count($data),2);
        $data=$o->getFileList("/probando/otro",null,true);
        $this->assertEquals(count($data),1);
    }
    function testRemoveFileList()
    {
        $o=new OracleStorageConnector($this->defaultParameters);
        $o->removeFilesByRegularExpression("/","#.*#",true);
        $o->saveFile("/probando/uno","aaa");
        $o->saveFile("/probando/dos","bbb");
        $o->saveFile("/probando/otro/tres","ccc");
        $list=$o->getFileList("/probando");
        $this->assertEquals(count($list),3);
        $o->removePath("/probando",true);
        $list=$o->getFileList("/probando");
        $this->assertEquals(count($list),0);

    }

    function testMultipleRemovals()
    {
        $o=new OracleStorageConnector($this->defaultParameters);
        $o->removeFilesByRegularExpression("/","#.*#",true);
        $o->saveFile("/probando/uno","eee");
        $o->saveFile("/probando/dos/tres","qqq");
        $o->saveFile("/probando/dos/cuatro/cinco","qqq");
        $o->saveFile("/probando/cuatro/cinco","fff");
        $o->saveFile("/probando/cinco/seis","fff");
        $o->saveFile("/probando/seis","hhh");

        $list=$o->getFileList("/probando/dos");
        $this->assertEquals(count($list),2);
        $o->removeFilesByRegularExpression("/probando/dos","#cin.*#",true);

        $list=$o->getFileList("/probando/dos");
        $this->assertEquals(count($list),1);

        $o->removeFilesByRegularExpression("/probando","#cin.*#",true);

        $list=$o->getFileList("/probando/cuatro");
        $this->assertEquals(count($list),0);

    }

    function testCompleteRemoval()
    {
        $o=new OracleStorageConnector($this->defaultParameters);
        $o->saveFile("/probando/uno","eee");
        $o->saveFile("/probando/dos/tres","qqq");
        $o->saveFile("/probando/dos/cuatro/cinco","qqq");
        $o->saveFile("/probando/cuatro/cinco","fff");
        $o->saveFile("/probando/cinco/seis","fff");
        $o->saveFile("/probando/seis","hhh");
        $o->removeFilesByRegularExpression("/","#.*#",true);
        $list=$o->getFileList("/");
        $this->assertEquals(count($list),0);

    }
}

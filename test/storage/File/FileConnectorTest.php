<?php
namespace lib\test\storage\File;
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 25/06/2017
 * Time: 2:24
 */
include_once("../../../../config/config.php");
include_once(PROJECTPATH."/lib/autoloader.php");
include_once(PROJECTPATH."/vendor/autoload.php");
include_once(PROJECTPATH."/lib/php/FileTools.php");
include_once(PROJECTPATH."/lib/model/BaseException.php");
include_once(PROJECTPATH."/lib/storage/Base/Connectors/TreeBased.php");
include_once(PROJECTPATH."/lib/storage/File/FileConnector.php");
use \lib\storage\File\FileConnector;
use \lib\storage\Base\Connectors\TreeBasedException;
use PHPUnit\Framework\TestCase;

define("TESTDIR",__DIR__."/fileTests");
class FileConnectorTest extends TestCase
{
    function SetUp()
    {
        if(!is_dir(TESTDIR))
            return;
        chmod(TESTDIR,0777);
        \lib\php\FileTools::delTree(TESTDIR);
    }
    function tearDown()
    {
        if(is_dir(TESTDIR))
        {
            chmod(TESTDIR,0777);
            if(is_dir(TESTDIR."/subdir"))
                chmod(TESTDIR."/subdir",0777);
            \lib\php\FileTools::delTree(TESTDIR);
        }
    }
    function testCreateDirectory()
    {
        $c=new FileConnector(array());
        $c->createDirectory(TESTDIR."/simpleDir");
        $this->assertEquals(is_dir(TESTDIR."/simpleDir"),true);
    }
    function testRemoveDirectory()
    {
        $c=new FileConnector(array());
        $c->createDirectory(TESTDIR."/simpleDir");
        $c->removePath(TESTDIR."/simpleDir");
        $this->assertEquals(is_dir(TESTDIR."/simpleDir"),false);
    }
    function testCreateDeepDirectory()
    {
        $c=new FileConnector(array("PATH"=>TESTDIR));
        $c->createDirectory("/simpleDir/a/b/c",true);
        $this->assertEquals(is_dir(TESTDIR."/simpleDir/a/b/c"),true);
        $this->assertEquals($c->dirExists("/simpleDir/a/b/c"),true);
        $this->assertEquals($c->dirExists("/simpleDir/a/b/d"),false);
    }
    function testRemoveRecursive()
    {
        $c=new FileConnector(array("PATH"=>TESTDIR));
        $c->createDirectory("/simpleDir/a/b/c",true);
        $c->removePath("/simpleDir/a",true);
        $this->assertEquals(is_dir(TESTDIR."/simpleDir/a/b/c"),false);
        $this->assertEquals(is_dir(TESTDIR."/simpleDir"),true);
    }
    function testCreateFile()
    {
        $c=new FileConnector(array("PATH"=>TESTDIR));
        $c->saveFile("/data.txt","Hola");
        $this->assertEquals(is_file(TESTDIR."/data.txt"),true);
        $d=$c->readFile("/data.txt");
        $this->assertEquals($d,"Hola");
        $c->removeFile("/data.txt");
        $this->assertEquals(is_file(TESTDIR."/data.txt"),false);
    }
    function testNoSuchFile()
    {

        if(PHP_OS!="WINNT") {
            $c = new FileConnector(array("PATH"=>TESTDIR));
            $this->expectException('lib\storage\Base\Connectors\TreeBasedException');
            $this->expectExceptionCode(TreeBasedException::ERR_NO_SUCH_NODE);
            $c->readFile("/notExistent.txt");
        }
        else
            $this->assertEquals(1,1);
    }
    function testNoFilePermissions()
    {
        $c=new FileConnector(array("PATH"=>TESTDIR));
        $c->saveFile("/uu.txt","a");
        chmod(TESTDIR."/uu.txt",0000);
        $this->expectException('lib\storage\Base\Connectors\TreeBasedException');
        $this->expectExceptionCode(TreeBasedException::ERR_PERMISSION_DENIED);
        $c->removeFile("/uu.txt");
        chmod(TESTDIR."/uu.txt",0777);
        $c->removeFile("/uu.txt");
        $this->assertEquals(is_file(TESTDIR."/uu.txt"),false);
    }
    function testNoDirPermissions()
    {

        if(PHP_OS!="WINNT") {
            $c = new FileConnector(array("PATH" => TESTDIR));
            chmod(TESTDIR, 0000);
            $this->expectException('lib\storage\Base\Connectors\TreeBasedException');
            $this->expectExceptionCode(TreeBasedException::ERR_PERMISSION_DENIED);
            $c->saveFile("/uu.txt", "hola");
        }
        else
            $this->assertEquals(true,true);
    }
    function testGetList1()
    {
        $c=new FileConnector(array("PATH"=>TESTDIR));
        $c->saveFile("/a1.txt","one");
        $c->saveFile("/b1.txt","two");
        $c->saveFile("/c1.dat","three");
        $c->createDirectory("/a1");
        $l1=$c->getFileList("/");
        $this->assertEquals(count($l1),4);
        $l2=$c->getFileList("/",null,true);
        $this->assertEquals(count($l2),3);
        $l3=$c->getFileList("/","#a1.*#",false);
        $this->assertEquals(count($l3),2);
        $l4=$c->getFileList("/","#a1.+#",false);
        $this->assertEquals(count($l4),1);
        $l5=$c->getFileList("/","#a1.*#",true);
        $this->assertEquals(count($l5),1);

    }

}
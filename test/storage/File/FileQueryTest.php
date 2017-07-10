<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 28/06/2017
 * Time: 13:47
 */

namespace lib\test\storage\File;
include_once("../../../../config/config.php");
include_once(PROJECTPATH."/lib/autoloader.php");
include_once(PROJECTPATH."/vendor/autoload.php");
include_once(LIBPATH."/storage/Base/StorageEngine.php");
include_once(LIBPATH."/storage/File/FileQuery.php");
use \lib\storage\File\FileQuery;
use \lib\storage\File\FileQueryFactory;
use \lib\storage\Base\StorageEngineGetParams;
use \lib\storage\File\FileFileQuery;
use \lib\storage\File\FileDirQuery;
use \lib\storage\File\FileConnector;

use PHPUnit\Framework\TestCase;

class FileQueryTest extends TestCase
{

    function testSimpleQuery()
    {
        $f=new FileQueryFactory();
        $file="file:///u.txt";
        $fq=$f->getInstance(array("query"=>$file));
        $params=new StorageEngineGetParams(array());
        $p=$fq->parse($params);
        $this->assertEquals($p,$file);
    }
    function testSimpleQuery2()
    {
        $f=new FileQueryFactory();
        $file="file:///path/[%subpath%]/data-[%type%][%date:-{%date%}%].txt";
        $fq=$f->getInstance(array("query"=>$file));
        $params=new StorageEngineGetParams(array(
            "params"=>array(
                "subpath"=>"lala",
                "type"=>"uno"
            )
        ));
        $p=$fq->parse($params);
        $this->assertEquals($p,"file:///path/lala/data-uno.txt");
        $params=new StorageEngineGetParams(array(
            "params"=>array(
                "subpath"=>"lala",
                "type"=>"uno",
                "date"=>"2017-01-01"
            )
        ));
        $p=$fq->parse($params);
        $this->assertEquals($p,"file:///path/lala/data-uno-2017-01-01.txt");
    }

}

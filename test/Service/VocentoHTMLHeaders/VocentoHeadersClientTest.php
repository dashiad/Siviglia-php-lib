<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 7/10/15
 * Time: 15:49
 */

namespace Vocento\PublicationBundle\Tests\Service\VocentoHTMLHeaders;
include_once(__DIR__."/../../../../Lib/Service/VocentoHTMLHeaders/VocentoHeadersClient.php");

class VocentoHeadersClientConfigFixture
{
    // Sin config
}
class VocentoHeadersFetcherFixture
{
    private $suffix;
    function __construct($suffix="")
    {
        $this->suffix=$suffix;
    }
    function fetch($config)
    {
        return array(
            "header"=>file_get_contents(__DIR__."/headerfiles/clientHeader".$this->suffix.".html"),
            "footer"=>file_get_contents(__DIR__."/headerfiles/clientFooter".$this->suffix.".html")
        );
    }
}

class VocentoHeadersFetcherFixture2
{
    var $fileCache;
    var $wasCached;
    function __construct($dir)
    {
        $this->fileCache=new \VocentoSimpleFileCache($dir);
        $this->fileCache->clean();
    }
    function fetch($publication)
    {
        $this->wasCached=false;
        $cached=$this->fileCache->get($publication);
        if($cached)
        {
            $this->wasCached=true;
            return $cached;
        }
        $response=array(
            "header"=>file_get_contents(__DIR__."/headerfiles/clientHeader2.html"),
            "footer"=>file_get_contents(__DIR__."/headerfiles/clientFooter2.html")
        );
        // Se ponen 4 segundos de TTL, para comprobar su funcionamiento.
        $this->fileCache->store($publication,$response,4);
        return $response;
    }
}
/**
 * Class VocentoHeadersClientTest
 * @package Vocento\PublicationBundle\Tests\Service\VocentoHTMLHeaders
 */
class VocentoHeadersClientTest extends \PHPUnit_Framework_TestCase {

    static function SetUpBeforeClass()
    {
        \Vocento\PublicationBundle\Lib\StorageEngine\StorageEngineFactory::clearCache();

    }
    function getConfiguration()
    {

    }
    function testLoad()
    {
        $transforms=new \VocentoHeadersTransform(array());
        $client=new \VocentoHeadersClient(new VocentoHeadersClientConfigFixture(),new VocentoHeadersFetcherFixture());
        $result=$client->fetch("hoy",$transforms);
        $this->assertEquals(true,strpos($result["header"],"<!-- @@")!==false);
    }
    function testTransform()
    {
        $transforms=new \VocentoHeadersTransform(array());
        $usedScript='<script src="testproof.js"></script>';
        $usedScript2='<script src="testproof2.js"></script>';
        $usedCss='<link type="text/css" href="testproof.css">';

        $transforms->addAfterTagStart("scripts",$usedScript);
        $transforms->addAfterTagStart("scripts",$usedScript2);
        $transforms->addBeforeTagEnd("css",$usedCss);
        $client=new \VocentoHeadersClient(new VocentoHeadersClientConfigFixture(),new VocentoHeadersFetcherFixture());
        $result=$client->fetch("hoy",$transforms);

        $this->assertEquals(true,strpos($result["header"],"<!-- @@BEGIN:scripts@@ -->".$usedScript.$usedScript2)!==false);
        $this->assertEquals(true,strpos($result["header"],$usedCss."<!-- @@END:css@@ -->")!==false);
    }
    function testReplace()
    {
        $transforms=new \VocentoHeadersTransform(array());
        $find="aa.html";
        $replace="https://bb.html";
        $transforms->addReplace($find,$replace);
        $client=new \VocentoHeadersClient(new VocentoHeadersClientConfigFixture(),new VocentoHeadersFetcherFixture());
        $result=$client->fetch("hoy",$transforms);

        $this->assertEquals(true,strpos($result["header"],$replace)!==false);
    }
    function testException()
    {
        $transforms=new \VocentoHeadersTransform(array());
        $client=new \VocentoHeadersClient(new VocentoHeadersClientConfigFixture(),new VocentoHeadersFetcherFixture(2));
        $this->setExpectedException('\ParametrizableStringException',
            '',
            \ParametrizableStringException::ERR_MISSING_REQUIRED_PARAM);
        $result=$client->fetch("hoy",$transforms);
    }
    function testParams()
    {
        $transforms=new \VocentoHeadersTransform(
            array("title"=>"El titulo","description"=>"La descripcion")
        );
        $client=new \VocentoHeadersClient(new VocentoHeadersClientConfigFixture(),new VocentoHeadersFetcherFixture(2));
        $result=$client->fetch("hoy",$transforms);
        $this->assertEquals(true,strpos($result["header"],'<meta name="description" value="La descripcion">')!==false);
        $this->assertEquals(true,strpos($result["header"]," coms_2='Valor por defecto'")!==false);
    }
    function testParamsOverride()
    {
        $transforms=new \VocentoHeadersTransform(
            array("title"=>"El titulo","description"=>"La descripcion","coms2"=>"DatoComscore")
        );
        $client=new \VocentoHeadersClient(new VocentoHeadersClientConfigFixture(),new VocentoHeadersFetcherFixture(2));
        $result=$client->fetch("hoy",$transforms);
        $this->assertEquals(true,strpos($result["header"]," coms_2='DATOCOMSCORE'")!==false);
    }
    function testFileCache()
    {
        $transforms=new \VocentoHeadersTransform(
            array("title"=>"El titulo","description"=>"La descripcion","coms2"=>"DatoComscore")
        );
        $fetcherFixture=new VocentoHeadersFetcherFixture2(__DIR__."/headercache");
        $client=new \VocentoHeadersClient(new VocentoHeadersClientConfigFixture(),$fetcherFixture);
        $result=$client->fetch("hoy",$transforms);
        $this->assertEquals(false,$fetcherFixture->wasCached);
        $result=$client->fetch("hoy",$transforms);
        $this->assertEquals(true,$fetcherFixture->wasCached);
        $this->assertEquals(true,file_exists(__DIR__."/headercache/hoy"));
        $this->assertEquals(true,strpos($result["header"]," coms_2='DATOCOMSCORE'")!==false);
        $fetcherFixture->fileCache->expire("hoy");
        $this->assertEquals(false,file_exists(__DIR__."/headercache/hoy"));
        $result=$client->fetch("hoy",$transforms);
        $this->assertEquals(false,$fetcherFixture->wasCached);
        // Se duerme durante 5 segundos
        sleep(2);
        $result=$client->fetch("hoy",$transforms);
        $this->assertEquals(true,$fetcherFixture->wasCached);
        sleep(3);
        $result=$client->fetch("hoy",$transforms);
        $this->assertEquals(false,$fetcherFixture->wasCached);
    }

} 
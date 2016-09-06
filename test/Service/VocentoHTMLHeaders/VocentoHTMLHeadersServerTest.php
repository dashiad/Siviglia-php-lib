<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 6/10/15
 * Time: 17:09
 */

namespace Vocento\PublicationBundle\Tests\Service\VocentoHTMLHeaders;
use Vocento\PublicationBundle\Service\VocentoHTMLHeaders;
use Vocento\PublicationBundle\Service\VocentoHTMLHeaders\DomainConfig;
use Vocento\PublicationBundle\Service\VocentoHTMLHeaders\LayoutConfig;
use Vocento\PublicationBundle\Service\VocentoHTMLHeaders\LayoutParser;

$ex1=class_exists('\Vocento\PublicationBundle\Lib\Base\Defined');
$ex2=class_exists('\Vocento\PublicationBundle\Service\VocentoHTMLHeaders\VocentoHTMLHeadersServer');


class testDomainFactory implements \Vocento\PublicationBundle\Lib\Base\DefinedFactory
{
    var $config=array(
        "http://www.a.com"=>array(
            "type"=>"type1_web",
            "parameters"=>array(
                "twitter"=>"@twittera",
                "tag"=>"sitea",
                "oas"=>"sitea-oas"
            ),
            "encoding"=>"utf-8"
        ),
        "http://m.c.a.com"=>array(
            "type"=>"type1_movil",
            "parameters"=>array(
                "twitter"=>"@twitterca",
                "tag"=>"siteca",
                "oas"=>"siteca-oas"
            ),
            "encoding"=>"utf-8"
        )
    );
    function getDefinition($domain)
    {
        return $this->config[$domain];
    }
}
class testLayoutFactory implements \Vocento\PublicationBundle\Lib\Base\DefinedFactory
{
    var $config=array(
        "one"=>array(
            "layout"=>"one.html"
        ),
        "one-1"=>array(
            "layout"=>"one.html",
            "parameters"=>array(
                "oas"=>"none"
            )
        ),
        "one-2"=>array(
            "inherits"=>"one-1",
            "parameters"=>array(
                "oas"=>"none-overriden"
            )
        ),
        "two"=>array(
            "layout"=>"two.html",
            "transforms"=>array(
                "insertBefore"=>array(
                    "block1"=>"beforeBlock1"
                ),
                "insertAfter"=>array(
                    "block1"=>"afterBlock1"
                ),
                "remove"=>array(
                    "block1"
                )
            )
        ),
        "two-1"=>array(
            "layout"=>"two-1.html",
            "transforms"=>array(
                "insertBefore"=>array(
                    "block1"=>"beforeBlock1"
                ),
                "insertAfter"=>array(
                    "block1"=>"afterBlock1"
                ),
                "remove"=>array(
                    "block1"
                )
            )
        ),
        "three"=>array(
            "layout"=>"three.html",
            "transforms"=>array(
                "replacements"=>array(
                    "reference"=>"[%domain%]",
                    "a[b-z]a"=>"e"
                )
            )
        ),
        "secure"=>array(
            "layout"=>"secure.html",
            "domainMap"=>array(
                "img.src"=>array(
                    "default"=>"https://www.default.com",
                    "relative"=>"https://www.default.com/relative",
                    "http://www.nondefault.com"=>"https://nondefault.com"
                )
            )
        ),
        "embed"=>array(
            "layout"=>"embed.html",
            "parameters"=>array(
                "output"=>"[%host%]"
            )
        ),
        "include"=>array(
            "layout"=>"include.html"
        ),
        "conditional"=>array(
            "layout"=>"conditional.html"
        ),
        "external"=>array(
            "layout"=>"external.html"
        ),
        "dummy"=>array(
            "layout"=>"dummy.html"
        )
    );
    function getDefinition($def)
    {
        return $this->config[$def];
    }
    function getLayout($definition)
    {
        $layout=$this->getLayoutPath($definition);
        return file_get_contents($layout);
    }
    function getLayoutPath($definition)
    {
        return __DIR__."/headerfiles/".$definition["layout"];
    }
}


class VocentoHTMLHeadersServerTest extends \PHPUnit_Framework_TestCase {
    function SetUp()
    {

    }
    function getTransformed($host,$config,$externalParameters=null)
    {
        $domainConfig = new DomainConfig($host,new testDomainFactory());
        $layoutConfig = new LayoutConfig($config,$domainConfig,new testLayoutFactory());
        if($externalParameters)
            $layoutConfig->addParameters($externalParameters);
        $layoutParser = new LayoutParser($layoutConfig,"http://www.base.com/resources");

        return $layoutParser->load();
    }
    function testWebSimple()
    {
        $result=$this->getTransformed("http://www.a.com","one");
        $expected="www.a.com-http://www.a.com-a.com-http://www.a.com-http://m.a.com-@twittera-sitea-sitea-oas";
        $this->assertEquals($expected,$result);
    }
    function testMobileSimple()
    {
        $result=$this->getTransformed("http://m.c.a.com","one");
        $expected="m.c.a.com-http://m.c.a.com-c.a.com-http://c.a.com-http://m.c.a.com-@twitterca-siteca-siteca-oas";
        $this->assertEquals($expected,$result);
    }
    function testLayoutOverridenParameter()
    {
        $result=$this->getTransformed("http://www.a.com","one-1");
        $expected="m.c.a.com-http://m.c.a.com-c.a.com-http://c.a.com-http://m.c.a.com-@twitterca-siteca-none";
        $this->assertEquals($expected,$result);
    }
    function testLayoutInheritance()
    {
        $result=$this->getTransformed("http://m.c.a.com","one-2");
        $expected="m.c.a.com-http://m.c.a.com-c.a.com-http://c.a.com-http://m.c.a.com-@twitterca-siteca-none-overriden";
        $this->assertEquals($expected,$result);
    }
    function testBlockOperations()
    {
        $result=$this->getTransformed("http://www.a.com","two");
        $expected="start-beforeBlock1afterBlock1-end";
        $this->assertEquals($expected,$result);
    }
    function testBlockJSOperations()
    {
        $result=$this->getTransformed("http://www.a.com","two-1");
        $expected="start-beforeBlock1afterBlock1-end";
        $this->assertEquals($expected,$result);
    }
    function testReplacements()
    {
        $result=$this->getTransformed("http://www.a.com","three");
        $expected="ewww.a.com";
        $this->assertEquals($expected,$result);
    }
    function testDomainMap()
    {
        $result=$this->getTransformed("http://www.a.com","secure");
        $parts=explode("body",$result);
        $expected='><img src="https://www.default.com/a/b"><img src="https://www.default.com/relative/a/b"><img src="https://nondefault.com/t/y"></';
        $this->assertEquals($expected,$parts[1]);
    }
    function testInclude()
    {
        $result=$this->getTransformed("http://www.a.com","include");
        $expected='includedFile';
        $this->assertEquals($expected,$result);
    }
    function testEmbebbedParameter()
    {
        $result=$this->getTransformed("http://www.a.com","embed");
        $expected='http://www.a.com';
        $this->assertEquals($expected,$result);
    }
    function testConditional()
    {
        $result=$this->getTransformed("http://www.a.com","conditional");
        $expected='ISAISNOTB';
        $this->assertEquals($expected,$result);
    }
    function testExternalParameter()
    {
        $result=$this->getTransformed("http://www.a.com","external",array("externalParam"=>"123"));
        $expected='http://www.a.com123';
        $this->assertEquals($expected,$result);
    }
    function testProcessCSSFile()
    {
        $domainConfig = new DomainConfig("http://www.a.com",new testDomainFactory());
        $layoutConfig = new LayoutConfig("dummy",$domainConfig,new testLayoutFactory());
        $layoutParser = new LayoutParser($layoutConfig,"http://www.base.com/resources");
        $targetFile=__DIR__."/headerfiles/includedcss.css";
        $asUrl=parse_url($targetFile);
        $targetFileDir=dirname($asUrl["path"]);
        $result=$layoutParser->processCSSFile(array(
            "domainMap"=>array(
                "default"=>"http://www.default.com",
                "http://www.absolute.com"=>"http://www.absolutechanged.com"
                )
            ),
            $targetFile,
            "http://www.base.com/resources",
            array("font-size:5px"=>"font-size:10px")
        );
        $expected=".a {background-image:url('http://www.default.com/res/a.img')}.b {background-image:url('http://www.default.com".$targetFileDir."/res/a.img')}.c {background-image:url('http://www.absolutechanged.com/res/a.img')}.d {font-size:10px}";
        $result=(strpos($result,$expected)!==false);
        $this->assertEquals(true,$result);
    }

} 
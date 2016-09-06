<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 2/10/15
 * Time: 13:08
 */

namespace Vocento\PublicationBundle\Tests\Service\VocentoHTMLHeaders;
use Vocento\PublicationBundle\Service\VocentoHTMLHeaders\VocentoHTMLHeadersParser;


class VocentoHTMLHeadersTest extends \PHPUnit_Framework_TestCase {
    var $simpleHeader;
    var $simpleFooter;
    function SetUp()
    {
        $this->simpleHeader=file_get_contents(__DIR__."/headerfiles/simpleHeader.html");
        $this->simpleFooter=file_get_contents(__DIR__."/headerfiles/simpleFooter.html");
    }
    function getConfig1()
    {
        return
            array(
            "httpDomain"=>"http://www.abc.es",
            "httpsDomain"=>"https://seguro.abc.es"

        );
    }
    function getConfig2()
    {
        return
            array(
                    "httpDomain"=>"http://www.abc.es",
                    "httpsDomain"=>"https://seguro.abc.es",
                    "transforms"=>array(
                        "insertAfter"=>array(
                            "css"=>"<script>[%testParam%]</script>"
                        )
                    )
            );
    }
    function getConfig3()
    {
        return
            array(
                    "httpDomain"=>"http://www.abc.es",
                    "httpsDomain"=>"https://seguro.abc.es",
                    "transforms"=>array(
                        "insertAfter"=>array(
                            "css"=>"<script>[%testParam%]</script>"
                        ),
                        "insertBefore"=>array(
                            "css"=>"<script>[%testParam2%]</script>"
                        )
                    )
            );
    }

    function getConfig4()
    {
        return
            array(
                "httpDomain"=>"http://www.abc.es",
                "httpsDomain"=>"https://seguro.abc.es",
            );
    }

    function cleanString($s1)
    {
        return str_replace(array("\n"," ","\r","\t"),"",$s1);
    }
    /**
     *  Test sin ningun tipo de reemplazo.
     */
    function testEmpty()
    {
        $config=$this->getConfig1();
        $request=array("publication"=>"abc","protocol"=>"http");
        $srv=new VocentoHTMLHeadersParser($config,$this->simpleHeader,$this->simpleFooter,$request);
        $result=$srv->parse();
        $expectedHeader='<!DOCTYPE html>
<html>
<head>
    <title></title>
    <!-- @@BEGIN:scripts@@ -->
    <script src="aa.html"></script>
    <!-- @@END:scripts@@ -->
    <!-- @@BEGIN:css@@ -->
    <link type="text/css" href="http://www.abc.es/css/estilosabc.css?v=38" rel="stylesheet" media="screen"/>
    <!-- @@END:css@@ -->

</head>
<body>
    <div class="bodyStart">';
        $expectedFooter='</div>

</body>
</html>';
        $h1=$this->cleanString($expectedHeader);
        $h2=$this->cleanString($result["header"]);
        $this->assertEquals($h1,$h2);
        $this->assertEquals($this->cleanString($expectedFooter),$this->cleanString($result["footer"]));
    }

    /****************************************************************************************************
     *
     *   Test de eliminacion de tag
     *
     ****************************************************************************************************/
    function testRemove()
    {
        $config=$this->getConfig1();
        $request=array("publication"=>"abc","protocol"=>"http","transforms"=>array("remove"=>array("css")));
        $srv=new VocentoHTMLHeadersParser($config,$this->simpleHeader,$this->simpleFooter,$request);
        $result=$srv->parse();
        $expectedHeader='<!DOCTYPE html><html><head><title></title>
            <!-- @@BEGIN:scripts@@ -->
            <script src="aa.html"></script>
            <!-- @@END:scripts@@ -->
            <!-- @@BEGIN:css@@ --><!-- @@END:css@@ -->
            </head><body><div class="bodyStart">';
        $expectedFooter='</div></body></html>';
        $h1=$this->cleanString($expectedHeader);
        $h2=$this->cleanString($result["header"]);
        $this->assertEquals($h1,$h2);
        $this->assertEquals($this->cleanString($expectedFooter),$this->cleanString($result["footer"]));
    }
    /****************************************************************************************************
     *
     *   Test de InsertBefore
     *
     ****************************************************************************************************/
    function testInsertBefore()
    {
        $config=$this->getConfig1();
        $request=array("publication"=>"abc","protocol"=>"http",
            "transforms"=>
                array(
                    "insertBefore"=>array("css"=>"<meta name=\"test\">")
                )
        );
        $srv=new VocentoHTMLHeadersParser($config,$this->simpleHeader,$this->simpleFooter,$request);
        $result=$srv->parse();
        $expectedHeader='<!DOCTYPE html>
<html>
<head>
    <title></title>
    <!-- @@BEGIN:scripts@@ -->
    <script src="aa.html"></script>
    <!-- @@END:scripts@@ -->
    <!-- @@BEGIN:css@@ --><meta name="test">
    <link type="text/css" href="http://www.abc.es/css/estilosabc.css?v=38" rel="stylesheet" media="screen"/>
    <!-- @@END:css@@ -->

</head>
<body>
    <div class="bodyStart">';
        $expectedFooter='</div></body></html>';
        $h1=$this->cleanString($expectedHeader);
        $h2=$this->cleanString($result["header"]);
        $this->assertEquals($h1,$h2);
        $this->assertEquals($this->cleanString($expectedFooter),$this->cleanString($result["footer"]));
    }
    /****************************************************************************************************
     *
     *   Test de simple insert after
     *
     ****************************************************************************************************/

    function testInsertAfter()
    {
        $config=$this->getConfig1();
        $request=array("publication"=>"abc","protocol"=>"http",
            "transforms"=>
                array(
                    "insertAfter"=>array("css"=>"<meta name=\"description\">")
                )
        );
        $srv=new VocentoHTMLHeadersParser($config,$this->simpleHeader,$this->simpleFooter,$request);
        $result=$srv->parse();
        $expectedHeader='<!DOCTYPE html>
<html>
<head>
    <title></title>
    <!-- @@BEGIN:scripts@@ -->
    <script src="aa.html"></script>
    <!-- @@END:scripts@@ -->
    <!-- @@BEGIN:css@@ -->
    <link type="text/css" href="http://www.abc.es/css/estilosabc.css?v=38" rel="stylesheet" media="screen"/>
    <meta name="description"><!-- @@END:css@@ -->

</head>
<body>
    <div class="bodyStart">';
        $expectedFooter='</div></body></html>';
        $h1=$this->cleanString($expectedHeader);
        $h2=$this->cleanString($result["header"]);
        $this->assertEquals($h1,$h2);
        $this->assertEquals($this->cleanString($expectedFooter),$this->cleanString($result["footer"]));

    }
    /****************************************************************************************************
     *
     *   Test de doble insert after
     *
     ****************************************************************************************************/

    function testDoubleInsertAfter()
    {
        $config=$this->getConfig1();
        $request=array("publication"=>"abc","protocol"=>"http",
            "transforms"=>
                array(
                    "insertAfter"=>array("css"=>"<script></script>","scripts"=>"<script></script>")
                )
        );
        $srv=new VocentoHTMLHeadersParser($config,$this->simpleHeader,$this->simpleFooter,$request);
        $result=$srv->parse();
        $expectedHeader='<!DOCTYPE html>
<html>
<head>
    <title></title>
    <!-- @@BEGIN:scripts@@ -->
    <script src="aa.html"></script>
    <script></script><!-- @@END:scripts@@ -->
    <!-- @@BEGIN:css@@ -->
    <link type="text/css" href="http://www.abc.es/css/estilosabc.css?v=38" rel="stylesheet" media="screen"/>
    <script></script><!-- @@END:css@@ -->

</head>
<body>
    <div class="bodyStart">';
        $expectedFooter='</div></body></html>';
        $h1=$this->cleanString($expectedHeader);
        $h2=$this->cleanString($result["header"]);
        $this->assertEquals($h1,$h2);
        $this->assertEquals($this->cleanString($expectedFooter),$this->cleanString($result["footer"]));
    }
/****************************************************************************************************
 *
 *   Test de reemplazo de variables + carga de config por defecto.
 *
 ****************************************************************************************************/
    function testVariableReplacement()
    {
        $config=$this->getConfig2();
        $request=array(
            "publication"=>"abc",
            "protocol"=>"http",
            "params"=>array(
                "testParam"=>"hola"
            )

        );
        $srv=new VocentoHTMLHeadersParser($config,$this->simpleHeader,$this->simpleFooter,$request);
        $result=$srv->parse(true);
        $expectedHeader='<!DOCTYPE html>
<html>
<head>
    <title></title>
    <!-- @@BEGIN:scripts@@ -->
    <script src="aa.html"></script>
    <!-- @@END:scripts@@ -->
    <!-- @@BEGIN:css@@ -->
    <link type="text/css" href="http://www.abc.es/css/estilosabc.css?v=38" rel="stylesheet" media="screen"/>
    <script>hola</script><!-- @@END:css@@ -->

</head>
<body>
    <div class="bodyStart">';
        $expectedFooter='</div></body></html>';
        $h1=$this->cleanString($expectedHeader);
        $h2=$this->cleanString($result["header"]);
        $this->assertEquals($h1,$h2);
        $this->assertEquals($this->cleanString($expectedFooter),$this->cleanString($result["footer"]));
    }
    /****************************************************************************************************
     *
     *   Test de reemplazo multiple de variables
     *
     ****************************************************************************************************/
    function testMultipleVariableReplacement()
    {
        $config=$this->getConfig3();
        $request=array(
            "publication"=>"abc",
            "protocol"=>"http",
            "params"=>array(
                "testParam"=>"hola",
                "testParam2"=>"adios"
            )

        );
        $srv=new VocentoHTMLHeadersParser($config,$this->simpleHeader,$this->simpleFooter,$request);
        $result=$srv->parse(true);
        $expectedHeader='<!DOCTYPE html>
<html>
<head>
    <title></title>
    <!-- @@BEGIN:scripts@@ -->
    <script src="aa.html"></script>
    <!-- @@END:scripts@@ -->
    <!-- @@BEGIN:css@@ --><script>adios</script>
    <link type="text/css" href="http://www.abc.es/css/estilosabc.css?v=38" rel="stylesheet" media="screen"/>
    <script>hola</script><!-- @@END:css@@ -->

</head>
<body>
    <div class="bodyStart">';
        $expectedFooter='</div></body></html>';
        $h1=$this->cleanString($expectedHeader);
        $h2=$this->cleanString($result["header"]);
        $this->assertEquals($h1,$h2);
        $this->assertEquals($this->cleanString($expectedFooter),$this->cleanString($result["footer"]));
    }
    /****************************************************************************************************
     *
     *   Test de reemplazo multiple de variables
     *
     ****************************************************************************************************/
    function testMultipleVariableAndDirectReplacement()
    {
        $config=$this->getConfig3();
        $request=array(
            "publication"=>"abc",
            "protocol"=>"http",
            "params"=>array(
                "testParam"=>"hola",
                "testParam2"=>"adios"
            ),
            "transforms"=>array(
                "pre-replacements"=>array(
                    "estilosabc.css"=>"sinestilo.css"
                )
            )

        );
        $srv=new VocentoHTMLHeadersParser($config,$this->simpleHeader,$this->simpleFooter,$request);
        $result=$srv->parse(true);
        $expectedHeader='<!DOCTYPE html>
<html>
<head>
    <title></title>
    <!-- @@BEGIN:scripts@@ -->
    <script src="aa.html"></script>
    <!-- @@END:scripts@@ -->
    <!-- @@BEGIN:css@@ --><script>adios</script>
    <link type="text/css" href="http://www.abc.es/css/sinestilo.css?v=38" rel="stylesheet" media="screen"/>
    <script>hola</script><!-- @@END:css@@ -->

</head>
<body>
    <div class="bodyStart">';
        $expectedFooter='</div></body></html>';
        $h1=$this->cleanString($expectedHeader);
        $h2=$this->cleanString($result["header"]);
        $this->assertEquals($h1,$h2);
        $this->assertEquals($this->cleanString($expectedFooter),$this->cleanString($result["footer"]));
    }

} 
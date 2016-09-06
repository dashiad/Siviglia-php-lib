<?php
set_time_limit(0);
// Define a usar en el codigo para cuando sea necesario ejecutar algo de distinta forma en caso de que se esten
// lanzando tests
define("__RUNNING_TESTS__",1);

$cli_mode = false;
$verbose_mode = false;
$startTest=null;
$endTest=null;
if ( isset($_SERVER['argc']) && $_SERVER['argc']>=1 ) {
    $cli_mode = true;
    if($argc==1)
    {
        die("Usage: ".$argv[0]." <namespacedModel> <startTest> <endTest>");
    }
    $objName=$argv[1];

    if ($argc>2) {
        $startTest = $argv[2];
    }

    if ($argc>3) {
        $endTest = $argv[3];
    }

    $doBuffer=false;
}
else
{
    $doBuffer=true;
    $objName=$_GET["object"];
    if(isset($_GET["start"]))
        $startTest=$_GET["start"];
    if(isset($_GET["end"]))
        $endTest=$_GET["end"];
}

@include_once("CONFIG_default.php");

if(!defined("PROJECTPATH"))
{
    set_include_path("/var/www/config".PATH_SEPARATOR.".");
    require_once("CONFIG_default.php");
}
require_once(PROJECTPATH."/lib/startup.php");
require_once(PROJECTPATH."/lib/testing/TestFactory.php");
if($doBuffer)
    ob_start();
$oFactory = new TestFactory($objName,$cli_mode);

$oFactory->executeTests($startTest,$endTest, false);
if($doBuffer)
    echo str_replace("\n","<br>",ob_get_clean());

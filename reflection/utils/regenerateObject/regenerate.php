<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Usuario
 * Date: 22/09/13
 * Time: 18:16
 * To change this template use File | Settings | File Templates.
 */
include_once("CONFIG_default.php");

function extra_autoload($name)
{

    //set_error_handler("_load_exception_thrower");
    if(is_file(PROJECTPATH."/".str_replace('\\','/',$name).".php"))
    {

        include_once(PROJECTPATH."/".str_replace('\\','/',$name).".php");
        return;
    }
    //restore_error_handler();
}
spl_autoload_register(extra_autoload);
//include_once(PROJECTPATH."/lib/startup.php");
include_once(LIBPATH . "/model/types/BaseType.php");
include_once(LIBPATH . "/php/debug/Debug.php");
include_once(LIBPATH . "/Registry.php");
// Se listan todos los objetos que hay.
include_once(LIBPATH . "/reflection/SystemReflector.php");
\lib\reflection\ReflectorFactory::loadFactory();
global $APP_NAMESPACES;
$layers=& $APP_NAMESPACES;
$layer=$_GET["layer"];
$obj=$_GET["object"];
$model=\lib\reflection\ReflectorFactory::getModel($obj,$layer);
// Hay que incluir los plugins
// Se regeneran acciones.
function printItem($cad){echo $cad;}
function printSubPhase($cad){echo $cad;}
include_once(LIBPATH."/reflection/plugins/ObjectLoader.php");
$oLoader=new \lib\reflection\plugins\ObjectLoader();
$oLoader->generateExtRelationships($layer,$obj,$model);
$oLoader->generateTempModelClasses($layer,$obj,$model);
if($_GET["regenerateTable"])
{
    include_once(LIBPATH."/reflection/plugins/MysqlProcessor.php");
    $oP=new \lib\reflection\plugins\MysqlProcessor();
    $oP->generateStorage($obj,$model,$layer);
}

include_once(LIBPATH."/reflection/plugins/ActionsProcessor.php");
$actions=new \lib\reflection\plugins\ActionsProcessor();
$actions->generateActions($obj, $model, $layer,false);
include_once(LIBPATH."/reflection/plugins/FormsGenerator.php");
$p=new \lib\reflection\plugins\FormsGenerator();
$p->buildForms($layer,$obj,$model);
include_once(LIBPATH."/reflection/plugins/DataSourceProcessor.php");
$p=new \lib\reflection\plugins\DataSourceProcessor();
$p->createDataSources($layer,$obj,$model);
$p->createAliasDataSources($layer,$obj,$model);
include_once(LIBPATH."/reflection/plugins/MysqlProcessor.php");
$p=new \lib\reflection\plugins\MysqlProcessor();
$p->rebuildDataSources($layer,$obj,$model);

$model->save();

include_once(LIBPATH."/reflection/plugins/ListCodeProcessor.php");
$p=new \lib\reflection\plugins\ListCodeProcessor();
$p->buildViews($layer,$name,$model,true);
include_once(LIBPATH."/reflection/plugins/DojoGenerator.php");
$p=new \lib\reflection\plugins\DojoGenerator();
$p->createOutput($layer,$obj,$model);



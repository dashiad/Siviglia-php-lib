<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Usuario
 * Date: 22/09/13
 * Time: 18:16
 * To change this template use File | Settings | File Templates.
 */
include_once("CONFIG_default.php");
function __autoload($name)
{

    //set_error_handler("_load_exception_thrower");
    if(is_file(PROJECTPATH."/".str_replace('\\','/',$name).".php"))
    {

        include_once(PROJECTPATH."/".str_replace('\\','/',$name).".php");
        return;
    }
    //restore_error_handler();
}
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
$model=\lib\reflection\ReflectorFactory::getModel($obj);

include_once(LIBPATH."/reflection/js/dojo/DojoGenerator.php");
$dojoClass=new \lib\reflection\js\dojo\DojoGenerator($model);
$datasources=$model->getDataSources();
foreach($datasources as $dKey=>$dValue)
{
    $code=$dojoClass->generateDatasourceView($dKey,$dValue);
    $dojoClass->saveDatasource($dKey,$code);

}



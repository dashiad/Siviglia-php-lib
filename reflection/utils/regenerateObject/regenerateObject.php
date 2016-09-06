<?php
include_once("CONFIG_default.php");
include_once(PROJECTPATH."/lib/startup.php");

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

//Regenerar actions
$actions = $model->getActions();
foreach ($actions as $aKey=>$aValue) {
    $aValue->save();
}

//Regenerar datasources
$datasources=$model->getDataSources();
foreach ($datasources as $dKey=>$dValue) {
    $dValue->save();
    $code=$dojoClass->generateDatasourceView($dKey,$dValue);
    $dojoClass->saveDatasource($dKey,$code);
}

echo "TODO EL OBJETO REGENERADO";


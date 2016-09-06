<?php
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

include_once(LIBPATH."/reflection/js/jQuery/jQueryGenerator.php");

$type = $_GET['type'];

$act = $_GET['action'];
if (! $act) {
    throw new \RuntimeException('No se ha indicado action');
}

if ($type['all']) {
    $actions = $model->getActions();
    foreach ($actions as $aKey=>$aValue) {
        rebuildAction($model,$aKey,$type);
    }
    echo 'TODAS LAS ACTIONS REGENERADAS';
}
else {
    $action = $model->getAction($act);
    rebuildaction($model,$action,$type);
}

function rebuildAction($model,$action,$type)
{
    $dojoClass=new \lib\reflection\js\dojo\DojoGenerator($model);
    $jQueryClass = new \lib\reflection\js\jquery\jQueryGenerator($model);
    $act=$action->getName();
    if ($type['all']) {
        $action->save();
        echo "Regenerando Accion\n";
    }
    include_once(LIBPATH.'/reflection/html/forms/FormDefinition.php');
    $formInstance=new \lib\reflection\html\forms\FormDefinition($act,$action);
    $formInstance->initialize();
    if($type["form"] || $type["all"])
    {
        //$formInstance->create();
        $formInstance->saveDefinition();
        $formInstance->generateCode();
        echo "Regenerando Formulario\n";
    }

    $a = $formInstance->getDefinition();
    if($type["dojo"]|| $type["all"])
    {
        $code=$dojoClass->generateForm($action->getName(),$formInstance);
        $dojoClass->saveForm($act,$code);
        echo "Regenerando clases Dojo\n";
    }
    if($type["jquery"]|| $type["all"])
    {
        $code=$jQueryClass->generateForm($action->getName(),$formInstance);
        $jQueryClass->saveForm($act,$code);
        echo "Regenerando ";
    }



}
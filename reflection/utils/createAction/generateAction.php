<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Usuario
 * Date: 22/09/13
 * Time: 23:44
 * To change this template use File | Settings | File Templates.
 */

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
function printWarning($cad)
{
    echo $cad;
}
include_once(LIBPATH . "/model/types/BaseType.php");
include_once(LIBPATH . "/php/debug/Debug.php");
include_once(LIBPATH . "/Registry.php");
// Se listan todos los objetos que hay.
include_once(LIBPATH . "/reflection/SystemReflector.php");
$model=\lib\reflection\ReflectorFactory::getModel($_POST["objName"]);
include_once(LIBPATH . "/reflection/actions/ActionDefinition.php");
if($_POST["tipo"]=="EditAction" || $_POST["tipo"]=="DeleteAction")
    $indexFields = $model->getIndexFields();
else
    $indexFields=null;

for($k=0;$k<count($_POST["fieldList"]);$k++)
{
    $fName=$_POST["fieldList"][$k];
    $curField=$model->getFieldOrAlias($fName);
    if($_POST["required"][$k])
        $requiredFields[$fName]=$curField;
    else
        $optionalFields[$fName]=$curField;
}
$paths=array();
for($k=0;$k<count($_POST["extraFields"]);$k++)
{
    if($_POST["extraFields"][$k]!="")
    {
        $fName=$_POST["extraFieldNames"][$k];
        $fTarget=$_POST["extraFields"][$k];

        $curField=$model->getFieldOrAlias($fTarget);
        if($curField==null)
        {
            echo "NO ENCONTRADO:".$_POST["extraFields"][$k]."<br>";

        }
        if($_POST["requiredExtra"][$k])
            $requiredFields[$fName]=$curField;
        else
            $optionalFields[$fName]=$curField;
        $paths[$fName]=$fTarget;
    }
}
$perms=\lib\reflection\permissions\PermissionRequirementsDefinition::create(array("_PUBLIC_"));
$curAction=new \lib\reflection\actions\ActionDefinition($_POST["actionName"],$model);

$curAction->create($_POST["tipo"],$indexFields,$requiredFields,$optionalFields,null,false,"",$paths);
$curAction->save();

include_once(LIBPATH.'/reflection/html/forms/FormDefinition.php');
$formInstance=new \lib\reflection\html\forms\FormDefinition($_POST["actionName"],$curAction);
    $formInstance->create();
$formInstance->saveDefinition();
$formInstance->generateCode();

// Se crea el formulario dojo.
include_once(LIBPATH."/reflection/js/dojo/DojoGenerator.php");
$oDojo=new \lib\reflection\js\dojo\DojoGenerator($model);
$code=$oDojo->generateForm($_POST["actionName"],$formInstance);
$oDojo->saveForm($_POST["actionName"],$code);
$webPage=new \lib\reflection\html\pages\FormWebPageDefinition();
$webPage->create($_POST["actionName"],$curAction,$_POST["objName"],$model);
$path=$webPage->getPath();
$paths[$path]=$webPage->getPathContents();
$extra=$webPage->getExtraPaths();
if($extra)
    $extraInfo[$path]=$extra;
$webPage->save();

// Se introduce la nueva url.Primero, hay que cargar las existentes.
/*include_once(WEBROOT."/Website/Urls.php");
$urls=new Website\Urls();
$curPaths=$urls->getPaths();

$pathTree=$path;
$parts=explode("/",$pathTree);
array_shift($parts);

$len=count($parts);
$position=& $curPaths[trim(trim(WEBPATH,"/")."/".WEBLOCALPATH,"/")];
$k=0;
for($k=0;$k<$len-1;$k++)
{
    echo "CURPATH::".$parts[$k]."<br>";
    $position=& $position["SUBPAGES"]["/".$parts[$k]];
}

$position["SUBPAGES"]["/".$parts[$k]]=$paths[$path];

$pathHandler=new \lib\reflection\html\UrlPathDefinition($curPaths);
$pathHandler->save();
*/
// Se introduce la nueva url.Primero, hay que cargar las existentes.
include_once(WEBROOT."/Website/Urls.php");
$urls=new Website\Urls();
$curPaths=$urls->getPaths();

$pathTree=$path;
$parts=explode("/",$pathTree);
array_shift($parts);

$len=count($parts);
$keys=array_keys($curPaths);

$position=& $curPaths[$keys[0]];
$k=0;
for($k=0;$k<$len-1;$k++)
{
    $position=& $position["SUBPAGES"]["/".$parts[$k]];
}

$position["SUBPAGES"]["/".$parts[$k]]=$paths[$path];

$pathHandler=new \lib\reflection\html\UrlPathDefinition($curPaths[$keys[0]]["SUBPAGES"]);
$pathHandler->save();
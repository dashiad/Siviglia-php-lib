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
$model=\lib\reflection\ReflectorFactory::getModel($_GET["object"]);
$actionDir=$model->objectName->getDestinationFile("actions");
$baseNamespace=$model->objectName->getNamespaced();
echo "<h2>Seleccionar accion del objeto ".$_GET["object"]."</h2>";
$l=opendir($actionDir);
while($f=readdir($l))
{
    if($f=="." || $f=="..")
        continue;
    $parts=explode(".",$f);
    $nParts=count($parts);
    if($parts[$nParts-1]!="php")
        continue;
    array_splice($parts,-1,1);
    echo '<a href="designActionOnObject.php?object='.$_GET["object"]."&action=".implode(".",$parts).'">'.implode(".",$parts).'</a><br>';
}
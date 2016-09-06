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

for($k=0;$k<count($layers);$k++)
{
    echo "<h2>Layer : ".$layers[$k]."</h2>";

    $cLayer=\lib\reflection\ReflectorFactory::getObjectsByLayer($layers[$k]);

    foreach($cLayer as $key=>$value)
    {
        if($_GET["existing"]!=1)
            echo '<a href="designActionOnObject.php?layer='.$layers[$k].'&object='.$key.'">'.$key.'</a><br>';
        else
            echo '<a href="chooseActionOnObject.php?layer='.$layers[$k].'&object='.$key.'">'.$key.'</a><br>';
    }
}
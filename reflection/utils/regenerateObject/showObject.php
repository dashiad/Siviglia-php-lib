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
$dojoClass=new \lib\reflection\js\dojo\DojoGenerator($model);


echo '<h2>Layer : '.$layer.'</h2>';
echo '<h3>Object : ' . $obj . '</h3>';

echo '<table style="width:100%;"><tr><th style="text-align:left;">Datasources</th><th style="text-align:left;">Actions</th></tr>';

$datasources=$model->getDataSources();
if ($datasources) {
    echo '<tr><td style="width:50%;"><ul>';
    foreach($datasources as $dKey=>$dValue) {
        echo '<li>'.$dKey;
        echo '&nbsp;[<a href="./regenerateDatasource.php?layer='.$layer.'&object='.$obj.'&datasource='.$dKey.'&type[all]=1">Regenerar datasource]</a>';
        echo '&nbsp;[<a href="./regenerateDatasource.php?layer='.$layer.'&object='.$obj.'&datasource='.$dKey.'&type[dojo]=1">Regenerar dojo]</a>';
        echo '&nbsp;[<a href="./regenerateDatasource.php?layer='.$layer.'&object='.$obj.'&datasource='.$dKey.'&type[jquery]=1">Regenerar jQuery]</a>';
        echo '</li>';
    }
    echo '<li><a href="./regenerateDatasource.php?layer='.$layer.'&object='.$obj.'&datasource=all&type[all]=1">Regenerar todos los datasources</li>';
}
else {
    echo 'No existen datasources';
}

echo '</ul></td>';

$actions = $model->getActions();
if ($actions) {
    echo '<td style="width:50%;vertical-align:top;"><ul>';
    foreach ($actions as $aKey => $aValue) {
        echo '<li>'.$aKey;
        echo '&nbsp;[<a href="./regenerateAction.php?layer='.$layer.'&object='.$obj.'&action='.$aKey.'&type[form]=1">Regenerar form]</a>';
        echo '&nbsp;[<a href="./regenerateAction.php?layer='.$layer.'&object='.$obj.'&action='.$aKey.'&type[all]=1">Regenerar vista]</a>';
        echo '&nbsp;[<a href="./regenerateAction.php?layer='.$layer.'&object='.$obj.'&action='.$aKey.'&type[dojo]=1">Regenerar dojo]</a></li>';
        echo '&nbsp;[<a href="./regenerateAction.php?layer='.$layer.'&object='.$obj.'&action='.$aKey.'&type[jquery]=1">Regenerar jQuery]</a></li>';
    }
    echo '<li><a href="./regenerateAction.php?layer='.$layer.'&object='.$obj.'&action=all&type[all]=1">Regenerar todas las actions</li>';
}
else {
    echo 'No existen actions';
}

echo '</ul></td></tr></table>';

echo '<a href="">Regenerar todo el objeto</a>';
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
$type=$_GET["type"];
$model=\lib\reflection\ReflectorFactory::getModel($obj);

include_once(LIBPATH."/reflection/js/dojo/DojoGenerator.php");


$ds = $_GET['datasource'];
if (! $ds) {
    throw new \RuntimeException('No se ha indicado datasource');
}
function regenerate($model,$dsName,$ds,$type)
{
    $dojoClass=new \lib\reflection\js\dojo\DojoGenerator($model);
    $jQueryClass=new \lib\reflection\js\jQuery\jQueryGenerator($model);

    if($type["all"])
    {
        $ds->save();
    }
    if($type["html"] || $type["all"])
    {
        //Ahora hay que generar la vista HTML y dojo.
        include_once(LIBPATH."/reflection/html/views/ListWidget.php");
        $listWidget=new lib\reflection\html\views\ListWidget($ds,$model,$datasource);
        $listWidget->initialize();
        $listWidget->generateCode(false,false);
        $listWidget->save();
        $webPage=new \lib\reflection\html\pages\ViewWebPageDefinition();
        $base="";
        if($model->objectName->isPrivate())
        {
            $base=$model->objectName->getNamespaceModel()."/";
        }
        $base.=$model->objectName->getClassName();
        $webPage->create($ds,$datasource,$base,null);
        $path=$webPage->getPath();
        $paths[$path]=$webPage->getPathContents();
        $extra=$webPage->getExtraPaths();
        if($extra)
            $extraInfo[$path]=$extra;
        $webPage->save();

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
    }

    if($type["dojo"]|| $type["all"])
    {
        $code=$dojoClass->generateDatasourceView($dsName,$ds);
        $dojoClass->saveDatasource($dsName,$code);
    }

    if($type["jquery"]|| $type["all"])
    {
        $code=$jQueryClass->generateDatasourceView($dsName,$ds);
        $jQueryClass->saveDatasource($dsName,$code);
    }
}

if ($ds === 'all') {
    $datasources=$model->getDataSources();
    foreach ($datasources as $dKey=>$dValue) {
        regenerate($model,$dKey,$dValue,$type);
    }
    echo 'TODOS LOS DATASOURCES REGENERADOS';
}
else {
    $datasource = $model->getDataSource($ds);
    regenerate($model,$ds,$datasource,$type);
}
    if ($action === 'all') {
        $datasource->save();
        $suffix = 'completo';
    }
    else {
        $suffix = 'solo con dojo';
    }

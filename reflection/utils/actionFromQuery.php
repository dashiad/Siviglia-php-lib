<?php
$object=$_POST["object"];
$query=$_POST["query"];
$name=$_POST["name"];

$object='backoffice\Bag';
$query="SELECT id_bag,id_bag_request FROM bag";
$name="lala";

// Se busca el objeto.

// Se busca ahora el modelo.
include_once(LIBPATH."/reflection/SystemReflector.php");

$model=\lib\reflection\ReflectorFactory::getModel($object);

include_once(LIBPATH."/reflection/datasources/DataSourceDefinition.php");
$oDs=new \lib\reflection\datasources\DataSourceDefinition($name,$model);

include_once(LIBPATH."/reflection/storage/MysqlDsDefinition.php");
$mysqlDef=new \lib\reflection\storage\MysqlDsDefinition($model,$name,$oDs);

$info=$mysqlDef->discoverQueryFields($query);
// Se miran ahora los campos que se han descubierto, las tablas a las que pertenecen, y se ve si la query contiene
// el id de la tabla del objeto principal.Si la query devuelve el id, se supone que es un action tipo "Edit".En otro caso,
// es un "add".
$fields=$info["fields"];
$table=$model->getTableName();

$ownFields=array();
for($k=0;$k<count($fields);$k++)
{
    if($fields[$k]["TABLE"]==$table)
    {
        $ownFields[]=$fields[$k]["FIELD"];
    }
}

if($model->areIndexesContained($ownFields))
{
    $role="Add";
}else
    $role="Edit";


die();

// Ahora hay que generar la vista HTML y dojo.
include_once(LIBPATH."/reflection/html/views/ListWidget.php");
$listWidget=new lib\reflection\html\views\ListWidget($name,$model,$newDs);
$listWidget->initialize();
$listWidget->generateCode(false,false);
$listWidget->save();


$webPage=new \lib\reflection\html\pages\ViewWebPageDefinition();
$webPage->create($name,$newDs,$model->objectName->getClassName(),null);
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

?>

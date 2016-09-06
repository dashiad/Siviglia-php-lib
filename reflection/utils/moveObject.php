<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Usuario
 * Date: 18/09/13
 * Time: 2:14
 * To change this template use File | Settings | File Templates.
 */
$dest=$_POST["destNamespace"];
$obj=$_POST["object"];
//moveObject($dest,$obj);
$dirs=array();

$base=PROJECTPATH."/backoffice/objects/";
$op1=opendir($base);
while($f=readdir($op1))
{
    if($f=="." || $f=="..")
        continue;
    if(is_dir($base.$f))
    {
        $base2=$base.$f."/objects";
        if(is_dir($base2))
        {

            $op2=opendir($base2);
            while($g=readdir($op2))
            {
                echo "MOVIENDO $g a $f<br>";

                flush();
                if($g!="." && $g!="..")
                {
                    moveObject($f,$g);
                    echo "MOVIDO";

                }
            }

        }
    }
}


function moveObject($dest,$obj)
{
$srcDir=dirname(__FILE__)."/../..";
$parts=explode('\\',$obj);
var_dump($_POST);



if(count($parts)>1)
{
    // Tenemos un namespace previo.
    $namespace=$parts[0];
    $object=$parts[1];
}
else
{
    $namespace=DEFAULT_NAMESPACE;
    $object=$parts[0];
}
$parts=array();
$parts=explode('\\',$dest);
var_dump($parts);
if(count($parts)>1)
{
    // Tenemos un namespace previo.
    $dstNamespace=$parts[0];
    $dstObject=$parts[1];
}
else
{
    $dstNamespace=DEFAULT_NAMESPACE;
    $dstObject=$parts[0];
}

$srcDir=PROJECTPATH."/".$namespace."/objects/".$object;
$destDir=PROJECTPATH."/".$dstNamespace."/objects/".$dstObject."/objects";
// Se mira si existe la carpeta original.
if(is_dir(PROJECTPATH."/".$namespace."/objects/".$object))
{
    if(!is_dir($destDir))
        mkdir($destDir,0777,true);
    echo "MOVIENDO DE $srcDir a $destDir";
   // rename($srcDir,$destDir);
}
$destDir.="/".$obj;
$dstNamespace=$dstNamespace.'\\'.$dstObject;
if(!is_dir($destDir))
{
    die("La carpeta destino ($destDir) no existe");
}
// Ahora hay que ejecutar los cambios textuales.
// Primero, dentro de la carpeta, cambiar cualquier referencia al simple namespace ('namespace <old>;') al nuevo.Esto hay que hacerlo
// desde dentro de la nueva carpeta.
$command="find $destDir -type f -name \"*.php\" -exec sed -i'' -e 's#namespace ".str_replace('\\','\\\\',$namespace).";#namespace ".str_replace('\\','\\\\',$dstNamespace).";#g' {} +";
exec($command);
// Siguiente:sustituir todas las referencias internas del objeto
$command="find $destDir -type f -name \"*.php\" -exec sed -i'' -e 's#namespace ".str_replace('\\','\\\\',$namespace)."\\\\$obj#namespace ".str_replace('\\','\\\\',$dstNamespace)."\\\\$obj#g' {} +";
echo $command;
exec($command);

// Siguiente: en todo el proyecto, sustituir las referencias al modelo
$command="find ".PROJECTPATH." -type f -name \"*.php\" -exec sed -i'' -e \"s#'MODEL'=>'$obj'#'MODEL'=>'".str_replace('\\','\\\\\\\\',$dstNamespace)."\\\\\\\\$obj'#g\" {} +";
echo $command;
exec($command);
$command="find ".PROJECTPATH." -type f -name \"*.php\" -exec sed -i'' -e \"s#'OBJECT'=>'$obj'#'OBJECT'=>'".str_replace('\\','\\\\\\\\',$dstNamespace)."\\\\\\\\$obj'#g\" {} +";
exec($command);
$command="find ".PROJECTPATH." -type f -name \"*.wid\" -exec sed -i'' -e 's#\"object\":\"".$obj."\"#\"object\":\"".str_replace('\\','\\\\',$dstNamespace)."\\\\$obj\"#g' {} +";
echo "<br>$command<br>";
exec($command);
$command="find ".PROJECTPATH." -type f -name \"*.wid\" -exec sed -i'' -e 's#\\\\\\\\[\\\\\\\\*/$obj#\\\\\\\\[\\\\\\\\*/".str_replace('\\','/',$dstNamespace)."/$obj#g\" {} +";
exec($command);
echo "<br>$command<br>";

$command="find ".PROJECTPATH." -type f -name \"*.php\" -exec sed -i'' -e \"s#'MODEL'=>'\\\\\\\\$namespace\\\\\\\\$obj'#'MODEL'=>'".str_replace('\\','\\\\\\\\',$dstNamespace)."\\\\\\\\$obj'#g\" {} +";
echo $command;
exec($command);
$command="find ".PROJECTPATH." -type f -name \"*.php\" -exec sed -i'' -e \"s#'OBJECT'=>\\\\\\\\$namespace\\\\\\\\'$obj'#'OBJECT'=>'".str_replace('\\','\\\\\\\\',$dstNamespace)."\\\\\\\\$obj'#g\" {} +";
exec($command);

//
$command="find $destDir -type f -name \"*.js\" -exec sed -i'' -e 's#dojo/text\\!$namespace/$obj#dojo/text\\!".str_replace('\\','/',$dstNamespace)."/objects/$obj#g' {} +";
echo $command;
exec($command);
}
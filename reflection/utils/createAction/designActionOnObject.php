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

echo "<h2>Objeto : ".$_GET["object"];
if($_GET["action"])
    echo " Accion:".$_GET["action"];
echo "</h2>";

$objName=str_replace('/','\\',$_GET["object"]);

$ins=\lib\model\BaseModel::getModelInstance($objName);
if($_GET["action"])
{
    $actionDir=$ins->__getObjectNameObj()->getDestinationFile("actions");
    include_once($actionDir."/".$_GET["action"].".php");
    $namespace=$ins->__getObjectNameObj()->getNamespaced();
    $actionName=$namespace.'\actions\\'.$_GET["action"];
    $definition=$actionName::$definition;
    $actfields=$definition["FIELDS"];
    $role=$definition["ROLE"];
}
else
{
    $actfields=array();
    $role="";
}

$fields=$ins->__getFields();
echo "<form method=\"POST\" action=\"generateAction.php\">";
echo '<input type="hidden" name="layer" value="'.$_GET["layer"].'">';
echo '<input type="hidden" name="objName" value="'.$objName.'">';
if($_GET["action"])
    echo 'Nombre:'.$_GET["action"].'<br><input type="hidden" name="actionName" value="'.$_GET["action"].'">';
else
    echo 'Nombre:<input type="text" name="actionName"><br>';
echo 'Tipo:<br>';
echo '<input type="checkbox" name="tipo" '.($role=="Add"?"checked":"").' value="AddAction">Add<br>';
echo '<input type="checkbox" name="tipo" '.($role=="Edit"?"checked":"").' value="EditAction">Edit<br>';
echo '<input type="checkbox" name="tipo" '.($role=="Delete"?"checked":"").' value="DeleteAction">Delete<br>';
echo "<table border=1>";
foreach($fields as $k=>$v)
{
    echo '<tr><td><input type="checkbox" value="'.$k.'" name="fieldList[]" '.(isset($actfields[$k])?"checked":"").'></td><td>'.$k.'</td>';
    echo '<td><input type="checkbox" value="Required'.$k.'" '.((isset($actfields[$k]) && $actfields[$k]["REQUIRED"])?"checked":"").'  name="required[]"></td></tr>';
    if(isset($actfields[$k]))
        unset($actfields[$k]);
}
echo "</table><br><br>";
echo "Campos adicionales [paths]";
echo "<table border=1>";
echo "<tr><td>Nombre de campo</td><td>Path</td><td>Requerido</td></tr>";
$keys=array_keys($actfields);
$nKeys=count($keys);
for($k=0;$k<10;$k++)
{
    if($k>=$nKeys)
    {
    echo '<tr><td><input type="text" name="extraFieldNames['.$k.']"></td><td>
    <input type="text" name="extraFields['.$k.']"></td>
    <td><input type="checkbox" name="requiredExtra['.$k.']"></tr>';
    }
    else
    {
        echo '<tr><td><input type="text" name="extraFieldNames['.$k.']" value="'.$keys[$k].'"></td><td>
    <input type="text" name="extraFields['.$k.']" value="'.$actfields[$keys[$k]]["FIELD"].'"></td>
    <td><input type="checkbox" name="requiredExtra['.$k.']" '.($actfields[$keys[$k]]["REQUIRED"]?"checked":"").'></tr>';
    }
}
echo "</table>";
echo '<input type="submit" value="Crear"></form>';

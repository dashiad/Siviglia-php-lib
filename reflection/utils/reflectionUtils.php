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
function ___cleanup()
{
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    global $oCurrentUser;
    Registry::save();
    //   if($_GET["output"]!="json")
    //      dumpDebug();
    $last_error = error_get_last();
    if($last_error['type'] === E_ERROR || $last_error['type'] === E_USER_ERROR)
    {
        header("Content-type: text/html");

        Kint::trace();
        Kint::dump($last_error);
    }
}
register_shutdown_function('___cleanup');

include_once(LIBPATH."/model/types/BaseType.php");
include_once(LIBPATH."/php/debug/Debug.php");
include_once(LIBPATH."/Registry.php");
include_once(PROJECTPATH."/lib/php/debug/Kint.class.php");
set_time_limit(0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

switch($_GET["action"])
{
    case "fromQuery":
    {
        try
        {
            include_once("fromQuery.php");
        }
        catch(Exception $e)
        {
            Kint::trace();
            Kint::dump($e);
        }
    }break;
    case "createAction":
    {
        include_once("createAction.php");
    }break;
    case "moveObject":
    {
        include_once("moveObject.php");
    }
}
?>
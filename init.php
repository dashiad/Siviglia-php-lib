<?php

if(defined("DEVELOPMENT"))
{
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
    ini_set("html_errors",1);
    ini_set("display_errors",1);
}
ini_set("xdebug.max_nesting_level",500);
include_once(LIBPATH."/autoloader.php");
include_once(LIBPATH."/reflection/model/ObjectDefinition.php");
include_once(LIBPATH."/model/BaseException.php");
include_once(LIBPATH."/model/types/BaseType.php");
include_once(LIBPATH."/Registry.php");
include_once(LIBPATH."/datasource/DataSourceFactory.php");
include_once(LIBPATH."/model/BaseTypedObject.php");
include_once(LIBPATH."/Request.php");

$request=Request::getInstance();
$request->initialize();
// Se determina el host al que va esta request, y a partir de ahi, el proyecto y site.
$host=$request->getHost();

$currentProject=\Projects::getProjectByHost($host);

if(!$currentProject)
    die();

\Registry::store(\Registry::PROJECT,$currentProject);

global $currentSite;
// Si tenemos proyecto actual, creamos defines para ellos
define("CURRENT_PROJECT",$currentProject->getName());
define("CURRENT_SITE",$currentProject->getCurrentSiteName());

$confClass=$currentProject->getConfig();
$currentSite=$currentProject->getCurrentSite();


// Copiamos algunas variables globales, para mantener la compatibilidad

global $SERIALIZERS;
if(isset($confClass::$SERIALIZERS)) {
    $SERIALIZERS = $confClass::$SERIALIZERS;
    define("DEFAULT_SERIALIZER", $confClass::$DEFAULT_SERIALIZER);
    define("DEFAULT_NAMESPACES", $confClass::$DEFAULT_NAMESPACE);
}
if(isset($confClass::$APP_NAMESPACES)) {
    global $APP_NAMESPACES;
    $APP_NAMESPACES = $confClass::$APP_NAMESPACES;
}

if(isset($confClass::$DEFAULT_TIMEZONE))
{
    //date_default_timezone_set('Europe/Madrid');
    date_default_timezone_set($confClass::$DEFAULT_TIMEZONE);
}


global $globalContext;
global $globalPath;
$globalContext=new \lib\model\SimpleContext();
$globalPath=new \lib\model\SimplePathObject();
$globalPath->addPath("registry",Registry::$registry);

//Incluimos las constantes en el registro para poder ser usadas en DS
$constants = get_defined_constants(true);
foreach($constants["user"] as $constant=>$constantValue) {
    Registry::store($constant, $constantValue);
}



// Funciones definidas aqui para simplificar el codigo de obtencion de modelos.
$modelCache=array();

function getModel($objName,$fields=null)
{
    global $currentSite;
    $prefix='';
    if($currentSite)
        $prefix='\runtime';
    $prefix.='\model';
    return \lib\model\ModelCache::getInstance($prefix.'\\'.$objName,$fields);
}

function getModelInstance($objName,$serializer=null,$definition=null)
{
    return \lib\model\BaseModel::getModelInstance($objName,$serializer,$definition);
}

  // La gestion de excepciones se realiza para evitar llamadas a file_exists,etc, que no ayudan al funcionamiento de apc_cache
  function _load_exception_thrower($code, $string, $file, $line, $context)
  { 
		throw new Exception($string,$code);
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
       echo "Error";
     }
     if (!( isset($_SERVER['argc']) && $_SERVER['argc']>=1 ))
     {
         \lib\php\FPMManager::getInstance()->runWorkers();
     }
 }
 register_shutdown_function('___cleanup');

function breakme()
{
    $a=1;
    $q=10;
}

// Se obtiene el usuario actual
global $oCurrentUser;
$oCurrentUser=$currentProject->getUserFactory()->getUser($request);
\Registry::store(\Registry::USER,$oCurrentUser);
\Registry::store(\Registry::USER_LANGUAGE_ISO,$oCurrentUser->getEffectiveLanguage());
// Finalmente, se enruta la request
$currentProject->initialize();


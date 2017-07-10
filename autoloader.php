<?php
namespace lib\autoload;
include_once(LIBPATH."/model/BaseException.php");
class AutoLoaderException extends \lib\model\BaseException {
    const ERR_PATH_NOT_FOUND=1;
    const ERR_CLASS_NOT_FOUND=2;
    }



class AutoLoader
{
    const LOCATION_SITE=1;
    const LOCATION_DEFAULT_SITE=2;
    const LOCATION_PROJECT=3;
    const LOCATION_ROOT=4;
    static $registered=0;
    static $lookupPaths=array();
    static $visitedFiles=array();
    static $sitePaths=array();
    static $projectRootPath=null;
    function __construct()
    {
        if (AutoLoader::$registered == 0) {
            AutoLoader::$registered = 1;
            spl_autoload_register(array($this, "resolve"));
        }
    }
    static function initializeProject()
    {
        global $currentProject;
        global $currentSite;

        if($currentProject) {
            $curSite = $currentSite;

            $pName = $currentProject->getName();

            do {
                AutoLoader::$sitePaths[] = array($curSite->getSiteRoot(), \lib\project\NamespaceMap::getSiteNamespace($pName, $curSite->getName()));
                $inherited = $curSite->inherits();
                if ($inherited)
                    $curSite->getSite($inherited);
                else
                    $curSite = null;
            } while ($curSite);
            AutoLoader::$projectRootPath = array($currentProject->getProjectRoot(), \lib\project\NamespaceMap::getProjectNamespace($pName));
        }

        // Se inicializan los paths de busqueda.
    }
    static function findFile($namespaced,$includeRootPath=0,$includeProjectPath=0,$project=null,$site=null)
    {
        $namespaced=str_replace("/","\\",$namespaced);
        $searchPath=AutoLoader::$sitePaths;
        if($includeRootPath && AutoLoader::$projectRootPath)
            $searchPath[]=AutoLoader::$projectRootPath;
        if($includeProjectPath)
            $searchPath[]=array(PROJECTPATH,"");
        for($k=0;$k<count($searchPath);$k++)
        {
            $f=$searchPath[$k][0]."/".str_replace('\\',"/",$namespaced).".php";
            if(is_file($f) && !in_array($f,AutoLoader::$visitedFiles))
                return array($f,$searchPath[$k][1].'\\'.$namespaced);
        }
        throw new AutoLoaderException(AutoLoaderException::ERR_PATH_NOT_FOUND,array("path"=>$namespaced));
    }


    // Atencion! $className ya viene en un array, para evitar explodes de mas
    static function _findClass($classParts,$project=null,$site=null)
    {
        // Las clases buscables son: page, model, config.Solo en "model" buscamos en el raiz.
        // En page, buscamos solo en site y en defaultSite
        $includeRoot=0;
        $includeProject=0;
        switch($classParts[0])
        {
            case "model":
            {
                $includeRoot=1;
                $includeProject=1;
            }break;
        }
        try{
            return AutoLoader::findFile(implode("/",$classParts),$includeRoot,$includeProject,$project,$site);
        }
        catch(AutoLoaderException $e)
        {
            throw new AutoLoaderException(AutoLoaderException::ERR_CLASS_NOT_FOUND,array("className"=>"runtime\\".implode("\\",$classParts)));
        }

    }
    static function resolveAlias($name)
    {
        $res=AutoLoader::_getRuntimeClass($name);
        return $res[1];
    }

    static function _getRuntimeClass($name)
    {
        global $currentSite;
        if ($currentSite) {
            try {
                $result = $currentSite->getCache(\lib\project\Site::SITECACHE_CLASSMAP)->get($name);
                return $result;
            } catch (\lib\cache\CacheException $e) {
            }
        }
        $parts = explode('\\', ($name[0] == '\\' ? substr($name, 1) : $name));
        if ($parts[0] == "")
            array_shift($parts);

        if (strpos($parts[0], "runtime") === 0) {
            array_shift($parts);
            return AutoLoader::_findClass($parts);
        } else {
            if ($parts[0] == "model") {
                array_shift($parts);
                $subName = implode('\\', $parts);
                $def = new \lib\reflection\model\ObjectDefinition($subName);
                $fName = $parts[count($parts) - 1];
                $fName = str_replace("Exception", "", $fName);
                return array($def->getDestinationFile($fName) . ".php", $name);
            }
        }
        $deffile=PROJECTPATH . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $name) . ".php";
        if(file_exists($deffile))
            return array($deffile, $name);
        return null;
    }

    function resolve($name)
    {
        $result=AutoLoader::_getRuntimeClass($name);
        if(!$result)
            return;

        include_once($result[0]);
        if($result[1]!=$name)
            class_alias($result[1],$name);
    }
}
global $autoLoader;
$autoLoader=new AutoLoader();



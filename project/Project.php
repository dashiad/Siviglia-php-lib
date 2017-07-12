<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 26/07/15
 * Time: 19:41
 */

namespace lib\project;

include_once(LIBPATH."/project/PathMap.php");
include_once(LIBPATH."/project/Site.php");
include_once(LIBPATH."/model/BaseException.php");
class ProjectException extends \lib\model\BaseException
{
    const ERR_NO_CONFIG=1;
    const ERR_UNKNOWN_CLASS=2;
}

abstract class Project {
    var $name;
    var $definition;
    var $currentSiteName;
    var $projectRoot;
    var $relativeProjectRoot;
    var $site;
    var $namespace;
    var $defaultSite;
    var $config;
    function __construct($name,$definition,$currentSite=null)
    {
        $this->name=$name;
        $this->definition=$definition;
        $this->currentSiteName=$currentSite;

        $this->currentSite=$currentSite?$currentSite:$definition["base"];

        $this->relativeProjectRoot="projects/".$this->name;
        $this->projectRoot=PathMap::getProjectRoot($this->name);
        $this->namespace=NamespaceMap::getProjectNamespace($this->name);
        if($currentSite)
            $this->setSite($currentSite);
    }
    function initialize()
    {
        // A ser sobreescrita.
    }
    function setSite($siteName)
    {
        $this->site=$this->getSite($siteName);
    }
    function cleanup()
    {
        // A ser sobreescrita
        $this->getCurrentSite()->cleanup();
    }
    function getSite($siteName)
    {
        include_once(PathMap::getSiteClassPath($this->name,$siteName));
        $siteClass=NamespaceMap::getSiteClass($this->name,$siteName);
        return new $siteClass($siteName,$this->definition["sites"][$siteName],$this);
    }
    function getProjectRoot()
    {
        return $this->projectRoot;
    }

    function getRelativeProjectRoot()
    {
        return $this->relativeProjectRoot;
    }

    function getNamespace()
    {
        return $this->namespace;
    }

    function getConfig()
    {
        if($this->config)
        {
            return $this->config;
        }
        // Ojo, la carga de configuraciones se hace de forma relativa, para cargarlas de un path de desarrollo, en
        // caso de que este exista.
        if($this->site!==null) {
            $siteConf = $this->site->loadConfig();
            if ($siteConf) {
                $this->config = $siteConf;
                return $siteConf;
            }
        }

        $target=stream_resolve_include_path($this->projectRoot."/config/Config.php");
        if(!$target)
        {
            throw new ProjectException(ProjectException::ERR_NO_CONFIG,array("project"=>$this->name,"site"=>$this->currentSite));
        }
        include_once($target);
        $cName=$this->namespace.'\\Config';
        $this->config=new $cName;
        return $this->config;
    }

    function getName()
    {
        return $this->name;
    }
    function getCurrentSite()
    {
        return $this->site;
    }

    function getCurrentSiteName()
    {
        return $this->currentSiteName;
    }

    abstract function getRouter();
    function getUrlPaths()
    {
        $default=$this->getDefaultSite();
        $sP=$default->getUrlPaths();
        $result=array(array(
            "Urls"=>$sP["Urls"],
            "Definitions"=>$sP["Definitions"],
            "namespace"=>NamespaceMap::getRouteNamespace($default)
        ));

        if($this->currentSite!=$this->definition["base"])
        {
            $spC=$this->site->getUrlPaths();
            $result[]=array(
                "Urls"=>$spC["Urls"],
                "Definitions"=>$spC["Definitions"],
                "namespace"=>NamespaceMap::getRouteNamespace($this->site)
            );
        }
        return $result;
    }

    function getCacheDir()
    {
        return $this->site->getCacheDir();
    }
    function getDefaultSite()
    {
        if(!$this->defaultSite)
        {
            $base=$this->getDefaultSiteName();
            include_once(PathMap::getSiteClassPath($this->name,$base));
            $siteClass=NamespaceMap::getSiteClass($this->name,$base);
            $this->defaultSite=new $siteClass($base,$this->definition["sites"][$base],$this);
        }
        return $this->defaultSite;
    }
    function getDefaultSiteName()
    {
        return $this->definition["base"];
    }
    function getUserFactory()
    {
        include_once(LIBPATH."/user/User.php");
        return new \lib\user\SimpleFactory();
    }
    function getAllowedLanguages()
    {
        $config=$this->getConfig();
        return $config::$ALLOWED_LANGUAGES;
    }
    function getDefaultLanguage()
    {
        return $this->conf("DEFAULT_LANGUAGE");
    }
    function conf($key)
    {
        $config=$this->getConfig();
        return $config::$$key;
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 26/07/15
 * Time: 23:00
 */

namespace lib\project;
include_once(LIBPATH."/cache/Cache.php");
class SiteException extends \lib\model\BaseException
{
    const ERR_PAGE_DOESNT_EXISTS=1;
}

class Site {
    var $name;
    var $definition;
    var $project;
    var $siteRoot;
    var $namespace;
    var $projectName;
    var $caches;
    const SITECACHE_ROUTES=1;
    const SITECACHE_LAYOUTS=2;
    const SITECACHE_CLASSMAP=3;
    const SITECACHE_FILEMAP=4;
    function __construct($name,$definition,$project)
    {
        $this->name=$name;
        $this->definition=$definition;
        $this->project=$project;
        $this->projectName=$project->getName();
        $this->relativeSiteRoot=PathMap::getRelativeSiteRoot($this->projectName,$name);
        $this->siteRoot=PathMap::getSiteRoot($this->projectName,$name);
        $this->namespace=NamespaceMap::getSiteNamespace($this->projectName,$name);

    }
    function getNamespace()
    {
        return $this->namespace;
    }
    function loadConfig()
    {
        $relative=$this->project->getRelativeProjectRoot();
        $target=stream_resolve_include_path($this->siteRoot."/config/Config.php");
        if($target)
        {
            include_once($target);
            $cName=$this->namespace.'\\Config';
            return new $cName;
        }
        return false;
    }
    function getCache($cacheType)
    {
        if(!$this->caches)
        {
            $baseDir=$this->getCacheDir();
            $this->caches=array(
                 Site::SITECACHE_CLASSMAP=>new \lib\cache\SerializedFileCache($baseDir."/classMap.srl",array()),
                 Site::SITECACHE_LAYOUTS=>new \lib\cache\DirectoryCache($baseDir."/pages",array()),
                 Site::SITECACHE_ROUTES=>new \lib\cache\SerializedFileCache($baseDir."/routes.srl",array()),
                 Site::SITECACHE_FILEMAP=>new \lib\cache\SerializedFileCache($baseDir."/paths.srl",array())
            );
        }
        return $this->caches[$cacheType];
    }
    function getCacheDir()
    {
        return PathMap::getSiteCachePath($this->projectName,$this->name);
    }
    function getUrlPaths()
    {
        $base=PathMap::getUrlPath($this->projectName,$this->name);
        return array(
          "Urls"=>$base."/Urls",
          "Definitions"=>$base."/Definitions"
        );
    }
    function getPagePath($pageName)
    {
        return PathMap::getSitePagePath($this->projectName,$this->name,$pageName);
    }
    function getCanonicalUrl()
    {
        return $this->definition["CANONICAL_URL"];
    }
    function getDocumentRoot()
    {
        return PathMap::getSiteDocumentRoot($this->projectName,$this->name);

    }
    function inherits()
    {
        return isset($this->definition["inherits"])?$this->definition["inherits"]:null;
    }
    function isDefaultSite()
    {
        return $this->name==$this->project->getDefaultSite()->getName();
    }
    function getProject()
    {
        return $this->project;
    }
    function getName()
    {
        return $this->name;
    }
    function getWidgetPath()
    {
        // Retornar el WIDGETPATH del site, incluyendo el del site por defecto si no es este mismo.
        $paths=array($this->relativeSiteRoot."/widgets");
        if(!$this->isDefaultSite())
        {
            $postPaths=$this->project->getDefaultSite()->getWidgetPath();
            $paths=array_merge_recursive($paths,$postPaths);
        }
        return $paths;
    }
    function getSiteRoot()
    {
        return $this->siteRoot;
    }
    function cleanup()
    {
        foreach($this->caches as $key=>$value)
            $value->save();
    }
    function getStaticsSite()
    {

        $s=\Projects::getProjectSite($this->project->conf("STATICS_PROJECT"));
        return $s;
    }
}
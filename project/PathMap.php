<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 25/07/15
 * Time: 19:40
 */

namespace lib\project;


class PathMap {

    static function getProjectFilePath(){return PROJECTPATH."/projects/Projects.php";}
    static function getRelativeProjectRoot($projectName){return "/projects/".$projectName;}
    static function getProjectRoot($projectName){return PROJECTPATH.PathMap::getRelativeProjectRoot($projectName);}
    static function getRelativeSiteRoot($projectName,$siteName){return PathMap::getRelativeProjectRoot($projectName)."/sites/".$siteName;}
    static function getSiteRoot($projectName,$siteName){return PathMap::getProjectRoot($projectName)."/sites/".$siteName;}
    static function getSiteCachePath($projectName,$siteName){return PathMap::getSiteRoot($projectName,$siteName)."/cache";}
    static function getUrlPath($projectName,$siteName){return PathMap::getSiteRoot($projectName,$siteName)."/routes";}
    static function getSitePage($projectName,$siteName,$pageName){return PathMap::getSitePagePath($projectName,$siteName,$pageName)."/".ucfirst($pageName).".php";}
    static function getSitePagePath($projectName,$siteName,$pageName){return PathMap::getSiteRoot($projectName,$siteName)."/pages/".$pageName;}
    static function getSiteDocumentRoot($projectName,$siteName){return PathMap::getSiteRoot($projectName,$siteName)."/html/";}
    static function getSiteClassPath($projectName,$siteName){return PathMap::getSiteRoot($projectName,$siteName)."/".ucfirst($siteName).".php";}
    static function toRelative($path){return "/".str_replace(PROJECTPATH,"",$path);}
}
class NamespaceMap {
    static function getRouteNamespace($site){return $site->getNamespace().'\routes';}
    static function getProjectNamespace($projectName){return 'projects\\'.$projectName;}
    static function getSiteNamespace($project,$siteName){return NamespaceMap::getProjectNamespace($project).'\sites\\'.$siteName;}
    static function getSitePageClass($project,$siteName,$pageName){return NamespaceMap::getSiteNamespace($project,$siteName).'\pages\\'.ucfirst($pageName).'\\'.ucfirst($pageName);}
    static function getSiteClass($project,$siteName){return NamespaceMap::getSiteNamespace($project,$siteName)."\\".ucfirst($siteName);}
}
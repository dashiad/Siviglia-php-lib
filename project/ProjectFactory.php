<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 26/07/15
 * Time: 18:48
 */

namespace lib\project;
include_once(LIBPATH."/reflection/Interfaces.php");
include_once(LIBPATH."/project/PathMap.php");

class ProjectFactory implements \lib\reflection\ManagedSourceCode {
    static $definition;
    function getSourceTemplate()
    {
        return "ProjectList";
    }
    static function getProjectByName($name,$site=null)
    {
        foreach(static::$definition as $key=>$value)
        {
            if($key==$name)
            {
                $className=ucfirst($key);
                $namespaced='projects\\'.$className;
                include_once(PathMap::getProjectRoot($key)."/".$className.".php");
                return new $namespaced($key,$value,$site);
            }

        }

    }
    static function getProjectByHost($host)
    {


        foreach(static::$definition as $key=>$value)
        {
            foreach($value["sites"] as $k2=>$v2)
            {
                foreach($v2["DOMAINS"] as $k3=>$v3)
                {
                    if(preg_match("#".$v3."#",$host))
                    {
                        $className=ucfirst($key);
                        $namespaced='projects\\'.$className;
                        include_once(PathMap::getProjectRoot($key)."/".$className.".php");
                        return new $namespaced($key,$value,$k2);
                    }
                }
            }
        }
        return null;
    }
    static function getProjectSite($project,$site=null)
    {
        if(!$site)
        {
            $parts=explode("/",$project);
            $project=$parts[0];
            $site=$parts[1];
        }
        $project=self::getProjectByName($project,$site);
        return $project->getCurrentSite();
    }
} 
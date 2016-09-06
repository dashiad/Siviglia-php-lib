<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 26/07/15
 * Time: 19:48
 */

namespace lib\project\types;
include_once(LIBPATH."/project/Project.php");


class WebProject extends \lib\project\Project{

    function getRouter()
    {
        include_once(LIBPATH."/output/html/HTMLRouter.php");
        return new \lib\output\html\HTMLRouter($this);

    }
    function getPage($pageName,$params)
    {
        $className='\runtime\\pages\\'.ucfirst($pageName).'\\'.ucfirst($pageName);
        return new $className($pageName,$this->getCurrentSite(),$params);
    }
    function templatePreviewMode()
    {
        return false;
    }
    function getWidgetPath()
    {
        // El widget path es,primero, el del site por defecto, luego, el del proyecto, luego, el de sistema.

        return array(
            \lib\project\PathMap::getRelativeSiteRoot($this->name,$this->defaultSite->g)."/widgets",
            \lib\project\PathMap::getRelativeProjectRoot($this->name)."/widgets",
            "/widgets"
        );
    }

}
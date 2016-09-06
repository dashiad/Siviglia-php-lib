<?php
namespace lib\output\html;
class  WebPageException extends \lib\model\BaseException
{
    const ERR_REQUIRED_PARAM=1;
    const ERR_INVALID_PARAM=2;
    const ERR_UNAUTHORIZED=3;
    const ERR_NO_LAYOUT=4;
}
/*

*/
abstract class WebPage extends \lib\model\BaseTypedObject
{
    var $site;
    var $name;
    var $_definition;
    function __construct($pageName,$site,$params)
    {
        $realClass=get_class($this);
        $this->_definition=$realClass::$definition;
        $this->name=$pageName;
        $this->site=$site;
        if($this->_definition["INHERIT_PARAMS"])
        {
            $i=$this->_definition["INHERIT_PARAMS"];
            $sourceModel=$i["MODEL"];
            if(isset($i["DATASOURCE"]))
            {
                $source=\getDataSource($sourceModel,$i["DATASOURCE"]);
            }
            if(isset($i["FORM"]))
            {
                $objName=new \lib\reflection\model\ObjectDefinition($sourceModel);
                include_once($objName->getFormFileName($i["FORM"]));
                $formClass=$objName->getNamespacedForm($i["FORM"]);
                $source=new $formClass();
            }
            if(isset($i["ACTION"]))
            {
                $objName=new \lib\reflection\model\ObjectDefinition($sourceModel);
                include_once($objName->getActionFileName($i["ACTION"]));
                $actionClass=$objName->getNamespacedAction($i["ACTION"]);
                $source=new $actionClass();
            }
            $fields=$source->getDefinition();
            $this->_definition["FIELDS"]=$fields["FIELDS"];

        }
        parent::__construct($this->_definition);
        $this->_initialize($params);
        $this->initialize();
    }
    abstract function initialize();
    // Los parametros recibidos en la llamada a la funcion son, por orden de prioridad
    // 1) Parametros fijos establecidos en la definicion.
    // 2) Parametros incrustados en el path (y no en la query string)
    // 3) Parametros obtenidos por GET
    private function _initialize($parameters)
    {
        // Parametros recibidos por GET
        $getData=\Registry::$registry["params"];
        // Sobre ellos, sobreescribimos lo recibido en parameyters
        if(is_array($getData))
            $fullData=array_merge($getData,$parameters);


        foreach($this->_definition["FIELDS"] as $getKey=>$getDef)
        {
                if( !isset($fullData[$getKey]))
                {
                    if($getDef["REQUIRED"] )
                    {
                        throw new WebPageException(WebPageException::ERR_REQUIRED_PARAM,array("name"=>$getKey));
                    }
                    else                    
                        unset($this->_definition["FIELDS"][$getKey]);                                           
                }
                else
                {

                    if(is_object($fullData[$getKey]))
                    {
                       $curVal=$fullData[$getKey]->getValue();
                    }
                    else
                    $curVal=$fullData[$getKey];

                    \lib\model\types\TypeFactory::unserializeType($this->{"*".$getKey},$curVal,"HTML",($getDef["PARAMTYPE"]=="DYNAMIC"?0:1));
                }
        }

    }
    function getName()
    {
        return $this->name;
    }
    function render($renderType, $requestedPath, $outputParams)
    {
        $fileType = ucfirst($renderType) . 'Renderer';
        include_once(LIBPATH.'/output/html/renderers/'.$fileType.'.php');
        $className = "\\lib\\output\\html\\renderers\\".$fileType;
        $renderer = new $className();
        $renderer->render($this, $requestedPath, $outputParams);
    }
    function getSite()
    {
        return $this->site;
    }

    // Este metodo puede ser sobreescrito por las clases Page
    function onUserNotLogged($outputType)
    {
        switch($outputType)
        {
            case "html":
            {
                header("Location: /");
                die();
            }break;
            case "json":
            {
                die(json_encode(array("result"=>0,"error"=>2)));
            }
        }
    }

    function checkPermissions($user,$modelInstance)
    {        
        $permManager=\Registry::getPermissionsManager();
        $perms=$this->_definition["PERMISSIONS"];       
        
        if(!$permManager->canAccessModel( $modelInstance,$perms,$user?$user->getId():null))
                throw new WebPageException(WebPageException::ERR_UNAUTHORIZED);                        
    }

    function getLayout()
    {
        $currentProject=$this->site->getProject();
        $currentSite=$currentProject->getCurrentSite();

        if(isset($this->_definition["LAYOUT"]))
            $layout=$this->_definition["LAYOUT"];
        else
        {
            $pagePath=$currentSite->getPagePath($this->name);
            $layout=$pagePath."/".$this->name."Layout.wid";
            if(!is_file($layout))
            {
                // No existe el layout.Se mira si lo que existe es el layout del site base, en caso de que
                // el site actual no lo sea.
                if($currentSite->isDefaultSite())
                {
                    throw new WebPageException(WebPageException::ERR_NO_LAYOUT);
                }
                // Se le pide esta pagina al site por defecto
                $layout=$currentProject->getDefaultSite()->getPagePath($this->name)."/".$this->name."Layout.wid";
            }
        }

        if($currentProject->templatePreviewMode())
        {
            $newlayout=str_replace(".php","",$layout)."_work.php";
            if(is_file($newlayout))
                $layout=$newlayout;
        }
        return $layout;
    }
    function getWidgetPath()
    {
        $curProject=$this->site->getProject();
        $currentSite=$curProject->getCurrentSite();

        $baseDir=\lib\project\PathMap::toRelative($currentSite->getPagePath($this->name));

        // Path base : el de la pagina, mas el del site.
        $widgetPath=array(
            $baseDir."/widgets",
            $baseDir);
        $sitePaths=$currentSite->getWidgetPath();
        $widgetPath=array_merge($widgetPath,$sitePaths);

        if(!$currentSite->isDefaultSite())
        {
            $defSite=$this->site->getProject()->getDefaultSite();
            $defSitePageDir=\lib\project\PathMap::toRelative($defSite->getPagePath($this->name));
            $widgetPath[]=$defSitePageDir."/widgets";
            $widgetPath[]=$defSitePageDir;
        }
        $widgetPath=array_merge($widgetPath,$curProject->getWidgetPath());

        if($curProject->templatePreviewMode())
        {
            array_unshift($widgetPath,$baseDir."/widgets_work");
        }

        if(isset($this->_definition["WIDGETPATH"]))
        {
            for($k=count($this->_definition["WIDGETPATH"])-1;$k>=0;$k--)
                array_unshift($widgetPath,$this->_definition["WIDGETPATH"][$k]);
        }

        return $widgetPath;
    }
    function getCacheFile()
    {
        $project=$this->site->getProject();
        $lang=\Registry::retrieve(\Registry::USER_LANGUAGE_ISO);
        return $project->getCurrentSite()->getCacheDir()."/pages/".$lang."/".$this->name.".php";
    }
}

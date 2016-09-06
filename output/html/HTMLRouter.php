<?php
namespace lib\output\html;
class HTMLRouter
{
   static $urlParser;
   static $routingVariables;
   static $routerListeners;
   const URL_PARSED="onUrlParsed";
   const STARTUP="onStartup";
   const PAGENOTFOUND="onPageNotFound";
   const PAGEEXCEPTION="onPageException";
   const CLEANUP="onCleanup";

    function __construct($project)
    {
        $this->project=$project;
    }

    function route($request)
    {
        $renderType = $request->getOutputType();
        include_once(LIBPATH."/UrlResolver.php");
        $resolver=new \UrlResolver(
            $this->project,
            $this->project->getCacheDir()."/routes.srl"
        );
        $resolver->resolve($request);
        /*
        $subpath=$request->getRequestedPath();


        include_once(WEBROOT."/Website/".$target.".php");
        $namespaced=str_replace("/","\\",trim($target,"/"));
        $className="Website\\".$namespaced;
                
        // La validacion de la pagina debe ser tanto de parametros como de permisos.
        $page=new $className();
        $page->initialize($request,$urlInfo["PARAMS"]);
        \Registry::$registry["currentPage"]=$page;
        //$page->validate();

        $extraParams = array();
        $extraParamsAllowed=array('output_params', 'filtering_datasources');
        foreach($extraParamsAllowed as $epa) {
            if ($urlInfo['PARAMS'][$epa]) {
                $extraParams[$epa] = $urlInfo['PARAMS'][$epa];
            }
        }
        $page->render($renderType, $requestedPath, $extraParams);*/
    }

    function resolveActions()
    {

        $object=\Registry::$registry["action"]["object"];
        $actionName=\Registry::$registry["action"]["name"];
        if($actionName=="" || $object=="")
            return; // TODO : Redirigir a pagina de error.

        $curForm=\lib\output\html\Form::getForm($object,$actionName,\Registry::$registry["action"]["keys"]);
        $curForm->process();
    }
    function routeToReferer()
    {
        header("Location: ".\Registry::$registry["client"]["referer"]);        
    }
}
?>

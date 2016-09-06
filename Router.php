<?php
namespace lib\html;
        class Router
        {
            /*static $urlParser;
            static $routingVariables;
            static $routerListeners;
            const URL_PARSED="onUrlParsed";
            const STARTUP="onStartup";
            const PAGENOTFOUND="onPageNotFound";
            const PAGEEXCEPTION="onPageException";
            const CLEANUP="onCleanup";
            */

            static function route($request)
            {
                $className=get_class($request);
                switch($className)
                {
                    case '\lib\output\html\HTMLRequest':
                    {
                        \lib\output\html\HTMLRouter::route($request);
                    }break;
                }
            }

            /*
            static function route($subDomain,$request)
            {
                  $requestedPath=trim($request["subpath"],"/");
                                    
               //try{
                      Router::$urlParser=$subDomain->getUrlMapping();
                  
                   $urlInfo=Router::$urlParser->processUrl($requestedPath);
                   Router::onEvent(Router::URL_PARSED,array($urlInfo));

                   // Any variable not declared in the CANDIDATE/PARAMS, is suppossed to be a "routing variable", that
                   // will be automatically added to any link build.
                   foreach($urlInfo["PARAMS"] as $key=>$value)
                   {
                       if( !isset($urlInfo["CANDIDATE"]["PARAMS"][$key]) )
                           Router::$routingVariables[$key]=$value;
                   }
                   
                   $request->setResolvedUrl($urlInfo["CANDIDATE"],$urlInfo["PARAMS"]);

                 //  }catch(Exception $e)
                 //  {
                 //      echo "unknown page";
                 //      exit();
                 //  }
                   $layout=$urlInfo["CANDIDATE"]["LAYOUT"];
                   

                   if( $layout )
                   {                       
                       Router::onEvent(Router::STARTUP,array($urlInfo));

                       include_once("lib/templating/TemplateParser2.php");
                       include_once("lib/templating/TemplateHTMLParser.php");
                       include_once("lib/templating/Plugin.php");
                       include_once("lib/templating/html/plugins/L.php");
                       include_once("lib/templating/html/plugins/CSS.php");
                       include_once("lib/templating/html/plugins/SCRIPT.php");

                       $oLParser=new \CLayoutHTMLParserManager();
                       $widgetPath=array(PROJECTPATH."/site/widgets",PLATFORMPATH."/platform/widgets",PROJECTPATH."/site",PLATFORMPATH."/platform");
                       $user=\Registry::$registry["user"];
                       $lang=$user->getLanguage();                       
                       $oManager=new \CLayoutManager("html",$widgetPath,array("L"=>array("lang"=>$lang)),$lang);
                       $definition=array("LAYOUT"=>PROJECTPATH."/site/layouts/".$layout.".wid");  
                       try
                       {  
                           Router::onEvent(Router::STARTUP,array($urlInfo));          
                           $oManager->renderLayout($definition,$oLParser,null,true);  
                           Router::onEvent(Router::CLEANUP,array($urlInfo));          
                       }
                       catch(Exception $e)
                       {
                           Router::onEvent(Router::PAGEEXCEPTION,array($urlInfo));                           
                       }
                   }
                }

            static function buildLink($name,$params=null)
            {       
                // If any variable exists in the routing variables, but they're not present in the $params sent, 
                // they're re-set using the values got from the original request.
                
                foreach(Router::$routingVariables as $key=>$value)
                {
                    if( !isset($params[$key]) )
                        $params[$key]=$value;
                }                            
                return "http://".Router::$urlParser->build($name,$params);
            }
            static function addRoutingVariable($name,$value)
            {
                Router::$routingVariables[$name]=$value;
            }
            static function addEventListener($obj)
            {
                Router::$routerListeners[]=$obj;
            }
            static function onEvent($eventName,$args)
            {
                if( !Router::$routerListeners ) return;
                foreach(Router::$routerListeners as $value)
                    call_user_func_array(array($value,$eventName),$args);
            }*/
        } 
?>

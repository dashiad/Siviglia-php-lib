<?php
namespace lib\output\html\renderers;

class HtmlRenderer
{
    public function render($page, $requestedPath, $outputParams, $language = null)
    {
        $site=$page->getSite();

         \Registry::$registry["PAGE"]=$page;

         $widgetPath=$page->getWidgetPath();
         $layout=$page->getLayout();
         $cacheFile=$page->getCacheFile();

         foreach($widgetPath as $key=>$value)
             $widgetPath[$key]=PROJECTPATH."/".$value;

         include_once(LIBPATH."/output/html/templating/TemplateParser2.php");
         include_once(LIBPATH."/output/html/templating/TemplateHTMLParser.php");

        $request=\Registry::getRequest();

        $plugins = array(
            "L"=>array("lang"=>$language),
            "DEPENDENCY"=>array(
                "MACROS"=>array(
                    "##SITE_DOCUMENT_ROOT##"=>$site->getDocumentRoot(),
                    "##SITE_WEB_ROOT##"=>$site->getCanonicalUrl(),
                    "##STATICS_DOCUMENT_ROOT##"=>$site->getStaticsSite()->getDocumentRoot(),
                    "##STATICS_WEB_ROOT##"=>$site->getStaticsSite()->getCanonicalUrl()
                ),
                "BUNDLES"=>array(
                    "Global"=>"bundles"
                ),
                "DOCUMENT_ROOT"=>$site->getDocumentRoot(),
                "WEB_ROOT"=>$site->getCanonicalUrl(),
                "WIDGET_PATH"=>array("/")
            )
        );
         $oLParser=new \CLayoutHTMLParserManager();
         $oManager=new \CLayoutManager(PROJECTPATH."../","html",$widgetPath,$plugins);

         $definition=array("LAYOUT"=>$layout,
                          "CACHE_SUFFIX"=>"php",
                          "TARGET"=>$cacheFile);

         $oManager->renderLayout($definition,$oLParser,true);
        /*
         *       if ($this->isHTML()) {
            include_once(CUSTOMPATH."../backoffice/lib/output/html/templating/TemplateParser2.php");
            include_once(CUSTOMPATH."../backoffice/lib/output/html/templating/TemplateHTMLParser.php");

            $oLParser=new CLayoutHTMLParserManager();


            $oManager=new CLayoutManager(CUSTOMPATH."/../","html",$widgetPath,

                                array("L"=>array("lang"=>$namespace,"LANGPATH"=>CUSTOMPATH."/lib/templating/lang/"),
                                              "CSS"=>array("CSSPATH"=>CUSTOMPATH."html/"._CURRENTPAGE_WEBPATH_),
                                      "SCRIPT"=>array("SCRIPTPATH"=>CUSTOMPATH."html/"._CURRENTPAGE_WEBPATH_),
                                      "LINK"=>array("lang"=>$namespace)
                                ));


            $definition=array("TEMPLATE"=>$this->getLayout(),"TARGET"=>dirname(__FILE__)."/../cache/pages/$namespace/".$this->subDir."/".$this->pageName."/");
            if(!$this->definition["LAYOUT"])
            {
                $definition["PREFIX"]="[PAGE][_CONTENT]";
                $definition["PREFIX"]="[$namespace/PAGE][_CONTENT]";
                $definition["SUFFIX"]="[#][#]";
            }
            ini_set("error_reporting",E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
            ini_set("display_errors","on");
            ini_set("html_errors","on");
            $oManager->renderLayout($definition,$oLParser,true);

        */
    }
}

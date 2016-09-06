<?php
namespace lib\reflection\plugins;

class WebsiteProcessor extends \lib\reflection\SystemPlugin
{

    function BUILD_URLS($level)
    {
    
      if($level!=2)return;
      $this->paths=array();
      $this->configurationFile=new \lib\reflection\base\ConfigurationFile("Config", "", "\html",WEBROOT."/config.php");
      $this->iterateOnModels("generateIndexes");
      $this->iterateOnModels("generatePage");
      $this->saveUrlTree();

/*       printPhase("Generando Paths");
        // Se almacenan los paths        
       $pathHandler=new \lib\reflection\html\UrlPathDefinition($this->paths);
       $pathHandler->save();
       $this->configurationFile->save();*/
    }
    function generateIndexes($layer,$name,$model)
    {

        $normalized=$model->objectName->getNormalizedName();
        $this->paths["/".str_replace('\\','/',$normalized)]=array("NAME"=>str_replace('\\','_',$normalized),
                                                                   "LAYOUT"=>str_replace('\\','/',$normalized)."/index.wid");
        $this->paths["/admin/".str_replace('\\','/',$normalized)]=array("NAME"=>str_replace('\\','_',$normalized)."Admin",
                                                                   "LAYOUT"=>"admin/".str_replace('\\','/',$normalized)."/index.wid");

    }

    function saveUrlTree()
    {
        $ftree=array();    
        foreach($this->paths as $key=>$value)
        {
            $parts=explode("/",$key);
            array_shift($parts);
            $len=count($parts);
            $position=& $ftree;
            $k=0;
            for($k=0;$k<$len-1;$k++)
                $position=& $position["SUBPAGES"]["/".$parts[$k]];
            $position["SUBPAGES"]["/".$parts[$k]]=$value;
        }

       
       $pathHandler=new \lib\reflection\html\UrlPathDefinition($ftree["SUBPAGES"]);
       $pathHandler->save();
       $this->configurationFile->save();
    }
    function generatePage($layer,$name,$model)
    {
       $curLayer=$layer;
       $key=$name;
       $value=$model;
                        
       printSubPhase("Procesando acciones de $curLayer/$key");
       $actions=$value->getActions();       
       $datasources=$value->getDataSources();
       foreach($actions as $actionName=>$actionDef)
       {
           $configPath=$layer."/".$name."/forms";
           if(!$this->configurationFile->mustRebuild($name."_views",$actionName,$configPath))
               continue;
           // No se generan paginas para acciones que se basan en relaciones multiples.
           if($actionDef->getTargetRelation())
                continue;
           
          printItem("Procesando $actionName");                    
          $webPage=new \lib\reflection\html\pages\FormWebPageDefinition();
          $webPage->create($actionName,$actionDef,$key,$value);
          
          $path=$webPage->getPath();
          $this->paths[$path]=$webPage->getPathContents();
          $extra=$webPage->getExtraPaths();
          if($extra)
              $this->paths=array_merge($this->paths,$extra);          
          $webPage->save();
          $model->addActionPage($webPage,$actionDef);
      }
      printSubPhase("Generando Listados de $curLayer/$key");
      foreach($datasources as $dsName=>$dsDef)
      {
          $configPath=$layer."/".$name."/views";
          if(!$this->configurationFile->mustRebuild($name."_views",$dsName,$configPath))
               continue;

          printItem("Procesando $dsName");
          $webPage=new \lib\reflection\html\pages\ViewWebPageDefinition();
          $webPage->create($dsName,$dsDef,$key,$value);

          $path=$webPage->getPath();
          $this->paths[$path]=$webPage->getPathContents();
          $extra=$webPage->getExtraPaths();
          if($extra)
              $this->paths=array_merge($this->paths,$extra);

          $webPage->save();                
          $model->addDatasourcePage($webPage,$dsDef);
       }
    }
/*
        \lib\reflection\WebPage::generateDefaultPages();
        */
}
?>

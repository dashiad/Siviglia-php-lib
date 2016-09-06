<?php
  namespace lib\reflection\classes;
  class WebPageDefinition extends BaseDefinition
  {

      function __construct($definition,$code,$regenDef,$regenCode,$isadmin)
      {
          $this->definition=$definition;
          $this->code=$code;
          $this->regenDef=$regenDef;
          $this->regenCode=$regenCode;
          $this->isadmin=$isadmin;
          $this->setupPath();
      }
      function setupPath()
      {
          // Primero hay que ver si esta pagina depende de algun objeto.
          if(!$this->definition["OBJECT"])
          {
              $subPath=array("/".$this->definition["NAME"]=>array());
                // Si no lo es, su path es directamente su nombre.
                if(!$this->isadmin)
                {
                    $this->path=$subPath;
                }
                else
                {
                    $this->path=array("/admin"=>array('SUBPAGES'=>$subPath));
                }
                return;
          }
          $isViewDef=false;
          
          if($this->definition["SOURCES"])
          {
              if(!\lib\php\ArrayTools::isAssociative($this->definition["SOURCES"]))
              {
                  $firstDef=$this->definition["SOURCES"][0];
                  
                  if($firstDef["ROLE"]=="view" && ((!$this->isadmin && $firstDef["NAME"]=="View") ||
                                                    ($this->isadmin && $firstDef["NAME"]=="AdminView")))
                  {
                      $isViewDef=true;                      
                  }
              }
          }
          // Si existe un objeto, ese es el path inicial.
          $path=$this->definition["OBJECT"];

          // Se recogen todos los parametros requeridos, y se mira si pertenecen a un modelo, en cuyo caso,
          // hay que ver si completan la clave requerida de dicho modelo, para incluir ese modelo en el path, y en 
          // el calculo de permisos.
          $probableKeys=array();
          if( $this->definition["FIELDS"] )
          {
              foreach($this->definition["FIELDS"] as $pKey=>$pValue)
              {
                if(!$pValue["REQUIRED"])continue;
                if(!$pValue["MODEL"])continue;
                
                $objName=new \lib\reflection\classes\ObjectDefinition($pValue["MODEL"]);
                $field=$pValue["FIELD"];
                $probableKeys[$objName->className][]=$field;
                // Se almacena el nombre que se le ha dado al parametro.
                $paramNames[$objName->className."_".$field]=$pKey;
              }
          }
          $objects=array_keys($probableKeys);
          // Si no se han encontrado parametros...
          if(count($objects)==0)
          {
              
                $base=array("/".$this->definition["OBJECT"]=>array("SUBPAGES"=>array("/".$this->definition["NAME"]=>array())));
                if(!$this->isadmin)
                    $this->path=$base;
                else
                    $this->path=array("/admin"=>array('SUBPAGES'=>$base));
                return;
           
          }
          $subPaths=array();
          // Si habia parametros, hay que encontrar de que modelos son, y si completan las keys de dichos modelos.
          foreach($probableKeys as $key=>$value)
          {
              // Se carga la definicion del modelo.
              $modelDef=\lib\model\types\TypeFactory::getObjectDefinition($key);
              $indexes=$modelDef["INDEXFIELD"];
              $diff=array_diff($value,$indexes);
              if(count($diff)==0)
              {                          
                $paramPath=array();
                foreach($value as $fieldName)
                {
                    $paramName=$paramNames[$key."_".$fieldName];
                    $this->definition["MODELIDS"][$key][]=$paramName;
                    $paramPath[]="{".$paramName."}";        
                }
                $newPath=implode("/",$paramPath);

                // No habia diferencias.Se tienen todas las keys de este modelo.
                // Lo siguiente a saber si este modelo es el del que deriva esta pagina. Es decir, si esta pagina
                // es el formulario "Edit" de "Section", hay que saber si esta key completa es del mismo objeto Section,
                // o de cualquier otro, ya que si es de Section, debe aparecer en primer lugar.
                if($key!=$this->definition["OBJECT"])
                {
                    $newPath=$key."/".$newPath;    
                    $subPaths[]=$newPath;
                }
                else
                    array_unshift($subPaths,$newPath);                
              }
          }

          if( count($subPaths)>0 )
          {
              // Se han encontrado parametros que son indices
              $fullSubPath=implode("/",$subPaths)."/".$this->definition["NAME"];
          }
          else
          {
              // Se han encontrado parametros, pero no son indices.
              $fullSubPath=$this->definition["NAME"];
          }
          $subPathArray=array("/".$fullSubPath=>array());
          if($isViewDef)
          {
              $subPathArray["/".implode("/",$paramPath)."/*"]=array("PATH"=>"/".$fullSubPath);
          }
          
          $subPath=array("/".$this->definition["OBJECT"]=>array("SUBPAGES"=>$subPathArray));
          if($this->isadmin)
          {
              $this->path=array("/admin"=>array('SUBPAGES'=>$subPath));
              
          }
          else
            $this->path=$subPath;
      }
      function getPath()
      {
        return $this->path;
      }

      function generateFromAction($actionName,$actionDef,$key,$value)
      {
          // Un action, en principio, no tiene parametros.
          $parentModel=$actionDef->parentModel;
          $isadmin=$actionDef->isAdmin();
          $path="/".$key."/".$actionName;
          if( $isadmin )
          {
              $path="/admin".$path;
          }
          $form=$actionDef->getForm();
          $def=$actionDef->getDefinition();
          
          
          
          
          
          // El tema de los parametros funciona asi:
          
          $def=array(
              "NAME"=>$actionName,    
              "TYPE"=>"HTML",
              "OBJECT"=>$key,
              "CACHING"=>array(
                  "TYPE"=>"NO-CACHE"
                  ),
              "ENCODING"=>"utf8",    
              "ADD_STATE"=>array(),
              "REQUIRE_STATE"=>array(),
              "REMOVE_STATE"=>array(),
              "LAYOUT"=>array($path.".wid"),
              "PERMISSIONS"=>$def["PERMISSIONS"],
              "FIELDS"=>$def["INDEXFIELD"]?$def["INDEXFIELD"]:array(),
              "PATH"=>$path,
              "SOURCES"=>array(array('ROLE'=>'action',"OBJECT"=>$key,"NAME"=>$actionName)),
              "WIDGETPATH"=>array(
                  "/html/Website"
                  )              
              );
          global $APP_NAMESPACES;
          foreach($APP_NAMESPACES as $val)
              $def["WIDGETPATH"][]="/$val/objects";
          $def["WIDGETPATH"][]="/output/html/Widgets";



          // Setup de los parametros.
          // Los parametros se toman del formulario.Si hay indexFields, estos son parametros de la pagina formulario, y,
          // ademas, deben formar parte del path de esta pagina dentro de la web.
         $formCodePath=$form->getWidgetPath();
         $objName=$actionDef->parentModel->objectName->className;
         if($isadmin)
         {
            $prefix="ADMIN";
            $prefix2="admin";
            
            $pageprefix="/admin/".$objName."/ADMIN";
         }
         $code="[*".$pageprefix."PAGE]\n\t[_CONTENT]\n\t\t[*".$formCodePath."][#]\n\t[#]\n[#]";
         
         
         return new WebPageDefinition($def,$code,
                                $parentModel->config->mustRebuild($prefix2."webpage",$actionName,WEBROOT."/".$path.".php"),
                                $parentModel->config->mustRebuild($prefix2."webpageWidget",$actionName,WEBROOT."/".$path.".wid"),$isadmin
                  );

      }
      
      function getDefinition()
      {
          return $this->definition;
      }
      function save()
      {
          
          if($this->regenDef)
          {
          $basePath=dirname($this->definition["PATH"]);
          $path=WEBROOT."/Website".$basePath;
          $this->saveFile($path,
                                      "Website".str_replace("/","\\",$basePath),
                                      $this->definition["NAME"]);
          }
          if($this->regenCode)
            file_put_contents($path."/".$this->definition["NAME"].".wid",$this->code);
      }

       function saveFile($dir,$namespace,$className,$extends=null,$defVar=null)
       {
           $text="<?php\r\n\tnamespace ".$namespace.";\nclass $className extends \\lib\\output\\html\\WebPage\n".
                      " {\n".
                      "        var \$definition=";                                      
             $text.=$this->dumpArray($this->getDefinition(),5);
             $text.=";\n}\n?>";             
             @mkdir($dir,0777,true);
             file_put_contents($dir."/".$className.".php",$text);
       }

      function parse()
      {
            include_once(LIBPATH."/output/html/templating/TemplateParser.php");
            include_once(LIBPATH."/output/html/templating/TemplateHTMLParser.php");
  
            $oLParser=new \CLayoutHTMLParserManager("sampleLayout");

            $widgetPath=$this->definition["WIDGETPATH"];

            $oManager=new \CLayoutManager("html",$widgetPath,array("L"=>array("lang"=>"en")));

            
            $definition=array("LAYOUT"=>WEBROOT."/Website/".$this->definition["PATH"].".wid",
                              "CACHE_SUFFIX"=>"php");
  

            $oManager->renderLayout($definition,$oLParser,false);
      }

      
      function generateFromDataSource($dsName,$dsDef,$key,$value)
      {
          if($dsDef->isAdmin())
            $isadmin=true;
          // Un action, en principio, no tiene parametros.
          $parentModel=$dsDef->parentModel;
          $path="/".$key."/".$dsName;
          
          if( $isadmin )
          {
              $path="/admin".$path;
          }       
          $origDef=$dsDef->getDefinition();

          $def=array(
              "NAME"=>$dsName, 
              "OBJECT"=>$key,   
              "TYPE"=>"HTML",
              "CACHING"=>array(
                  "TYPE"=>"NO-CACHE"
                  ),
              "ENCODING"=>"utf8",    
              "ADD_STATE"=>array(),
              "REQUIRE_STATE"=>array(),
              "REMOVE_STATE"=>array(),
              "LAYOUT"=>array($path."_layout.php"),
              "PERMISSIONS"=>$origDef["PERMISSIONS"],
              "SOURCES"=>array(array('ROLE'=>$dsDef->getRole(),"OBJECT"=>$key,"NAME"=>$dsName)),
              "FIELDS"=>$origDef["PARAMS"]["FIELDS"]?$origDef["PARAMS"]["FIELDS"]:array(),
              "PATH"=>$path,
              "WIDGETPATH"=>array(
                  "/html/Website"
              )
          );
          global $APP_NAMESPACES;
          foreach($APP_NAMESPACES as $val)
              $def["WIDGETPATH"][]="/$val";
          $def["WIDGETPATH"][]="/output/html/Widgets";
          $listCodePath=$dsDef->getListCodePath($isadmin);
          if($isadmin)
          {
            $prefix="ADMIN";
            $prefix2="admin";
            $pageprefix="/admin/".$dsDef->parentModel->objectName->className."/ADMIN";
          }
          
          $code="[*".$pageprefix."PAGE]\n\t[_CONTENT]\n\t\t[*".$listCodePath."][#]\n\t[#]\n[#]";
          return new WebPageDefinition($def,$code,
                                $parentModel->config->mustRebuild($prefix2."webpage",$dsName,WEBROOT."/".$path.".php"),
                                $parentModel->config->mustRebuild($prefix2."webpageWidget",$dsName,WEBROOT."/".$path.".wid"),$isadmin
                  );

      }
  }

?>

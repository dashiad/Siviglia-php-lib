<?php
  namespace lib\reflection\html\pages;
  abstract class WebPageDefinition extends \lib\reflection\base\BaseDefinition
  {
      function __construct()
      {          
      }
      abstract function create($dsName,$dsDef,$key,$value);
      
      abstract function getCodeManager();

      function initialize($name,$srcObject,$definition)
      {

          $this->srcObject=$srcObject;
          $this->parentModel=$srcObject->parentModel;

          $this->name=$name;
          $parentClassName=$this->srcObject->parentModel->objectName->getNormalizedName();
          $this->filePath=WEBROOT."/Website/".$parentClassName."/".$this->srcObject->getName().".php";

          $this->definition=$definition;
          $this->setupPath();
          $basePath=dirname($this->definition["PATH"]);
          $this->filePath=WEBROOT."/Website".$basePath;
          $this->isAdmin=$srcObject->isAdmin();
      }
      function isAdmin()
      {
          return $this->isAdmin;
      }
      function getName()
      {
          return $this->name;
      }
      
      function setupPath()
      {
          $this->path="";
          $pathContents=array();
          if(isset($this->definition["FIELDS"]))
              $pathContents["PARAMS"]=$this->definition["FIELDS"];
          $pathContents["LAYOUT"]=$this->definition["LAYOUT"];
          
          
          $this->pathContents=$pathContents;
          // Primero hay que ver si esta pagina depende de algun objeto.
          if(!$this->definition["OBJECT"])
          {
              $pathContents["NAME"]=$this->definition["NAME"];
              $this->path="/".$this->definition["NAME"];              
              return;
          }
          else
          {
          
             $this->pathContents["NAME"]=str_replace("\\","/",$this->definition["OBJECT"])."_".$this->definition["NAME"];
          }
          $srcModel=\lib\reflection\ReflectorFactory::getModel($this->definition["OBJECT"]);
          $isPrivate=$srcModel->objectName->isPrivate();
          
          
          // Si es una pagina de administracion, se poner por delante "admin".
          if($this->srcObject->isAdmin())
              $this->path="/admin".$this->path;
          
          $isViewDef=false;
          // If there's an object, the name of this page will be composed by ObjectName-View/List/Form name
          // This is dangerous, as duplicates may appear.
                    
          if($this->definition["SOURCES"])
          {
              if(!\lib\php\ArrayTools::isAssociative($this->definition["SOURCES"]))
              {
                  $firstDef=$this->definition["SOURCES"][0];
                  
                  if($firstDef["ROLE"]=="view" && ((!$this->srcObject->isAdmin() && $firstDef["NAME"]=="View") ||
                                                    ($this->srcObject->isAdmin() && $firstDef["NAME"]=="AdminView")))
                  {
                      $isViewDef=true;                      
                  }
              }
          }          
          // Se recogen todos los parametros requeridos, y se mira si pertenecen a un modelo, en cuyo caso,
          // hay que ver si completan la clave requerida de dicho modelo, para incluir ese modelo en el path, y en 
          // el calculo de permisos.
          $probableKeys=array();
          $myFields=$this->definition["FIELDS"];
          // TARGET_RELATION existe en caso de que se edite una relacion multiple.
          // En ese caso, los campos indices no apuntan al objeto local, sino a la tabla intermedia
          // de relacion.En este caso, el path a esta pagina incluye las keys propias del modelo actual
              if( $this->definition["FIELDS"] )
              {
                  foreach($this->definition["FIELDS"] as $pKey=>$pValue)
                  {
                    if(!$pValue["REQUIRED"])continue;
                    if(!$pValue["MODEL"])continue;
                    $objName=new \lib\reflection\model\ObjectDefinition($pValue["MODEL"]);
                    $field=$pValue["FIELD"];           
                    $probableKeys[$objName->getNormalizedName()][]=$field;
                    // Se almacena el nombre que se le ha dado al parametro.
                    $paramNames[$objName."_".$field]=$pKey;
                  }
              }
          // De principio, el path es el objeto que esta en la definicion.
          // Luego, en caso de que se encuentren keys, se reordena.
          $this->path="/".str_replace("\\","/",$this->definition["OBJECT"]);
          $objects=array_keys($probableKeys);
          // Si no se han encontrado parametros...
          
          
          if(count($objects)==0)
          {   
              $this->path.="/".$this->definition["NAME"];
              $this->path=$this->simplifyPath($this->path);              
              return;                            
          }

          $subPaths=array();
          // Si habia parametros, hay que encontrar de que modelos son, y si completan las keys de dichos modelos.

          foreach($probableKeys as $key=>$value)
          {
              
              if($isPrivate)
                  {
                  $n=1;
                    var_dump($probableKeys["FIELDS"]);
                  }
              // Se carga la definicion del modelo.
              $modelDef=\lib\reflection\ReflectorFactory::getModel($key);
              $field=$modelDef->getField($value[0]);
              $hasFullKey=false;
              if($field->isUnique())
                  $hasFullKey=true;
              else
              {
                  $indexes=array_keys($modelDef->getIndexFields());
                  $nIndexes=count($indexes);
                  $diff=array_intersect($value,$indexes);
                  $hasFullKey=(count($diff)==count($nIndexes));
              }

              if($hasFullKey)
              { 
                $paramPath=array();
                foreach($value as $fieldName)
                {
                    $paramName=$paramNames[$key."_".$fieldName];
                    $this->definition["MODELIDS"][$key][]=$paramName;
                    $paramPath[]="{".$paramName."}";        
                }
                $newPath=implode("/",$paramPath);
                $fullKeys[$key]=$newPath;
                // No habia diferencias.Se tienen todas las keys de este modelo.
                // Lo siguiente a saber si este modelo es el del que deriva esta pagina. Es decir, si esta pagina
                // es el formulario "Edit" de "Section", hay que saber si esta key completa es del mismo objeto Section,
                // o de cualquier otro, ya que si es de Section, debe aparecer en primer lugar.                
              }              
          }

          if($isPrivate)
                  {
                  $n=1;
                  var_dump($this->definition["FIELDS"]);
                  }
          if( count($fullKeys)==0 )
          {
              // Se han encontrado parametros que son indices
             $this->path.="/".$this->definition["NAME"];
          }
          else
          {
              $this->path="";
              if($isPrivate)
              {
                  $parentNamespaceModel=$srcModel->objectName->getNamespaceModel();
                  $parentNamespaceName=new \lib\reflection\model\ObjectDefinition($parentNamespaceModel);
                  $parentNamespaceFull=$parentNamespaceName->getNormalizedName();
                  $this->path="/".$parentNamespaceName->className;
                  if($fullKeys[$parentNamespaceFull])
                      $this->path.="/".$fullKeys[$parentNamespaceFull];
              }
              $this->path.="/".$srcModel->objectName->className;
              $fullSrcName=$srcModel->objectName->getNormalizedName();
              if($fullKeys[$fullSrcName])
                  $this->path.="/".$fullKeys[$fullSrcName];                  
              $this->path.="/".$this->definition["NAME"];
          }
          $this->path=$this->simplifyPath($this->path);
          
          if($isViewDef)
              $this->extraPaths["/".implode("/",$paramPath)."/*"]=array("PATH"=>"/".$fullSubPath);
      }
      

      function hasCompleteKey($modelName,& $normalizedParams,& $path,& $extraParams)
      {
              $model=\lib\reflection\ReflectorFactory::getModel($modelName);
              $indexes=array_keys($model->getIndexFields());
              $namespaced=$model->objectName->getNamespaced();

              $path="/".$model->objectName->className;

              if(!isset($normalizedParams[$namespaced]))
                  return false;
              $subKeys=& $normalizedParams[$namespaced];
              foreach($indexes as $value)
              {
                  if(!isset($subKeys[$value]))
                      return false;
              }
              
              foreach($indexes as $value)
              {
                  $path.="/{".$subKeys[$value]."}";
                  unset($subKeys[$value]);
              }              
              $extraParams=$subKeys; 
              return true;
      }
      // Este metodo es para objetos privados.Obtiene el campo del objeto privado, que apunta al objeto
      // publico, y que es una relacion del tipo "BELONGS_TO"
      function getBelongsToNamespaceField($model)
      {
          $publicModelName=$model->objectName->getNamespaceModel();
          $publicModel=\lib\reflection\ReflectorFactory::getModel($publicModelName);
          $indexes=array_keys($publicModel->getIndexFields());

          $fields=$model->getFields();
          foreach($fields as $key=>$value)
          {
                  if(!$value->isRelation())
                      continue;
                  $role=$value->getRole();
                  $remModelName=$value->getRemoteModelName();
                  $equals=$publicModel->objectName->equals($remModelName);
                  $diff=array_diff($indexes,$value->getRemoteFieldNames());
                  if($value->getRole()=="BELONGS_TO" && $equals 
                     && count($diff)==0)
                  {                      
                      return array($key=>array("MODEL"=>$value->getRemoteModelName(),
                                               "FIELD"=>$indexes[0],
                                               'REQUIRED'=>1,
                                               'MAP_TO'=>$key));
                  }                  
          }
          return null;
      }

      function simplifyPath($path)
      {
          /*$suffixes=array("/FullList","/AdminFullList","/AdminView","/View","Ds","Action");
          foreach($suffixes as $cur)
          {
              $len=strlen($cur);
              if(substr($path,-$len)==$cur)
                  $path=substr($path,0,-$len);
          }*/
          return $path;
      }

      function getPath()
      {
        return $this->path;
      }
      function getPathContents()
      {
          return $this->pathContents;
      }
      function getExtraPaths()
      {
          return $this->extraPaths;
      }
      
      function getDefinition()
      {
          return $this->definition;
      }
      function setDefinition($def)
      {
          $this->definition=$def;
      }
      function save()
      {
          if($this->srcObject->isAdmin())          
          {
              $admin="admin/";
              $adminNamespace="admin\\";
          }
          $parentClassName=$this->srcObject->parentModel->objectName->getNormalizedName();

          $this->filePath=WEBROOT."/Website/".$admin.$parentClassName."/".$this->definition["NAME"].".php";
          $namespace="Website\\".$adminNamespace.$parentClassName;
          $this->saveFile($this->filePath,
                          $namespace,
                             $this->definition["NAME"]);

          $this->getCodeManager()->save();
      }
      function getFilePath()
      {
          return $this->filePath;
      }
      function getDestinationFile()
      {
          return $this->destinationFile;
      }
       function saveFile($dir,$namespace,$className,$extends=null,$defVar=null)
       {
           $dir=str_replace('\\','/',$dir);
           $this->destinationFile=$dir;
           echo "GUARDANDO EN PATH::$dir<br>";
           $text="<?php\r\n\tnamespace ".$namespace.";\nclass $className extends \\lib\\output\\html\\WebPage\n".
                      " {\n".
                      "        var \$definition=";                                      
             $text.=$this->dumpArray($this->getDefinition(),5);
             $text.=";\n}\n?>";             
             @mkdir(dirname($dir),0777,true);
             file_put_contents($dir,$text);
       }

      function parse()
      {
            include_once(LIBPATH."/output/html/templating/TemplateParser.php");
            include_once(LIBPATH."/output/html/templating/TemplateHTMLParser.php");
  
            $oLParser=new \CLayoutHTMLParserManager();

            $widgetPath=$this->definition["WIDGETPATH"];

            $oManager=new \CLayoutManager("html",$widgetPath,array("L"=>array("lang"=>DEFAULT_LANGUAGE)));

            
            $definition=array("LAYOUT"=>WEBROOT."/Website/".$this->definition["PATH"].".wid",
                              "CACHE_SUFFIX"=>"php");
  

            $oManager->renderLayout($definition,$oLParser,false);
      }

            
  }

?>

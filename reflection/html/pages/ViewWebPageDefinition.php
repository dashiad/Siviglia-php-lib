<?php
  namespace lib\reflection\html\pages;
  class ViewWebPageDefinition extends WebPageDefinition
  {  
      var $dsName;
      var $dsDef;
      var $codeManager=null;          
      function create($dsName,$dsDef,$key,$value)
      {
          $this->dsName=$dsName;
          $this->dsDef=$dsDef;


          if($dsDef->isAdmin())
            $isadmin=true;
          // Un action, en principio, no tiene parametros.
          $parentModel=$dsDef->parentModel;
          $this->parentModel=$parentModel;
          $objName=$parentModel->objectName;
          $path="";

          $path.="/".$key."/".$dsName;

          if( $isadmin )
          {
              $path="/admin".$path;
          }       
          $origDef=$dsDef->getDefinition();
          $indexes=isset($origDef["INDEXFIELDS"])?$origDef["INDEXFIELDS"]:array();
          $params=isset($origDef["PARAMS"])?$origDef["PARAMS"]:array();
          

          $role=$dsDef->getRole();

          

          $def=array(
              "NAME"=>$dsName,
              "OBJECT"=>$key,   
              "TYPE"=>"HTML",
              "CACHING"=>array(
                  "TYPE"=>"NO-CACHE"
                  ),
              'INHERIT_PARAMS_FROM_URL'=>true,
              "ENCODING"=>"utf8",    
              "ADD_STATE"=>array(),
              "REQUIRE_STATE"=>array(),
              "REMOVE_STATE"=>array(),
              "LAYOUT"=>$path.".wid",
              "PERMISSIONS"=>$origDef["PERMISSIONS"],
              "SOURCES"=>array(array('ROLE'=>$dsDef->getRole(),"OBJECT"=>$key,"NAME"=>$dsName)),
              "PATH"=>$path,
              "WIDGETPATH"=>array(
                  "/html/Website"
              )
          );
          global $APP_NAMESPACES;
          foreach($APP_NAMESPACES as $val)
              $def["WIDGETPATH"][]="/$val/objects";
          $def["WIDGETPATH"][]="/output/html/Widgets";
          if($objName->isPrivate())
          {
              $def["WIDGETPATH"][]="/".$objName->getLayer()."/objects/".$objName->getNamespaceModel()."/objects";
          }
          $this->initialize($dsName,$dsDef,$def);
          $this->codeManager=new ViewWebPageLayout($this,$dsName,$dsDef);
      }

      function getCodeManager()
      {
          return $this->codeManager;
      }          
      function setupPath()
      {
          //$actionName=$name
          //$actionDef=$srcObject
          //$definition=pageDefinition
          if(isset($this->definition["FIELDS"]))
              $pathContents["PARAMS"]=$this->definition["FIELDS"];
          $pathContents["INHERIT_PARAMS"]=array(
                "MODEL"=>$this->parentModel->objectName->getNamespaced(),
                "DATASOURCE"=>$this->dsName
          );
          $pathContents["LAYOUT"]=$this->definition["LAYOUT"];          
          $parentModel=$this->srcObject->parentModel;
          $parentName=$parentModel->objectName;          

          $pathContents["NAME"]=str_replace("\\","-",$parentName)."_".$this->definition["NAME"];
          $this->pathContents=$pathContents;

          $role=$this->srcObject->getRole();
          $srcDef=$this->srcObject->getDefinition();

          $params=array_merge(isset($srcDef["INDEXFIELDS"])?$srcDef["INDEXFIELDS"]:array(),
                              isset($srcDef["PARAMS"])?$srcDef["PARAMS"]:array());

          if($params)
          {
              foreach($params as $key=>$value)
              {
                  if($value["MODEL"])
                  {
                      $objName=new \lib\reflection\model\ObjectDefinition($value["MODEL"]);
                      $normalizedParams[$objName->getNamespaced()][$value["FIELD"]]=$key;
                  }
              }
          }


          $this->path="";
          if($this->srcObject->isAdmin())
              $this->path="/admin";

          $isPrivate=$parentName->isPrivate();

          if($isPrivate)
          {

              $this->path="/".$parentModel->objectName->getNamespaceModel();
          }

          $path="";
          $parentName=$parentModel->objectName;

          /*if($this->hasCompleteKey($parentName->getNormalizedName(),$normalizedParams,$path,$extraParams))
          {
              $ownParams=$normalizedParams[$parentName->getNamespaced()];
              $this->definition["MODELIDS"][$parentName->getNormalizedName()]=array_diff($ownParams,$extraParams);
              $this->path.=$path;
          }
          else
          {*/
              $this->path.="/".$parentName->className;
          //}
          $this->path.="/".$this->definition["NAME"];
          //$this->definition["PATH"]=$this->path;
          if($this->srcObject->getRole()=="view")
          {
              $this->extraPaths[$this->path."/*"]=array("PATH"=>$this->path);
          }
      }

  }

?>

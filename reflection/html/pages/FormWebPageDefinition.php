<?php
  namespace lib\reflection\html\pages;
  class FormWebPageDefinition extends WebPageDefinition
  {
      
      function create($actionName,$actionDef,$key,$value)
      {

          // Un action, en principio, no tiene parametros.

          $isadmin=$actionDef->isAdmin();
          $parentModel=$actionDef->parentModel;
          $objName=$parentModel->objectName;
          $path="";
          /*if($objName->isPrivate())
              $path="/".$objName->getNamespaceModel();
            */
          $path.="/".str_replace("\\","/",$key)."/".$actionName;
          if( $isadmin )
          {
              $path="/admin".$path;
          }
          
          $forms=$actionDef->getForms();
          $form=$forms[0];
          $def=$form->getDefinition();
          $origDef=$actionDef->getDefinition();
          /*                                
          // El tema de los parametros funciona asi:
          if($actionDef->getRole()=="Add")
          {
              // Si es un add, buscamos alguna relacion de tipo "BELONGS_TO".Si existe, 
              // obtenemos el campo, y lo ponemos como parametro.El path de este objeto va a cambiar.
              $fields=$parentModel->getFields();
              foreach($fields as $key=>$value)
              {
                  if(!$value->isRelation())
                      continue;
                  if($value->getRole()=="BELONGS_TO")
                  {
                      $remoteFields=$value->getRemoteFieldNames();

                      $def["INDEXFIELDS"][$key]=array("MODEL"=>$value->getRemoteModelName(),
                                                      "FIELD"=>$remoteFields[0],
                                                      'REQUIRED'=>1);
                  }
                  $path="/".str_replace("\\","/",$value->getRemoteModelName)."/{".$remoteFields[0]."}/".$parentModel->objectName->className."/Add";
                  if($isadmin)
                      $path="/admin".$path;
              }
          }*/
          
          $wdef=array(
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
              "LAYOUT"=>$path.".wid",
              "PERMISSIONS"=>$origDef["PERMISSIONS"],
              "FIELDS"=>$def["INDEXFIELDS"]?$def["INDEXFIELDS"]:array(),
              "PATH"=>$path,
              "SOURCES"=>array(array('ROLE'=>'action',"OBJECT"=>$key,"NAME"=>$actionName)),
              "WIDGETPATH"=>array(
                  "/html/Website"
              )
          );
          global $APP_NAMESPACES;
          foreach($APP_NAMESPACES as $val)
              $wdef["WIDGETPATH"][]="/$val/objects";
          $wdef["WIDGETPATH"][]="/output/html/Widgets";
          if($objName->isPrivate())
          {
              $wdef["WIDGETPATH"][]="/".$objName->getLayer()."/objects/".$objName->getNamespaceModel()."/objects";
          }

          $this->codeManager=new FormWebPageLayout($this,$actionName,$actionDef);
          $this->initialize($actionName,$actionDef,$wdef);
          
      }

      function setupPath()
      {
          //$actionName=$name
          //$actionDef=$srcObject
          //$definition=pageDefinition

          if(isset($this->definition["FIELDS"]))
              $pathContents["PARAMS"]=$this->definition["FIELDS"];
          $pathContents["LAYOUT"]=$this->definition["LAYOUT"];

          $parentModel=$this->srcObject->parentModel;
          $parentName=$parentModel->objectName;  
          
          $pathContents["NAME"]=str_replace("\\","-",$parentName)."_".$this->definition["NAME"];
          $this->pathContents=$pathContents;        
                    
          $role=$this->srcObject->getRole();
          $srcDef=$this->srcObject->getDefinition();
          $params=$srcDef["INDEXFIELDS"];

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
          
          if($this->hasCompleteKey($parentName->getNormalizedName(),$normalizedParams,$path,$extraParams))
          {
              $ownParams=$normalizedParams[$parentName->getNamespaced()];
              $this->definition["MODELIDS"][$parentName->getNormalizedName()]=array_diff($ownParams,$extraParams);
              $this->path.=$path;
          }
          else
          {
              $this->path.="/".$parentName->className;
          }
          $this->path.="/".$this->definition["NAME"];
      //    $this->definition["PATH"]=$this->path;
      }

      function getCodeManager()
      {
          return $this->codeManager;
      }
          
  }

?>

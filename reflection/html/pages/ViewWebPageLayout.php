<?php
  namespace lib\reflection\html\pages;
  class ViewWebPageLayout extends \lib\reflection\base\BaseDefinition
  {

      function __construct($viewDef,$name,$dsDef)
      {          
          $this->viewDef=$viewDef;
          $this->name=$viewName;
          if($viewDef->isAdmin())
            $isadmin=true;
          
          if($isadmin)
          {            
            $prefix2="admin";
            $pageprefix="/admin/".$viewDef->parentModel->objectName->getNormalizedName()."/ADMIN";
          }
          $parentModel=$dsDef->parentModel;
          $listCodePath="/".$parentModel->objectName->getClassName()."/html/views/".$dsDef->getName();
          $this->code="[*".$pageprefix."PAGE]\n\t[_CONTENT]\n\t\t[*".$listCodePath."][#]\n\t[#]\n[#]";         
      }

      function save()
      {
          $definition=$this->viewDef->getDefinition();
          $defFile=$this->viewDef->getDestinationFile();
          $basePath=dirname($defFile);          
          
          @mkdir($basePath,077,true);
          file_put_contents($basePath."/".$definition["NAME"].".wid",$this->code);
      }
  }

?>

<?php
  namespace lib\reflection\html\pages;
  class FormWebPageLayout extends \lib\reflection\base\BaseDefinition
  {

      var $pageDef;
      var $actionDef;
      var $code;
      function __construct($pageDef,$actionName,$actionDef)
      {
          $this->actionDef=$actionDef;
          $this->pageDef=$pageDef;          
          $role=$actionDef->getRole();
          // En caso de que el rol de esta accion sea "EDIT", se aniaden los formularios necesarios
          // para editar las relaciones multiples asociadas a este objeto.
          if(strtoupper($role)=="EDIT")
          {
              $actions=$actionDef->parentModel->getActions();
              foreach($actions as $curAct=>$actDef)
              {                  
                       // No se generan paginas para acciones que se basan en relaciones multiples.
                       if($actDef->getTargetRelation())
                       {
                           
                           // Solo se van a generar los widgets necesarios para Aniadir y para Eliminar.
                           // El "Set" se queda sin pagina, pero si existira la accion.
                           if($actDef->getRole()!="SetRelation")
                               continue;
                           $forms=$actDef->getForms();
                           
                           foreach($forms as $mForm)
                           {
                               $extraForm.="\n\t\t\t[*:".$mForm->getWidgetPath()."][#]";
                           }
                       }
              }
          }
                     
          // Setup de los parametros.
          // Los parametros se toman del formulario.Si hay indexFields, estos son parametros de la pagina formulario, y,
          // ademas, deben formar parte del path de esta pagina dentro de la web.
         $parentModel=$actionDef->parentModel;
         
         if($actionDef->isAdmin())
         {
            $prefix="ADMIN";
            $prefix2="admin";            
            $pageprefix="/admin/".$actionDef->parentModel->objectName->getNormalizedName()."/ADMIN";
         }
         $formCodePath="/".$parentModel->objectName->getClassName()."/html/forms/".$actionDef->getName();
         $this->code="[*".$pageprefix."PAGE]\n\t[_CONTENT]\n\t\t[*".$formCodePath."][#]".$extraForm."\n\t[#]\n[#]";
      }
      
      function getCode()
      {
          return $this->code;
      }      

      function save()
      {
          $definition=$this->pageDef->getDefinition();
          $defFile=$this->pageDef->getDestinationFile();
          $basePath=dirname($defFile);          
          
          @mkdir($basePath,077,true);
          file_put_contents($basePath."/".$definition["NAME"].".wid",$this->code);
      }
  }

?>

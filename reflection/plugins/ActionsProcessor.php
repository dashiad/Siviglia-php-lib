<?php
namespace lib\reflection\plugins;

class ActionsProcessor extends \lib\reflection\SystemPlugin
{

    function REBUILD_ACTIONS($level)
    {
        if ($level != 0)
            return;

        printPhase("Generando acciones");
        $this->iterateOnModels("generateNonAdminActions");
        $this->iterateOnModels("generateAdminActions");
    }

    function generateNonAdminActions($layer,$name,$model)
    {
        $this->generateActions($name,$model,$layer,false);
    }
    function generateAdminActions($layer,$name,$model)
    {
        $this->generateActions($name,$model,$layer,true);
    }

    function generateActions($objName, $objModel, $layer,$isadmin)
    {
        
        $ownershipField = $objModel->getOwnershipField();
        $modifyPermission = array();
        if(!$isadmin)
        {
            $prefixL="";
            $prefixU="";
            if ($ownershipField)
                $modifyPermission[] = "_OWNER_";
        }
        else
        {
            $prefixL="admin";
            $prefixU="Admin";
        }

        // No se han definido acciones: creo las acciones por defecto.

        $indexFields = $objModel->getIndexFields();
        $requiredFields = $objModel->getRequiredFields();
        $optionalFields = $objModel->getOptionalFields();

        if (count($requiredFields) == 0)
        {
            $requiredFields = $optionalFields;
            $optionalFields = array();
        }

        $extendedModel=$objModel->getExtendedModelName();
        if($objModel->getExtendedModelName())
        {
            // Extiende de otro objeto.
            // Se obtiene la instancia.
            $parentInstance=\lib\reflection\ReflectorFactory::getModel($extendedModel);
            $parentRequired=$parentInstance->getRequiredFields();
            $parentOptional=$parentInstance->getOptionalFields();
            if(count($parentRequired)==0)
            {
                $parentRequired=$parentOptional;
                $parentOptional=array();
            }
            $requiredFields=array_merge($parentRequired,$requiredFields);
            $optionalFields=array_merge($parentOptional,$optionalFields);
        }

        printItem("Generando acciones por defecto");
        
        

        $defaultActions=array("NAMES"=>array("DeleteAction","EditAction"),
                              "PERMISSIONS"=>array(
                                  \lib\reflection\permissions\PermissionRequirementsDefinition::create(array_merge($modifyPermission, array(array("MODEL"=>$objName,"PERMISSION"=>"delete")))),
                                  \lib\reflection\permissions\PermissionRequirementsDefinition::create(array_merge($modifyPermission, array(array("MODEL"=>$objName,"PERMISSION"=>(isadmin?"adminEdit":"edit")))))
                                  ),
                              "INDEXES"=>array($indexFields,$indexFields),
                              "FIELDS"=>array(null,$requiredFields),
                              "OPTFIELDS"=>array(array(),$optionalFields)
                              );
        // Si no tiene subtipos, se aniade el formulario ADD
        if(!$objModel->getSubTypes())
        {
            $defaultActions["NAMES"][]="AddAction";
            $defaultActions["PERMISSIONS"][]=\lib\reflection\permissions\PermissionRequirementsDefinition::create(array(array("MODEL"=>$objName,"PERMISSION"=>($isadmin?"adminCreate":"create"))));
            $defaultActions["INDEXES"][]=null;
            $defaultActions["FIELDS"][]=$requiredFields;
            $defaultActions["OPTFIELDS"][]=array();               
        }
        
        $nActions=count($defaultActions["NAMES"]);
        for($k=0;$k<$nActions;$k++)
        {
            $cName=$defaultActions["NAMES"][$k];
            $curName=$prefixU.$cName;
            $curAction=new \lib\reflection\actions\ActionDefinition($curName,$objModel);
            if($curAction->mustRebuild())
            {
                $curAction->create($defaultActions["NAMES"][$k],
                                   $defaultActions["INDEXES"][$k],
                                   $defaultActions["FIELDS"][$k],
                                   $defaultActions["OPTFIELDS"][$k],
                                   null,$isadmin);


            }
            else
                $curAction->initialize();
            $objModel->addAction($curName,$curAction);
         }
                       
        if($isadmin)
            return;
        /*
        // se preparan las acciones sobre las relaciones multiples.
        // Primero, para relaciones basadas en objetos intermedios.
        $role=$objModel->getRole();
        if($role=="MULTIPLE_RELATION")
        {
            $objDef=$objModel->getDefinition();
            $mrelfields=$objDef["MULTIPLE_RELATION"]["FIELDS"];
            foreach($mrelfields as $curf)
            {
                $relatedModel[]=''.$objModel->getField($curf)->getRemoteModel()->objectName;
            }
            sort($relatedModel);
            $name=implode("_",$relatedModel);
            foreach($mrelfields as $curf)
            {
                 $fake=new \lib\reflection\model\aliases\FakeRelationship($name,$objModel);
                 $fake->setSideFromField($curf);
                 $actions=$fake->generateActions($isadmin);
                 $target=$objModel->getField($curf)->getRemoteModel();
                 foreach($actions as $nact)
                 {
                      $target->addAction($nact->getName(),$nact);
                 }
            }
        }
        */
        // Hay que generar 2 acciones: una para el objeto que ha definido la relacion multiple, 
        // y otra, para el objeto relacionado.
        $aliases = $objModel->getAliases();        
        foreach ($aliases as $relKey => $alValue)
        {

            $acts=$alValue->generateActions($isadmin);
            if(!is_array($acts))
                continue;
            
            $targetModel=$objModel;

            foreach($acts as $key=>$value)
            {
                $targetModel->addAction($value->getName(),$value);
            }
        }
        
        
    }

}

?>

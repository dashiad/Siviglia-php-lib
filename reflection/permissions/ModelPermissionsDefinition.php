<?php
namespace lib\reflection\permissions;
class  ModelPermissionsDefinition
{
    function __construct($parentModel,$def)
    {
        $this->parentModel = $parentModel;
        $this->definition     = $def;
    }

    function install($permManager)
    {                
        // Se instala el modulo en si, como un grupo de Axos.
        $layer=$this->parentModel->layer;
        $path="/AllObjects/Sys/modules/".$layer."Modules";

        $objName=$this->parentModel->objectName->getNormalizedName();

        $rootGroupId=$permManager-> getGroupFromPath($path,\AclManager::AXO);

        
        $id=$permManager->add_group($objName,$rootGroupId,\AclManager::AXO);
        $path="/AllPerms/Sys/modules/".$layer."Modules";
        $rootGroupId=$permManager->getGroupFromPath($path,\AclManager::ACO);
        $acoGroupId=$permManager->add_group($objName,$rootGroupId,\AclManager::ACO);

        foreach($this->definition as $key=>$value)
        {
            
            // Para evitar duplicados, se le pone como prefijo el nombre del modulo
            $permName=$key;

            $objectId=$permManager->add_object("ModulePermission",$permName,\AclManager::ACO) ;
            $permManager->add_group_object($acoGroupId, $objectId);
            foreach($value as $allow=>$who)
            {
                $targets=array();
                foreach($who as $index=>$whoGroup)
                {
                    if( $whoGroup=="_PUBLIC_" || $whoGroup=="_OWNER_" )
                        continue;
                    $targets[]=$whoGroup;
                }
                if( count($targets)>0 )
                {
                    $permManager->add_acl(array("ITEMS"=>array($permName)),
                     array("GROUPS"=>$targets),
                     array("GROUPS"=>array($this->parentModel->objectName->getNormalizedName())),
                                          $value=="ALLOW"?1:0);
                }
            }            
        }
    }

    function getDefinition()
    {
        return $this->definition?$this->definition:array();
    }

    /*static function createDefault($parentModel)
    {
        $className=$parentModel->objectName->getNormalizedName();
        $layer=$parentModel->objectName->layer;
        $layerPerm=ucfirst($layer)."Admin";
        return new ModelPermissionsDefinition($parentModel,  
                                                                    array(
                                                                            $className."_create"=>array("ALLOW"=>array("_PUBLIC_")),
                                                                            $className."_destroy"=>array("ALLOW"=>array("_PUBLIC_")),
                                                                            $className."_edit"=>array("ALLOW"=>array("_PUBLIC_")),
                                                                            $className."_view"=>array("ALLOW"=>array("_PUBLIC_")),
                                                                            $className."_adminCreate"=>array("ALLOW"=>array($layerPerm)),
                                                                            $className."_adminDestroy"=>array("ALLOW"=>array($layerPerm)),
                                                                            $className."_adminEdit"=>array("ALLOW"=>array("_PUBLIC_")),
                                                                            $className."_adminView"=>array("ALLOW"=>array("_PUBLIC_")),
                                                                          )
                                                                 );

    }*/
    static function getDefaultAcls($className,$layer)
    {
        
        $adminGroup=ucfirst($layer)."Admin";        
        // Se retornan todos los permisos, establecidos para todos los usuarios.
        return array();
                     /* array(
                            array("GROUPS"=>array("Users")), 
                            array("ITEM"=>array("create")),
                            array("GROUP"=>array($className))
                          ),
                      array(
                            array("GROUPS"=>array("Users")), 
                            array("ITEM"=>array("destroy")),
                            array("GROUP"=>array($className))
                           ),
                      array(
                            array("GROUPS"=>array("Users")), 
                            array("ITEM"=>array("edit")),
                            array("GROUP"=>array($className))
                          ),
                      array(
                             array("GROUPS"=>array("Users")), 
                             array("ITEM"=>array("view")),
                            array("GROUP"=>array($className))
                           ),
                      array(
                             array("GROUPS"=>array("Users")), 
                             array("ITEM"=>array("list")),
                            array("GROUP"=>array($className))
                           ),
                      array(
                            array("GROUPS"=>array($adminGroup)), 
                            array("ITEM"=>array("adminCreate")),
                            array("GROUP"=>array($className))
                          ),
                      array(
                            array("GROUPS"=>array($adminGroup)), 
                            array("ITEM"=>array("adminDestroy")),
                            array("GROUP"=>array($className))
                           ),
                      array(
                            array("GROUPS"=>array($adminGroup)), 
                            array("ITEM"=>array("adminEdit")),
                            array("GROUP"=>array($className))
                          ),
                      array(
                             array("GROUPS"=>array($adminGroup)), 
                             array("ITEM"=>array("adminView")),
                            array("GROUP"=>array($className))
                           ),
                      array(
                             array("GROUPS"=>array($adminGroup)), 
                             array("ITEM"=>array("adminList")),
                            array("GROUP"=>array($className))
                           )            
                      );*/
                       
    }
}
?>

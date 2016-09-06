<?php
  namespace lib\reflection\plugins;

  class PermissionsProcessor extends \lib\reflection\SystemPlugin
  {
        
        function START_SYSTEM_REBUILD($level)
        {
            if($level!=2)
                return;

            printPhase("Inicializando permisos globales");
            global $APP_NAMESPACES;
            if(in_array("web",$APP_NAMESPACES))
                $tdb="web";
            else
                $tdb=$APP_NAMESPACES[0];

            $layer=\lib\reflection\ReflectorFactory::getLayer($tdb);
            $permissions=$layer->getPermissionsDefinition();       
            if($permissions)
            {                
                include_once(PROJECTPATH."/lib/model/permissions/AclManager.php");
                $this->permsManager=new \AclManager($layer->getSerializer());                
                $oPerms=& $this->permsManager;
                $perms=$layer->getPermissionsDefinition();

                if($perms["Users"])
                    $oPerms->createPermissions($perms["Users"],0,0,\AclManager::ARO);
                if($perms["Permissions"])
                    $oPerms->createPermissions($perms["Permissions"],0,0,\AclManager::ACO);
                if($perms["Objects"])
                    $oPerms->createPermissions($perms["Objects"],0,0,\AclManager::AXO);

                if($perms["DefaultPermissions"])
                {
                    for($j=0;$j<count($perms["DefaultPermissions"]);$j++)
                    {
                        $curPerm=$perms["DefaultPermissions"][$j];
                        $oPerms->add_acl($curPerm[0],$curPerm[1],$curPerm[2],(!$curPerm[3]?1:0));
                    }
                }
            }
            
        }
        function REBUILD_MODELS($level)
        {
            if($level!=2)
                return;
            printPhase("Generando permisos para modelos");
            global $APP_NAMESPACES;
            if(in_array("web",$APP_NAMESPACES))
                $tdb="web";
            else
                $tdb=$APP_NAMESPACES[0];
            $layer=\lib\reflection\ReflectorFactory::getLayer($tdb);
            $this->permsManager=new \AclManager($layer->getSerializer());

            global $APP_NAMESPACES;
            foreach($APP_NAMESPACES as $key=>$layer)
            {
                $objList=\lib\reflection\ReflectorFactory::getObjectsByLayer($layer);
                foreach($objList as $objName=>$objDef)
                {
                    $perms=$objDef->getPermissionsDefinition();
                    
                    if(!$perms)
                        continue;
                    // permsManager se ha inicializado en el metodo START_SYSTEM_REBUILD
            
                    $perms->install($this->permsManager);
                }
            }
            // Se crea el usuario 0 dentro de Anonymous.
            $aclId=$this->permsManager->add_object("user","0");
            $groupId=$this->permsManager->get_group_id(null,'Anonymous',\AclManager::ARO);
            $this->permsManager->add_group_object($groupId,$aclId);
        }        
   }

?>

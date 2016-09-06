<?php
namespace lib\reflection\plugins;

class SysInstall extends \lib\reflection\SystemPlugin
{
        
        function START_SYSTEM_REBUILD($level)
        {
            if($level!=1)
                return;
            printPhase("Inicializando Storage");
            // Inicializacion de serializadores
            global $APP_NAMESPACES;
            foreach($APP_NAMESPACES as $key=>$value)
            {
                $this->getLayer($value)->rebuildStorage();
            }
            printSubPhase("Instalando soporte de permisos");
            include_once(PROJECTPATH."/lib/model/permissions/AclManager.php");

            if(in_array("web",$APP_NAMESPACES))
                $permDB="web";
            else
                $permDB=$APP_NAMESPACES[0];
            $layer=\lib\reflection\ReflectorFactory::getLayer($permDB);
            
            $oPerm=new \AclManager($layer->getSerializer());
            $oPerm->uninstall();
            
            $oPerm->install();
        }

        function runObjectSetup($layer,$name,$model)
        {
            $model->runSetup();
        }

        function END_SYSTEM_REBUILD($level)
        {
            if($level==2)
                $this->iterateOnModels("runObjectSetup");
        }
            // Execution of startup scripts, both for objects and data types

              
            
        
}

?>

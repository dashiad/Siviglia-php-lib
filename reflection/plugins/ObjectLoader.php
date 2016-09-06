<?php
namespace lib\reflection\plugins;

class ObjectLoader extends \lib\reflection\SystemPlugin
{
        function REBUILD_MODELS($level)
        {
            if($level!=1)
                return;
            printPhase("Cargando Modelos");
            global $APP_NAMESPACES;
            foreach($APP_NAMESPACES as $index=>$curLayer)
            {
                printSubPhase("Cargando Modelos de ".$curLayer);
                // Se mira si existe una quickDef
                $layer=\lib\reflection\ReflectorFactory::getLayer($curLayer);
                $quickDef=$layer->getQuickDefinitions();

                printSubPhase("Procesando QuickDefs");
                //$quickDef=$layerConf->definition["QuickDef"];
                if(!$quickDef)
                    continue;

                foreach($quickDef as $key=>$value)
                {
                    printItem("Procesando $key");
                    if(ReflectionFactory::getModel($key))
                        continue;
                    $instance=\lib\reflection\model\QuickModelGenerator::createFromQuick($key,$curLayer,$value);
                    ReflectorFactory::addModel($curLayer,$key,$instance);
                    $instance->saveDefinition("objects");
                }                                              
            }                   
            printSubPhase("Generando Relaciones Inversas");
            $this->iterateOnModels("generateExtRelationships");
            printSubPhase("Generando clases modelo temporales");
            $this->iterateOnModels("generateTempModelClasses");
        }
        
        function generateExtRelationships($layer,$name,$instance)
        {            
            $instance->createDerivedRelations();
        }

        
        function generateTempModelClasses($layer,$name,$instance)
        {
            echo "GENERANDO PARA $layer $name<br>";
            $modelClassFile=new \lib\reflection\model\ModelClass($instance);
            if($modelClassFile->mustRebuild())
                $modelClassFile->generate();
            $instance->saveDefinition(); // se guarda una primera version del objeto compilado.

        }
        function saveModel($layer,$name,$instance)
        {
            $instance->save();
        }

        function SAVE_SYSTEM($level)
        {
            if($level!=1)return;
            $this->iterateOnModels("saveModel");
        }

        function saveModelConfig($layer,$name,$instance)
        {
            $instance->config->save();
        }
        function END_SYSTEM_REBUILD($level)
        {
            if($level!=2)return;
            $this->iterateOnModels("saveModelConfig");
        }
}
?>

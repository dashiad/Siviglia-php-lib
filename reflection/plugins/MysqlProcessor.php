<?php
namespace lib\reflection\plugins;

class MysqlProcessor extends \lib\reflection\SystemPlugin {

    function REBUILD_MODELS($step)
    {
        if($step!=2)
            return;        
        printPhase("Incializando Storage de Modelos [Mysql]");

        // Creacion del soporte en base de datos de las tablas.

        global $APP_NAMESPACES;
        foreach($APP_NAMESPACES as $layer)
        {
         // $layer=\lib\reflection\ReflectorFactory::getLayer($layer);
            $objects=\lib\reflection\ReflectorFactory::getObjectsByLayer($layer);
            foreach($objects as $objName=>$modelDef)
            {                
                 printItem("Generando $objName");
                 $this->generateStorage($objName,$modelDef,$layer);
            }                       
        }          
              
    }
    function generateStorage($objName,$modelDef,$layer)
    {
        $storageConfig=$modelDef->getStorageConfiguration("MYSQL");
        if(!$storageConfig)
        {
            $optionsDefinition=\lib\reflection\storage\options\MysqlOptionsDefinition::createDefault($modelDef);
        }
        else
        {
            $optionsDefinition=new \lib\reflection\storage\options\MysqlOptionsDefinition($modelDef,$storageConfig);
        }

        $layerObj=\lib\reflection\ReflectorFactory::getLayer($layer);
        $curSerializer=$layerObj->getSerializer();    
        if($curSerializer->getSerializerType()!="MYSQL")
        {
            return;
        }
        $modelDef->addStorageConfiguration("MYSQL",$optionsDefinition->getDefinition());
        printItem("<b>Creando Tablas Mysql para relaciones multiples</b>");
        $curSerializer->createStorage($modelDef,$optionsDefinition->getDefinition());

      
    }

    function REBUILD_DATASOURCES($step)
    {

        if($step!=2)
            return;

        printPhase("Generando Datasources Mysql");

        // Creacion del soporte en base de datos de las tablas.
        $this->iterateOnModels("rebuildDataSources");
    }
    function rebuildDataSources($layer,$objName,$modelDef)
    {
    
        $curSerializer=$modelDef->getSerializer();
        if($curSerializer->getSerializerType()!="MYSQL")
             return;
             
        // Se obtienen los datasources generados via el editor.Estan en el fichero Definition.js, key "Queries"
         $modelPath=$modelDef->objectName->getDestinationFile();
         $processedDs=array();
         if(is_file($modelPath."/Definition.json"))
         {
                $fcontents=file_get_contents($modelPath."/Definition.json");
                $def=json_decode($fcontents,true);
                if($def["Queries"])
                {
                    foreach($def["Queries"] as $key=>$value)
                    {
                        // Se anota este datasource como procesado.
                        $processedDs[]=$key;
                        $msQ=\lib\reflection\storage\MysqlDsDefinition::createDatasourceFromQuery($modelDef,$key,$value);
                    }
                }

            }
               
        foreach($modelDef->datasources as $dsKey=>$dsValue)
        {
            // Si es un datasource que ya hemos procesado (estaba en las definiciones generadas por el editor), no lo re-procesamos.
            if(in_array($dsKey,$processedDs))
                continue;
            $newDef=new \lib\reflection\storage\MysqlDsDefinition($modelDef,$dsKey,$dsValue);
            $srcRelation=$dsValue->getSourceRelation();
            
            $type="normal";
            if($srcRelation)
            {
                if(!is_array($srcRelation))
                {
                    $field=$modelDef->getFieldOrAlias($srcRelation);
                    $type="inverse";
                }
                else
                    $type="mxn";
            }
            
            switch($type)
            {            
            case "mxn":
                 {
                     $newDef->createMxNDsDefinition(0);
                 }break;
            case "inverse":
                 {
                     $newDef->createInverseDsDefinition($srcRelation);
                 }break;
                 default:
                     {
                         $newDef->create();
                     }
                 }
                        
        }    
    }
}
?>

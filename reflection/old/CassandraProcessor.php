<?php

namespace lib\reflection\plugins;

class CassandraProcessor extends \lib\reflection\SystemPlugin
{

    function REBUILD_MODELS($sys, $step)
    {
        if ($step != 2)
            return;
        printPhase("Incializando Storage de Modelos [Cassandra]");

        // Creacion del soporte en base de datos de las tablas.

        global $APP_NAMESPACES;
        foreach($APP_NAMESPACES as $key=>$value)
        {
            printSubPhase("Modelos de $value");
            if ($sys->config[$layer]->definition["DONT_REBUILD_DATASPACE"])
                continue;

            foreach ($sys->objectDefinitions[$value] as $objName => $modelDef)
            {
                printItem("Generando $objName");
                $this->generateStorage($objName, $modelDef, $value);
            }
        }
    }

    function generateStorage($objName, $modelDef, $layer)
    {
        $curSerializer = $modelDef->getSerializer();
        if (!$modelDef->config->mustRegenerateStorage())
        {
            return;
        }
        if ($curSerializer->getSerializerType() != "CASS")
            return;

        $cassDefinition = $modelDef->objectName->getDestinationFile("objects", "/definitions/CASS") . "/Definition.php";
        if (!is_file($mysqlDefinition))
        {
            $optionsDefinition = \lib\reflection\classes\storage\CassandraOptionsDefinition::createDefault($modelDef);
        }
        else
        {
            $definitionClass = $modelDef->objectName->getNamespaced("objects") . '\definitions\CASS\Definition';
            $inst = new $definitionClass();
            $optionsDefinition = new \lib\reflection\classes\storage\CassandraOptionsDefinition($modelDef, $inst::$definition);
        }
        $optionsDefinition->save();

        // Una vez generados los datos extra para la creacion de las tablas de los modelos, se comienzan a crear los modelos en la base de datos.

        printItem("<b>Creando ColumnFamilies en Cassandra para relaciones multiples</b>");


        // Se buscan los campos con relaciones multiples
        global $sys;


        $curSerializer->createStorage($objName, $extraDef);

        $aliases = $modelDef->getAliases();
        foreach ($aliases as $aliasKey => $aliasValue)
        {
            $aliasType = $aliasValue->getType();
            if (is_a($aliasType, '\lib\reflection\classes\aliases\Relationship'))
            {
                $aliasType->createStorage($curSerializer);
            }
        }
    }

    function REBUILD_DATASOURCES($sys, $step)
    {

        if ($step != 2)
            return;

        printPhase("Generando Datasources Cassandra");

        // Creacion del soporte en base de datos de las tablas.

        global $APP_NAMESPACES;
        foreach($APP_NAMESPACES as $key=>$value)
        {
            //if($sys->config[$key]->definition["DONT_REBUILD_DATASPACE"])
            //    continue;
            printSubPhase("Modelos de $value");
            $curSerializer = $sys->serializers[$value];
            if ($curSerializer->getSerializerType() != "CASS")
                continue;

            foreach ($sys->objectDefinitions[$value] as $objName => $modelDef)
            {
                $curSerializer = $modelDef->getSerializer();
                if ($curSerializer->getSerializerType() != "CASS")
                    continue;

                printItem("Generando definiciones de $objName");
                foreach ($modelDef->datasources as $dsKey => $dsValue)
                {
                    // En caso de que no tenga, se crea.
                    if ($dsValue->getSerializerDefinition("CASS"))
                        continue;

                    $dsDef = \lib\reflection\classes\CassDsDefinition::createDsDefinition($modelDef, $dsKey, $dsValue);
                    $dsValue->addSerializerDefinition($dsDef, "CASS");
                    $dsDef->save();
                }
            }
        }
    }

}

?>

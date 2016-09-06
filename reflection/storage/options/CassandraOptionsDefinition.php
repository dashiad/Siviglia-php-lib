<?php
namespace lib\reflection\storage;
class CassandraOptionsDefinition extends \lib\reflection\ClassFileGenerator
{
    function __construct($parentModel,$def)
    {
        // En esta clase, los aliases, indexes,etc, se regeneran cada vez que se
        // llama a getDefinition, para reflejar cambios realizados en el modelo.
        $this->definition=$def;
        $this->parentModel=$parentModel;
        \lib\reflection\ClassFileGenerator::__construct(
                "Definition", 
                $parentModel->objectName->layer, 
                $parentModel->objectName->getNamespace().'\definitions\CASS',
                $parentModel->objectName->getPath().'/definitions/CASS/Definition.php');     
    }

    static function createDefault($parentModel)
    {
        $defaultDefinition=array(
                "CONSISTENCY_LEVEL"=>2,
                "KEY"=>array(),
                "INDEXES"=>array()                
        );
        $indexes=$parentModel->getIndexFields();
        foreach($indexes as $key=>$value)
            $defaultDefinition["KEY"][]=$key;

        $ownershipField=$parentModel->getOwnershipField();

        if($ownershipField)
        {            
            $defaultDefinition["INDEXES"][]=$ownershipField;
        }

        $aliases=$parentModel->getInvRelationships();
        foreach($aliases as $key=>$value)
        {
            $defaultDefinition["ALIASES"][$key]=array("LAZY_LOAD"=>"true");
        }
        return new CassandraOptionsDefinition($parentModel,$defaultDefinition);
    }

    function getDefinition()
    {
        $def=$this->definition;
        $model=$this->parentModel;
        $modelFields=$this->parentModel->fields;
        // Se comprueba que todos los campos que existen en los INDEXES, existen en los campos del objeto.
        if($def["INDEXES"])
        {
            foreach($def["INDEXES"] as $key=>$value)
            {
                // TODO: Hacer que esto funcione tambien para los tipos compuestos.
                if(!is_array($value["FIELDS"]))
                {
                    if(!$modelFields[$value["FIELDS"]])
                        continue;
                    $indexes[]=$value;
                }
                else
                {
                    
                    $doExit=false;
                    for($k=0;$k<count($value["FIELDS"]) && !$doExit;$k++)
                    {
                        if(!$modelFields[$value["FIELDS"][$k]])
                            $doExit=true;
                    }
                    if(!$doExit)
                        $indexes[]=$value;
                }
            }
        }
        if(count($indexes)>0)
            $def["INDEXES"]=$indexes;
        
            $aliasDef=array();
        if($def["ALIASES"])
        {
            $aliases=$this->parentModel->aliases;
            foreach($def["ALIASES"] as $key=>$value)
            {
                if($aliases[$key])
                    $aliasDef[$key]=$value;
            }
            unset($def["ALIASES"]);
        }

        if(count(array_keys($aliasDef))>0)
            $def["ALIASES"]=$aliasDef;
        return $def;
    }

    function save()
    {
         if(!$this->parentModel->config->mustRebuild("cassDef",'Definition',$this->filePath))
                return;
                
        $this->addProperty(array("NAME"=>"definition",
                                 "ACCESS"=>"static",
                                 "DEFAULT"=>$this->getDefinition()
            ));
        $this->generate();
    }
}
?>

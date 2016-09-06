<?php
namespace lib\reflection\classes\storage;
class MysqlOptionsDefinition extends \lib\reflection\classes\ClassFileGenerator
{
    function __construct($parentModel,$def)
    {
        // En esta clase, los aliases, indexes,etc, se regeneran cada vez que se
        // llama a getDefinition, para reflejar cambios realizados en el modelo.
        $this->definition=$def;
        $this->parentModel=$parentModel;
        \lib\reflection\classes\ClassFileGenerator::__construct(
                "Definition", 
                $parentModel->objectName->layer, 
                $parentModel->objectName->getNamespace().'\definitions\MYSQL',
                $parentModel->objectName->getPath().'/definitions/MYSQL/Definition.php');                
    }

    static function createDefault($parentModel)
    {
        $defaultDefinition=array(
        "ENGINE"=>"InnoDb",
        "CHARACTER SET"=>"utf8",
        "COLLATE"=>"utf8_general_ci",
        "TABLE_OPTIONS"=>array(
            "ROW_FORMAT"=>"FIXED"
            )
        );
        $ownershipField=$parentModel->getOwnershipField();
        if($ownershipField)
        {
            
            $defaultDefinition["INDEXES"][]=array("FIELDS"=>array($ownershipField),"UNIQUE"=>"false");
        }

        $aliases=$parentModel->getInvRelationships();
        foreach($aliases as $key=>$value)
        {
            $defaultDefinition["ALIASES"][$key]=array("LAZY_LOAD"=>"true");
        }
        return new MysqlOptionsDefinition($parentModel,$defaultDefinition);
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
        if(!$this->parentModel->config->mustRebuild("mysqlDef",'Definition',$this->filePath))
                return;
                
        $this->addProperty(array("NAME"=>"definition",
                                 "ACCESS"=>"static",
                                 "DEFAULT"=>$this->getDefinition()
            ));
        $this->generate();
    }
}
?>

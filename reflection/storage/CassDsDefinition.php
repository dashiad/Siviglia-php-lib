<?php
namespace lib\reflection;
class CassDsDefinition extends ClassFileGenerator
{
        function __construct($parentModel,$dsName,$parentDs,$definition)
        {
                $this->parentModel=$parentModel;
                $this->definition=$definition;
                $this->parentDs=$parentDs;
                $this->dsName=$dsName;
                ClassFileGenerator::__construct($this->dsName,$parentModel->objectName->layer,
                $parentModel->objectName->getNamespace()."\\datasources\\CASS",
                $parentModel->objectName->getPath()."/datasources/CASS/".$this->dsName.".php",
                '\lib\storage\Cassandra\CassandraDataSource'
                );
        }

        static function createDsDefinition($parentModel,$dsKey,$dsValue)
        {

            $layer=$parentModel->objectName->layer;
            $serial=\lib\storage\StorageFactory::getSerializerByName($layer);
            $modelDef=& $parentModel;

                    $def=$dsValue->getDefinition();
                    $meta=$def["METADATA"];
                    $fieldColumns=array();
                    if($meta)
                    {
                        
                        foreach($meta as $metaK=>$metaD)
                        {
                            $field=$modelDef->fields[$metaD["FIELD"]];
                            $types=$field->getType();
                            $serializers=array();
                            $serType=$serial->getSerializerType();
                            foreach($types as $typeKey=>$typeValue)
                            {
                                $serializers[$typeKey]=\lib\model\types\TypeFactory::getSerializer($typeValue,$serType);
                            }

                            //$def=$value->getDefinition();
                            
                            foreach($serializers as $type=>$typeSerializer)
                            {
                                $columnDef=$typeSerializer->getCASSDefinition($type,$types[$type]->getDefinition());

                                if(\lib\php\ArrayTools::isAssociative($columnDef))
                                      $columnDef=array($columnDef);

                                for($k=0;$k<count($columnDef);$k++)
                                         $fieldColumns[]=$columnDef[$k]["NAME"];
                            }
                        }
                    }
                    $tableName=$parentModel->getTableName();
                    if(count($fieldColumns)==0)
                    {
                        $baseDef=null;
                    }
                    else
                        $baseDef=$fieldColumns;

                    // Se preparan las condiciones de filtro.
                    if($def["PARAMS"]["FIELDS"])
                    {
                        foreach($def["PARAMS"]["FIELDS"] as $keyP=>$valP)
                        {
                            $condition["FILTER"]=array("F"=>$valP["FIELD"],"OP"=>"=","V"=>"{%".$keyP."%}");
                            if(!$valP["REQUIRED"])
                            {
                                $condition["TRIGGER_VAR"]=$keyP;
                                $condition["DISABLE_IF"]="0";
                                $condition["FILTERREF"]=$keyP;
                            }
                            $conditions[]=$condition;
                        }
                    }
                    $baseDef=array(
                        "DEFINITION"=>array(
                            "TABLE"=>$tableName,
                            "BASE"=>$baseDef,
                            "CONDITIONS"=>$conditions
                                )               
                            );
                    return new CassDsDefinition($parentModel,$dsKey,$dsValue,$baseDef);

        }
        function discoverFields()
        {

            $parentModelName=$this->parentModel->objectName->getNormalizedName();
            if($this->definition["BASE"]==null || $this->definition["BASE"][0]=="*")
            {
                // Hay que devolver un array con todos los campos de la tabla, y meterlos como metadata.

                foreach($this->parentModel->fields as $key=>$value)
                {                    
                        $fields[$key]=array("MODEL"=>$parentModelName,"FIELD"=>$key);
                }
            
            }
            else
            {
                foreach($this->definition["BASE"] as $val)
                {
                    $fields[$val]=array("MODEL"=>$parentmodelName,"FIELD"=>$val);
                }
            }
                
            return $fields;
                                    
        }

        

        function save()
        {
            if($this->parentModel->config->mustRebuild("cassandrads",$this->dsName,$this->filePath))
            {
                $this->addProperty(array("NAME"=>"serializerDefinition",
                                         "DEFAULT"=>$this->getDefinition()
                ));
                $this->generate();
            }
        }
        function getDefinition()
        {
            return $this->definition;
        }
}

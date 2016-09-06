<?php
namespace lib\reflection\classes;
class MysqlDsDefinition extends ClassFileGenerator
{
        function __construct($parentModel,$dsName,$parentDs,$definition)
        {
            
                $this->parentModel=$parentModel;
                $this->definition=$definition;
                $this->parentDs=$parentDs;
                $this->dsName=$dsName;
                
                ClassFileGenerator::__construct($this->dsName, $this->parentModel->objectName->layer, 
                        $this->parentModel->objectName->getNamespace()."\\datasources\\MYSQL",
                        $this->parentModel->objectName->getPath()."datasources/MYSQL/".$this->dsName.".php",
                        '\lib\storage\Mysql\MysqlDataSource');
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
                                $columnDef=$typeSerializer->getSQLDefinition($type,$types[$type]->getDefinition());

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
                        $baseDef="SELECT * FROM ".$tableName;
                    }
                    else
                        $baseDef=$fieldColumns;

                    // Se preparan las condiciones de filtro.
                    if($def["PARAMS"]["FIELDS"])
                    {
                        foreach($def["PARAMS"]["FIELDS"] as $keyP=>$valP)
                        {
                            $condition=array("FILTER"=>$valP["FIELD"]."={%".$keyP."%}");
                            
                                $condition["TRIGGER_VAR"]=$keyP;
                                $condition["DISABLE_IF"]="0";
                                $condition["FILTERREF"]=$keyP;
                            _d($condition);    
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
                    return new MysqlDsDefinition($parentModel,$dsKey,$dsValue,$baseDef);

        }

        public function discoverFields()
        {
            $definition=$this->getDefinition();
            
            // Hay que eliminar todos los filtros que haya sobre la query, ya que solo
            // nos interesan los campos.

            // Se intentan descubrir que campos son los devueltos por esta query.
            $definition["CONDITIONS"]=array();
            $minDef["TABLE"]=$definition["DEFINITION"]["TABLE"];
            $minDef["BASE"]=$definition["DEFINITION"]["BASE"];
            
            $qb=new \lib\storage\Mysql\QueryBuilder($minDef);
            $q=$qb->build();

            $layer=$this->parentModel->objectName->layer;
            $serial=\lib\storage\StorageFactory::getSerializerByName($layer);
            $connObj=$serial->getConnection();
            $connRes=$connObj->getConnectionResource();
            $res=mysql_query($q,$connRes);
            if(!$res)
            {
                debug("Error al probar la query asociada al datasource ".$this->dsName." del objeto ".$this->parentModel->objectName->className);                
                _d(mysql_error($connRes));
                
                exit();
            }

            $nFields=mysql_num_fields($res);
            $parentTable=$this->parentModel->getTableName();
            $modelName=$this->parentModel->objectName->getNamespaced();

            // Se obtienen los nombres que los campos del modelo han creado sobre la tabla.
            // Esto es importante para los campos compuestos, ya que se mapean a varias columnas.
            foreach($this->parentModel->fields as $fieldName=>$field)
            {

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
                    $columnDef=$typeSerializer->getSQLDefinition($type,$types[$type]->getDefinition());

                    if(\lib\php\ArrayTools::isAssociative($columnDef))
                        $columnDef=array($columnDef);

                    for($k=0;$k<count($columnDef);$k++)
                          $fieldColumns[$columnDef[$k]["NAME"]]=$fieldName;
                }

            }
            $typeConversion=array(
                "string"=>"String",
                "int"=>"Integer",
                "real"=>"Float",
                "year"=>"Integer",
                "date"=>"DateTime",
                "datetime"=>"DateTime",
                "timestamp"=>"Timestamp",
                "blob"=>"Blob"
                );
            for($k=0;$k<$nFields;$k++)
            {
                $fTable=mysql_field_table($res,$k);
                $fName=mysql_field_name($res,$k);

                if($fTable==$parentTable) // Puede ser un campo del modelo.En ese caso, obtenemos la informacion del campo.
                {
                    $sourceField=$fieldColumns[$fName];
                    // $fName es el campo en la base de datos; $sourceField es el campo en la definicion del modelo.
                    // Es decir, para el $sourceField "coordinate", habra 2 $fName, "coordinate_x" y "coordinate_y".
                    // Sin embargo, en la metadata, solo hay que incluirlo una vez.Por eso se comprueba si el $sourceField ya ha sido
                    // incluido.
                    if($fieldColumns[$fName]) 
                    {
                        if(!$returnedFields[$sourceField])
                            $returnedFields[$sourceField]=array("MODEL"=>$modelName,"FIELD"=>$sourceField);
                        continue;                        
                    }
                    // Si la columna es de la tabla actual, pero no se ha encontrado el campo del modelo al que pertenece
                    // esa columna, se continua la evaluacion.(debe ser un campo calculado) 
                }

                // El campo no pertenece a este modelo.Hay que hacer 2 cosas:
                // 1: Obtener su nombre, y asegurarse de que no contiene caracteres extranios.Es decir, "count(*)" no deberia
                //    estar permitido, y deberia tener un alias.
                if( !preg_match("/^[a-zA-Z][a-zA-Z0-9_]*$/",$fName) )
                    {
                        // Por ahora, solo mostramos un warning
                        printWarning("Atencion.El datasource ".$this->dsName." del objeto ".$modelName." devuelve el campo ".$fName." que deberia tener su propio alias.");
                        continue;
                    }
                    // Vemos si tiene alguna tabla.
                    
                        // O no tiene tabla, o la tabla es la generada por este modelo.
                        // Se obtiene su metadata.
                        $fieldData=mysql_field_type($res,$k);
                        $parts=explode(" ",$fieldData);
                        $typePart=$parts[0];
                        $lengthPart=$parts[1];
                        $typeType=$typeConversion[$parts[0]];

                        if( !$typeType )
                        {
                            printWarning("Atencion.En el datasource ".$this->dsName." del objeto ".$modelName." devuelve el campo ".$fName.",de tipo ".$parts[0].", que no se sabe como mapear a nuestros tipos de datos .");
                            continue;
                        }
                        $returnedFields[$fName]=array("TYPE"=>$typeConversion[$parts[0]],"MAXLENGTH"=>$parts[1]);

                        if( $fTable!=$parentTable)
                        {
                            printWarning("Atencion.En el datasource ".$this->dsName." del objeto ".$modelName." devuelve el campo ".$fName.",de tipo ".$parts[0].", que pertenece a la tabla $fTable.<br>Se crea una metadata generica para el campo.");
                        }
   
            }
            return $returnedFields;       
        }


        function save()
        {
            
            if($this->parentModel->config->mustRebuild("mysqlds",$this->dsName,$this->filePath))
            {                
            $this->addProperty(array("NAME"=>"serializerDefinition",                                     
                                     "DEFAULT"=>$this->getDefinition()));
            $this->generate();
            }
        }
        function getDefinition()
        {
            return $this->definition;
        }        
    
}
 
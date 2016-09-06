<?php
namespace lib\reflection\storage;
class MysqlDsDefinition 
{
    static $connections;
        function __construct($parentModel,$dsName,$parentDs)
        {                 
                $this->parentDs=$parentDs;
                $this->dsName=$dsName;  
                $this->parentModel=$parentModel;           
                $this->initialize($parentDs->getStorageDefinition("MYSQL"));
        }
        function initialize($definition=null)
        {
             $this->hasDefinition=false;                            
            if(!$definition)
                return;
                           
            $this->definition=$definition;
            $this->hasDefinition=true;
            $this->serializer=$this->parentModel->getSerializer();            
            $this->discoverFields();                                    
            $this->parentDs->addStorageDefinition("MYSQL",$this->definition);
        }
        function generateFromQuery($model,$dsName,$query)
        {
            /* query:
             SELECT * FROM aa WHERE [%pname:CONCAT(a,b)='{%pname%}'%]
            */

            preg_match_all("/\[\%((?:.(?!(?:%])))*.)\%\]/",$query,$matches);
            $newQuery=$query;
            $testQuery=$query;
            $queryParams=array();
            $dsParams=array();
            $dsParamsBooleans=array();

            for($k=0;$k<count($matches[1]);$k++)
            {
                $current=$matches[1][$k];
                $pos=strpos($current,":");
                $pName=substr($current,0,$pos);

                $cond=substr($current,$pos+1);
                preg_match_all("/{\%([^%]*)\%}/",$cond,$matches2);
                $triggerVar=$matches2[1][0];
                if(!isset($triggerVar))
                {
                    // Si no hay una variable, es que nos tenemos que inventar una variable booleana, que
                    // incluya o no esta condicion.
                    $dsParamsBooleans[]=$pName;
                }
                else
                    $dsParamNames[]=$triggerVar;

                $queryParams[]=array("FILTER"=>$cond,"TRIGGER_VAR"=>$triggerVar,"DISABLE_IF"=>'');
                $newQuery=str_replace('[%'.$current.'%]','[%'.$k.'%]',$newQuery);
                $testQuery=str_replace('[%'.$current.'%]','true',$testQuery);
            }

            $data=$this->discoverQueryFields($testQuery);


            // Se sobreescribe en caso de que haya habido aniadido de aliases.
            if(count($data["replacements"]["look"])>0)
            {
                echo "NEW:$newQuery<br>";
                $query=str_replace($data["replacements"]["look"],$data["replacements"]["replace"],$newQuery);
                echo "NEWQ:$query<br>";
            }
            else
                $query=$newQuery;

            // Se comprueban y obtienen tipos para todos los campos.
            $nfields=count($data["fields"]);
            $metaData=array();
            for($k=0;$k<$nfields;$k++)
            {
                $c=$data["fields"][$k];
                if($c["TABLE"]!='')
                {
                    $info=$this->findTableField($c["TABLE"],$c["FIELD"]);
                    if($info)
                    {
                        //$metaData[$c["FIELD"]]=$info["FIELD"];
                        $metaData[$c["ALIAS"]]=$info["FIELD"];
                    }
                }
                if(!isset($metaData[$c["ALIAS"]]))
                {
                    //$metaData[$c["FIELD"]]=array("TYPE"=>$info["TYPE"],"MAXLENGTH"=>$info["MAXLENGTH"]);
                    $metaData[$c["ALIAS"]]=array("TYPE"=>$info["TYPE"],"MAXLENGTH"=>$info["MAXLENGTH"]);
                }
            }

            // Ahora hay que hacer lo mismo con los parametros que hemos encontrado.En teoria, esos parametros
            // se deben encontrar en alguna de las tablas que contiene la query.
            $tables=$data["tables"];
            for($k=0;$k<count($dsParamNames);$k++)
            {

                $paramName=$dsParamNames[$k];
                if(strpos($dsParamNames[$k],"dyn_")===0)
                {
                    $fieldName=substr($dsParamNames[$k],4);
                    $parameters[$paramName]=array("PARAMTYPE"=>"DYNAMIC");
                }
                else
                {

                    $fieldName=$paramName;
                    $parameters[$paramName]=array();
                }


                for($j=0;$j<count($tables);$j++)
                {
                    $curTable=$tables[$j];

                    $info=$this->findTable($curTable,$fieldName);
                    if($info)
                    {
                        // Hay que ver aqui que mientras el FIELD se toma de $paramName, el TRIGGER_VAR se toma del array original.
                        // Esto es porque, si es un parametro dinamico, en $dsParamNames[$k] tendremos dyn_field, mientras paramName
                        // sera simplemente "field", que es el nombre real de ese campo en el modelo.
                        $parameters[$paramName]=array("MODEL"=>$info,"FIELD"=>$fieldName,"TRIGGER_VAR"=>$dsParamNames[$k]);
                        break;
                    }
                }
            }
            for($k=0;$k<count($dsParamsBooleans);$k++)
            {
                $parameters[$dsParamsBooleans[$k]]=array("TYPE"=>"Boolean","TRIGGER_VAR"=>$dsParamsBooleans[$k]);
            }

            $ds=new \lib\reflection\datasources\DatasourceDefinition($dsName,$model);
            $ds->create($metaData,array("_PUBLIC_"),array(),$parameters,"list");
            $mysqlDef=array(
                "TABLE"=>$model->getTableName(),
                "BASE"=>$query,
                "CONDITIONS"=>$queryParams
            );

            $myDs=new MysqlDsDefinition($model,$dsName,$ds);
            $myDs->initialize(array("DEFINITION"=>$mysqlDef));
            $model->addDatasource($ds);
            $def=$ds->getDefinition();
            return $ds;

        }
               
        function create()
        {            
            $dsKey=$this->dsName;
            $dsValue=$this->parentDs;
            $def=$dsValue->getDefinition();            
            $layer=$this->parentModel->getLayer();
            $serial=$this->parentModel->getSerializer();            
            $modelDef=$this->parentModel;
            $tableName=$this->parentModel->getTableName();

            $meta=$def["FIELDS"];
            $fieldColumns=array();
            if($meta)
            {
               foreach($meta as $metaK=>$metaD)
               {
                   if($metaD["FIELD"])
                   {
                       $cModel=\lib\reflection\ReflectorFactory::getModel($metaD["MODEL"]);
                       $field=$cModel->getField($metaD["FIELD"]);
                   }
                   if($metaD["REFERENCES"])
                   {
                       $cModel=\lib\reflection\ReflectorFactory::getModel($metaD["REFERENCES"]["MODEL"]);
                       $field=$cModel->getField($metaD["REFERENCES"]["FIELD"]);
                   }
                   if($field==null)                   
                       $fields=$modelDef->getFields();

                   $types=$field->getRawType();
                   $serializers=array();
                   $serType=$serial->getSerializerType();
                   foreach($types as $typeKey=>$typeValue)
                   {
                        $serializers[$typeKey]=\lib\model\types\TypeFactory::getSerializer($typeValue,$serType);
                   }                       
                            
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
                    
            if(count($fieldColumns)==0)
            {
                if(is_string($def["DEFINITION"]["BASE"]))
                    $baseDef=$def["DEFINITION"]["BASE"];
                else
                    $baseDef="SELECT * FROM ".$tableName;
            }
            else
                 $baseDef=$fieldColumns;

            $params=isset($def["PARAMS"])?$def["PARAMS"]:array();
            $indexes=isset($def["INDEXFIELDS"])?$def["INDEXFIELDS"]:array();

            $fullParams=$indexes + $params;

            // Se preparan las condiciones de filtro.
            $role=$dsValue->getRole();
            foreach($fullParams as $keyP=>$valP)
            {
                if(isset($valP["PARAMTYPE"]))
                {
                    if($valP["PARAMTYPE"]=="PAGER")
                    {
                        $pagerVariable=$keyP;
                        continue;
                    }                       
                    if($valP["PARAMTYPE"]=="DYNAMIC")
                        $condition=array("FILTER"=>$valP["FIELD"]." LIKE {%".$keyP."%}");
                }
                else
                    $condition=array("FILTER"=>array("F"=>$valP["FIELD"],"OP"=>"=","V"=>"{%".$keyP."%}"));                          
                    
                 if(!isset($valP["REQUIRED"]))
                 {
                      $condition["TRIGGER_VAR"]=$keyP;
                      $condition["DISABLE_IF"]="0";
                 }
                 $condition["FILTERREF"]=$keyP;
                 $conditions[]=$condition;
            }
            

            $baseDef=array(
                 "DEFINITION"=>array(
                 "TABLE"=>$tableName,
                 "BASE"=>$baseDef,
                 "CONDITIONS"=>$conditions
                                )               
                 );
            if($def["PAGESIZE"] && isset($pagerVariable))
            {
                $baseDef["DEFINITION"]["PAGESIZE"]=$def["PAGESIZE"];
                $baseDef["DEFINITION"]["STARTINGROW"]="{%".$pagerVariable."%}";
            }
            if($def["LIMIT"])
            {
                $baseDef["DEFINITION"]["LIMIT"]=$def["LIMIT"];
            }
            if($pagerVariable)
                $baseDef["DEFINITION"]["STARTINGROW"]="{%".$keyP."%}";

            $this->initialize($baseDef);
            return $this;
        }


        function createInverseDsDefinition()
        {            

            $layer=$this->parentModel->getLayer();
            $serial=$this->parentModel->getSerializer();
            
            $def=$this->parentDs->getDefinition();
            $relation=$this->parentModel->getFieldOrAlias($def["RELATION"]);
            $target=$relation->getRemoteFieldInstances();

            /*
               A TIENE RELACION CON B, a traves del campo ab.
               B, por lo tanto, tiene una inverse relation con A.
               Estamos creando los datasources para B, los cuales son Full, Inner y Not.
               Full, es un left join de A con B, a traves de los campos de inverse relation.
               Inner es un inner join de B con A
               Not es un outer join de B con A.
               Necesitamos los nombres de tabla de ambos modelos, y los campos de relacion de la inverse
               relation.
            */
            $relationFields=$relation->getRelationFields();
            $localTable=$this->parentModel->getTableName();
            $remoteModel=$relation->getRemoteModel();
            $remoteTable=$remoteModel->getTableName();
            $remoteModelName=$remoteModel->objectName->getNormalizedName();
            $extraConds=$relation->getExtraConditions();
                       
            if($remoteModel->objectName->layer != $this->parentModel->objectName->layer)
            {
                $localDataSpace=$this->parentModel->getSerializer()->getCurrentDataSpace();
                $remoteDataSpace=$remoteModel->getSerializer()->getCurrentDataSpace();
                $localTable=$localDataSpace.".".$localTable;
                $remoteTable=$remoteDataSpace.".".$remoteTable;
            }
            
            $params=isset($def["PARAMS"])?$def["PARAMS"]:array();
            $indexes=isset($def["INDEXFIELDS"])?$def["INDEXFIELDS"]:array();

            $fullParams=$indexes + $params;
            // Se preparan las condiciones de filtro.
            $localAlias="tl";
            $remoteAlias="tr";
            foreach($fullParams as $keyP=>$valP)
            {
                $h=0;

                 if(isset($valP["PARAMTYPE"]))
                 {
                     if($valP["PARAMTYPE"]=="DYNAMIC")
                         $condition=array("FILTER"=>$localAlias.".".$valP["FIELD"]." LIKE {%".$keyP."%}");
                 }
                 else
                     $condition=array("FILTER"=>array("F"=>$localAlias.".".$valP["FIELD"],"OP"=>"=","V"=>"{%".$keyP."%}"));

                 if(!isset($valP["REQUIRED"]))
                 {
                      $condition["TRIGGER_VAR"]=$keyP;
                      $condition["DISABLE_IF"]="0";
                 }
                 $condition["FILTERREF"]=$keyP;
                 $conditions[]=$condition;
                 $condKeys[]="[%".$h."%]";
                 $h++;
            }
            if($condKeys)
                $condExpr=implode(" AND ",$condKeys);
            else
            {
                $h=12;
                $p=25;
            }
            
             // Se obtiene a que campos de la tabla local apuntan los campos de la relacion remota.
            foreach($target as $keyP=>$valP)
            {
                $remRemFields=$valP->getRemoteFieldNames();
                    $subParts[]=$remoteAlias.".".$keyP."=".$localAlias.".".$remRemFields[0];                    
            }

            $localRelationSQL=implode(" AND ",$subParts);

            switch($def["INCLUDE"][$remoteModelName]["JOINTYPE"])
            {
                case "INNER":{
                    $base="SELECT * FROM $localTable $localAlias,$remoteTable $remoteAlias WHERE ".$localRelationSQL;
                }break;
            default:
            case "LEFT":{
                $base="SELECT * FROM ".$localTable." $localAlias LEFT JOIN ".$remoteTable." $remoteAlias ON ";                
                $base.=$localRelationSQL;
            }break;
                case "OUTER":{                                       
                    $base="SELECT DISTINCT ".$remoteAlias.".* FROM ".$remoteTable." $remoteAlias,".$localTable." $localAlias WHERE NOT (".$localRelationSQL.")";
                }
            }

             $baseDef=array(
                        "DEFINITION"=>array(
                           // "TABLE"=>$def["TABLE"],
                            "BASE"=>$base,
                            "CONDITIONS"=>$conditions
                                )               
                            );
            // finalmente, las condiciones extra
            if($extraConds)
            {
                for($k=0;$k<count($extraConds);$k++)
                {
                    $baseDef["DEFINITION"]["CONDITIONS"][]=array("FILTER"=>$extraConds[$k]);
                }
            }
             $this->initialize($baseDef);
             return $this;
        }
        static function createDatasourceFromQuery($curModel, $queryName,$queryDef)
        {
            /*
             *             /*
             * "Queries": {"uu": {"TABLES":
             *                  ["Bag", "CarrierService", "ProductDestination"],
             *              "PARAMS": [{"TABLE": "Bag", "RELATION": {"None": "nprendas"}}, {},
             *                         {"TABLE": "CarrierService", "RELATION": {"None": "serviceName"}}]}}
             */

            $tables=$queryDef["TABLES"];                       
            $params=$queryDef["PARAMS"];
            $parameters=array();

            foreach($params as $value)
            {
                $curTable=$value["TABLE"];
                $parameters[$curTable][]=$value["RELATION"]["None"];
            }

            $relMap = \lib\reflection\ReflectorFactory::getRelationMap();
            $q = MysqlDsDefinition::getQuery($tables,array(),$parameters,$relMap["distances"],$relMap);
            // Se obtiene el datasource
            $ds=$curModel->getDataSource($queryName);
            if(!$ds || $ds->mustRebuild())
            {
                $descriptive=array();
                foreach($queryDef["TABLES"] as $curTable)
                {
                    $cModel=\lib\reflection\ReflectorFactory::getModel($curTable);
                    $curDescriptive=$cModel->getDescriptiveFields();
                    // TODO : Aqui tendremos problemas con los campos de ambas tablas que tengan el mismo nombre.
                    $descriptive=array_merge_recursive($descriptive,$curDescriptive);
                }
                $ds=new \lib\reflection\datasources\DatasourceDefinition($queryName,$curModel);
                $ds->create($descriptive,array("_PUBLIC_"),array(),$descriptive,"list");
                $curModel->addDatasource($ds);
                $myDs=new MysqlDsDefinition($curModel,$queryName,$ds);
                $myDs->initialize(array("DEFINITION"=>$q));
                return $myDs;
            }
        }

        function createMxNDsDefinition($fromInverse)
        {                                    
            // TODO:
            // Todo este codigo supone que la relacion MxN se realiza a traves de 1 campo de cada una de las tablas.
            // No soporta el caso en que una o ambas tablas, se relacionen con la otra a traves de mas de 1 campo.
            // Esto habria que ver si el resto del codigo (el codigo que crea la tabla intermedia, etc), lo soporta o no.
            $dsValue=$this->parentDs;
            $serial=$this->parentModel->getSerializer();
            
            $def=$dsValue->getDefinition();
            
            $srcModelName=$def["RELATION"]["MODEL"];
            $modelDef=\lib\reflection\ReflectorFactory::getModel($srcModelName);
            
            $relation=$modelDef->getFieldOrAlias($def["RELATION"]["FIELD"]);
        

            $localRelation=$relation->getRelationTableLocalFields();
            $remFields=$relation->getRelationTableRemoteFields();
            $remClass=$relation->getRemoteObjectName();
            $remModel=$relation->getRemoteInstance();
            $localTable=$modelDef->getTableName();
            $relationTable=$relation->getRelationTable();
            
            $remoteTable=$remModel->getTableName();
            // Para el caso de que los modelos pertenecen a distintos layers, hay que aniadir el nombre de la base de datos
            // como parte del nombre.Esto tambien podria hacerse por defecto.
            
            
                $localDataSpace=$modelDef->getSerializer()->getCurrentDataSpace();
                $remoteDataSpace=$remModel->getSerializer()->getCurrentDataSpace();
                $localTable=$localDataSpace.".".$localTable;
                $remoteTable=$remoteDataSpace.".".$remoteTable;
                // La relationTable es el duenio de este ds
                $relationTable=$this->parentModel->getSerializer()->getCurrentDataSpace().".".$relationTable;
            
            if($conds=$relation->getExtraConditions())
                $conditions=$conds;

            $params=isset($def["PARAMS"])?$def["PARAMS"]:array();
            $indexes=isset($def["INDEXFIELDS"])?$def["INDEXFIELDS"]:array();

            $fullParams=$indexes + $params;
            // Se preparan las condiciones de filtro.
            $h=0;
            foreach($fullParams as $keyP=>$valP)
            {
                
                 $colName=isset($valP["MAPS_TO"])?$valP["MAPS_TO"]:$keyP;
                 if(isset($valP["PARAMTYPE"]))
                 {
                     if($valP["PARAMTYPE"]=="DYNAMIC")
                         $condition=array("FILTER"=>$colName." LIKE {%".$keyP."%}");
                 }
                 else
                     $condition=array("FILTER"=>array("F"=>$colName,"OP"=>"=","V"=>"{%".$keyP."%}"));

                 if(!isset($valP["REQUIRED"]))
                 {
                      $condition["TRIGGER_VAR"]=$keyP;
                      $condition["DISABLE_IF"]="0";
                 }
                 $conditions[]=$condition;
                 $condKeys[]="[%".$h."%]";
                 $h++;
            }
            $condExpr=implode(" AND ",$condKeys);

            foreach($localRelation as $keyP=>$valP)
                    $subParts[]=$localTable.".".$keyP."=".$relationTable.".".$valP;                    
            $localRelationSQL=implode(" AND ",$subParts);

            switch($def["RELATIONTYPE"])
            {
                case "INNER":{
                    $base="SELECT * FROM ".$localTable." INNER JOIN ".$relationTable." ON ".$localRelationSQL." WHERE ".$condExpr;
                }break;
            case "LEFT":{
                $remF=array_keys($remFields);
                $base='SELECT * FROM '.$remoteTable.' LEFT JOIN '.$relationTable.' ON '.$remoteTable.'.'.$remF[0].'='.$relationTable.'.'.$remFields[$remF[0]].' AND  [%0%]';
            }break;
                case "OUTER":{                
                    $localkeys=array_keys($localRelation);
                    $remF=array_keys($remFields);
                    // TODO : Hacerlo funcionar para mas de 1 campo en la relacion MxN
                        $base="SELECT * FROM ".$localTable." WHERE ".$localkeys[0]." NOT IN (SELECT ".$localRelation[$localkeys[0]]." FROM ".$relationTable." WHERE [%0%])";                              
                    
                }
            }
             
             $baseDef=array(
                        "DEFINITION"=>array(
                            "BASE"=>$base,
                            "CONDITIONS"=>$conditions
                                )               
                            );             
             $this->initialize($baseDef);
             return $this;

        }

        public function discoverFields()
        {
            $definition=$this->getDefinition();
            $modelName=$this->parentModel->objectName->getNamespaced();
            
            // Hay que eliminar todos los filtros que haya sobre la query, ya que solo
            // nos interesan los campos.

            // Se intentan descubrir que campos son los devueltos por esta query.
            $definition["CONDITIONS"]=array();
            $minDef["TABLE"]=$definition["DEFINITION"]["TABLE"];
            $minDef["BASE"]=$definition["DEFINITION"]["BASE"];
            if($minDef["BASE"]=="")
                $minDef["BASE"]="SELECT * FROM ".$minDef["TABLE"];
            $qb=new \lib\storage\Mysql\QueryBuilder($minDef);
            $q=$qb->build();
            $q=preg_replace(array('/\[\%[^%]+\%\]/','/{\%[^%]+\%}/'),array("true","'-1'"),$q);
            $layer=$this->parentModel->objectName->layer;

            // Se utiliza mysqli ya que devuelve mejor metadata.
            $curSer=$this->parentModel->getSerializer();
            $d=$curSer->definition["ADDRESS"];

            $key=$d["host"].$d["user"].$d["database"]["NAME"];
            if(!isset(MysqlDsDefinition::$connections[$key]))
            {
                MysqlDsDefinition::$connections[$key]=mysqli_connect($d["host"],$d["user"],$d["password"],$d["database"]["NAME"]);
            }
            $connRes=MysqlDsDefinition::$connections[$key];

            $sentencia = mysqli_prepare($connRes,$q);

            /* obtener el conjunto de resultados para los metadatos */
            $res = mysqli_stmt_result_metadata($sentencia);

            if(!$res)
            {

                echo $q."<br>";
                echo "Error al probar la query asociada al datasource ".$this->dsName." del objeto ".$this->parentModel->objectName->getNormalizedName();
                echo mysqli_error($connRes);
                
                exit();
            }

            $metaData=mysqli_fetch_fields($res);
            mysqli_free_result($res);
            
            $parentTable=$this->parentModel->getTableName();

            // Se obtienen los nombres que los campos del modelo han creado sobre la tabla.
            // Esto es importante para los campos compuestos, ya que se mapean a varias columnas.
            foreach($this->parentModel->fields as $fieldName=>$field)
            {
                $types=$field->getType();
                $serializers=array();
                $serType="MYSQL";
                foreach($types as $typeKey=>$typeValue)
                {                       
                    $def=$typeValue->getDefinition();
                    
                    $serializers[$typeKey]=\lib\model\types\TypeFactory::getSerializer($def["TYPE"],$serType);
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
            $typeConversions=array(
                0=>"Decimal",
                1=>"Integer", // TINY
                2=>"Integer", // SHORT
                3=>"Integer", // LONG
                4=>"Float", // FLOAT
                5=>"Float", // DOUBLE
                6=>NULL, // NULL
                7=>"Timestamp",// TIMESTAMP
                8=>"Integer",//LONGLONG
                9=>"Integer",//INT24
                10=>"DateTime",//DATE
                11=>"DateTime",//TIME
                12=>"DateTime",//DATETIME
                13=>"Integer",// YEAR
                14=>"DateTime",// NEWDATE
                246=>"Decimal",
                247=>"Enum",// ENUM
                248=>"Enum",// SET
                249=>"Text",//"TINY_BLOB"
                250=>"Text",//"MEDIUM_BLOB"
                251=>"Text",//"LONG_BLOB"
                252=>"Text",//"BLOB"
                253=>"String",// VAR_STRING
                254=>"String",//STRING
                255=>"String" // GEOMETRY
                );

            $nFields=count($metaData);
            $returnedFields=array();
            for($k=0;$k<$nFields;$k++)
            {
                $fTable=$metaData[$k]->orgtable?$metaData[$k]->orgtable:$metaData[$k]->table;
                $fName=$metaData[$k]->orgname?$metaData[$k]->orgname:$metaData[$k]->name;
                $fNameAlias=$metaData[$k]->name;
                

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
                $remTable=$this->findTable($fTable,$fName);
                if($remTable)
                {
                    $returnedFields[$fNameAlias]=array("MODEL"=>$remTable,"FIELD"=>$fName);
                    continue;
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
                        $fieldType=$metaData[$k]->type;
                        $maxlength=$metaData[$k]->max_length;
                        
                        if( !isset($typeConversions[$fieldType]) )
                        {
                            printWarning("Atencion.En el datasource ".$this->dsName." del objeto ".$modelName." devuelve el campo ".$fName.",de tipo ".$parts[0].", que no se sabe como mapear a nuestros tipos de datos .");
                            continue;
                        }
                        $returnedFields[$fName]=array("TYPE"=>$typeConversions[$fieldType]);
                        if($typeConversions[$fieldType]=="String")
                        {
                            $returnedFields[$fName]["MAXLENGTH"]=$maxlength;
                        }

                        
                        //    if(!$data)
                        //        printWarning("Atencion.En el datasource ".$this->dsName." del objeto ".$modelName." devuelve el campo ".$fName.",de tipo ".$typeConversions[$fieldType].", que pertenece a la tabla $fTable.<br>Se crea una metadata generica para el campo.");
   
            }
            $this->parentDs->setFields($returnedFields);
            return $returnedFields;       
        }

        function findTable($tableName,$field)
        {

            $objName=str_replace('_','\\',$tableName);
            global $APP_NAMESPACES;
            foreach($APP_NAMESPACES as $value)
            {
                $objects=\lib\reflection\ReflectorFactory::getObjectsByLayer($value);
                foreach($objects as $key2=>$value2)
                {
                    if(strtolower($value2->getTableName())==$tableName)
                    {
                        if($value2->getField($field))
                            return $value2->objectName->getNamespaced();
                        return null;
                    }
                }
            }
            return null;
        }
    function findTableField($tableName,$field)
    {

        $objName=str_replace('_','\\',$tableName);
        global $APP_NAMESPACES;
        foreach($APP_NAMESPACES as $value)
        {
            $objects=\lib\reflection\ReflectorFactory::getObjectsByLayer($value);
            foreach($objects as $key2=>$value2)
            {
                if(strtolower($value2->getTableName())==$tableName)
                {
                    $f=$value2->getField($field);
                    if($f)
                        return array("MODEL"=>$value2->objectName->getNamespaced(),"FIELD"=>$f);;
                    return null;
                }
            }
        }
        return null;
    }
        function getDefinition()
        {
            return $this->definition;
        }        


        /**
         * FUNCIONES PARA OBTENER QUERIES A PARTIR DE DEFINICIONES DE 
         * TABLAS DESTINO + TABLAS PARAMETRO. 
         *  
         */
        static function expr2Query($expr,$definition,$lastTable,$joinType=" INNER", $mode=0)
        {
            $usedTables=array();
            $parts=explode("/",$expr);
            $q="";
            for($k=1;$k<count($parts);$k++)
            {
                $p2=explode("|",$parts[$k]);
            
                if(count($p2)==2)
                {
                    $model=\lib\reflection\ReflectorFactory::getModel($p2[0]);
                    $p2[0]=$model->getTableName();
                    if($usedTables[$p2[0]])
                    {
                        $lastTable=$p2[0];
                        continue;
                    }

                    if($mode==1 && $k==1)
                    {
                        $q=$lastTable.".".$definition["keys"][$lastTable]." IN ( SELECT ".$p2[0].".".$p2[1]." FROM ".$p2[0];
                    }
                    else
                        $q.=$joinType." JOIN ".$p2[0]." ON ".$p2[0].".".$p2[1]."=".$lastTable.".".$definition["keys"][$lastTable];                

                    $lastTable=$p2[0];
                }
                else
                {
                    $p2=explode("[",$parts[$k]);                
                    $model=\lib\reflection\ReflectorFactory::getModel($p2[0]);
                    $p2[0]=$model->getTableName();

                    if($usedTables[$p2[0]])
                    {
                        $lastTable=$p2[0];
                        continue;
                    }   
                    if($mode==1 && $k==1)
                    {
                        $q=$lastTable.".".$p2[1]." IN ( SELECT ".$p2[0].".".$definition["keys"][$p2[0]]." FROM ".$p2[0];
                    }
                    else         
                        $q.=$joinType." JOIN ".$p2[0]." ON ".$lastTable.".".$p2[1]."=".$p2[0].".".$definition["keys"][$p2[0]];
                    $lastTable=$p2[0];                
                }
                $usedTables[$p2[0]]=1;
                $joinType=" INNER";
            }
            return array("query"=>$q,"used"=>$usedTables);
        }

        // valueTables => array de tables.
        // ConditionFields=>array de condiciones fijas.Tipo Tabla=>condiciones.
        // ParameterFields=>array de condiciones variables.Tipo Tabla=>condiciones.

        static function getQuery($valueTables,$conditionFields,$parameterFields,$schema,$definition)
        {
            $targetTables=array_merge($valueTables,array_keys($conditionFields),array_keys($parameterFields));    
            $minDist=9999;
            $curTable="";
            $distances=$schema[0];
            foreach($distances as $name=>$distData)
            {
                $curDist=0;
                foreach($targetTables as $value)
                {
                    if($value!=$name)
                    {
                        if($distData[$value]==0)
                            break;
                        $curDist+=$distData[$value];
                    }
                }
                if($curDist==0)
                continue;
                if($curDist < $minDist)
                {
                    $curTable=$name;
                    $minDist=$curDist;
                }
            }
                $q=$curTable;

                $usedTables=array();
                foreach($targetTables as $value)
                {        
                    $joinType=" INNER";
                    if($value[0]=="?")
                    {
                        $joinType=" LEFT";
                        $value=substr($value,1);
                    }
                    $lastTable=$curTable;
                    $parts=explode("/",$schema[1][$curTable][$value]);
                    $results=MysqlDsDefinition::expr2Query($schema[1][$curTable][$value],$definition,$lastTable,$joinType);
                    $q.=$results["query"];
                    $usedTables=array_merge($usedTables,$results["used"]);
                }


            // Ahora, hace falta aniadir tanto las condiciones fijas, como los parametros.
            // Para ello, de las tablas ya usadas, se busca aquellas que se acerque m�s a las tablas de condicion.
            $subConds=array_merge_recursive($conditionFields,$parameterFields);
            $utables=array_keys($usedTables);
            $ccond=0;
            $extCond=0;
            foreach($subConds as $condTable=>$condParams)
            {
                $ttable=$key;
                $mDist=9999;

                foreach($utables as $utable)
                {            
                    if($utable == $condTable)
                    {
                        $mDist=0;
                        break;
                    }
                    $dist=$schema[0][$utable][$condTable];
                    if($dist < $mDist)
                    {                
                        $mDist=$dist;
                        $cand=$utable;
                    }
                }
               // La condicion es sobre un campo que ya esta en la query principal.
                    foreach($condParams as $key=>$value)
                    {
                        if(is_int($key)) // Entonces, es un parametro
                        {                                        
                            $curCond[$ccond]=array("FILTER"=>array("F"=>$value,"OP"=>"=","V"=>"{%".$value."%}"));
                            $curCondition="[%".$ccond."%]";
                            $ccond++;
                        }
                        else
                        {
                            $curCondition=$key."='".$value."'";
                        }                
                    }

                if($extCond==0)
                    $q.=" WHERE ".$curCondition;
                else
                    $q.=" AND ".$curCondition;
                $extCond++;
            }


           // Finalmente, se compone el inicio de la query:
           $finalQuery="SELECT ";
           $sParts=array();
           for($k=0;$k<count($valueTables);$k++)    
           {
               $ttable=$targetTables[$k];
               if($ttable[0]=='?')
                   $ttable=substr($ttable,1);
               $model=\lib\reflection\ReflectorFactory::getModel($ttable);
               if(!$model)
                   echo "TABLE:$ttable<br>";
               $ttable=$model->getTableName();

               $sParts[]=$ttable.".*";
           }
           $finalQuery.=implode(",",$sParts)." FROM ".$q;
           return array("BASE"=>$finalQuery,"CONDITIONS"=>$curCond);
        }

    public function discoverQueryFields($q)
    {
        $layer=$this->parentModel->objectName->layer;

        // Se utiliza mysqli ya que devuelve mejor metadata.
        $curSer=$this->parentModel->getSerializer();
        $d=$curSer->definition["ADDRESS"];

        $key=$d["host"].$d["user"].$d["database"]["NAME"];
        if(!isset(MysqlDsDefinition::$connections[$key]))
        {
            MysqlDsDefinition::$connections[$key]=mysqli_connect($d["host"],$d["user"],$d["password"],$d["database"]["NAME"]);
        }
        $connRes=MysqlDsDefinition::$connections[$key];

        $sentencia = mysqli_prepare($connRes,$q);

        if(!$sentencia)
        {
            echo $q."<br>";
            $err=mysqli_error($connRes);
            echo $err;
            debug("Error al probar la query asociada al datasource ".$this->dsName." del objeto ".$this->parentModel->objectName->getNormalizedName().":$err");
            _d(mysql_error($connRes));

            exit();
        }

        /* obtener el conjunto de resultados para los metadatos */
        $res = mysqli_stmt_result_metadata($sentencia);



        $metaData=mysqli_fetch_fields($res);
        mysqli_free_result($res);

        $typeConversions=array(
            0=>"Decimal",
            1=>"Integer", // TINY
            2=>"Integer", // SHORT
            3=>"Integer", // LONG
            4=>"Float", // FLOAT
            5=>"Float", // DOUBLE
            6=>NULL, // NULL
            7=>"Timestamp",// TIMESTAMP
            8=>"Integer",//LONGLONG
            9=>"Integer",//INT24
            10=>"DateTime",//DATE
            11=>"DateTime",//TIME
            12=>"DateTime",//DATETIME
            13=>"Integer",// YEAR
            14=>"DateTime",// NEWDATE
            246=>"Decimal",
            247=>"Enum",// ENUM
            248=>"Enum",// SET
            249=>"Text",//"TINY_BLOB"
            250=>"Text",//"MEDIUM_BLOB"
            251=>"Text",//"LONG_BLOB"
            252=>"Text",//"BLOB"
            253=>"String",// VAR_STRING
            254=>"String",//STRING
            255=>"String" // GEOMETRY
        );

        $nFields=count($metaData);
        $returnedFields=array();
        $tables=array();
        $fields=array();
        $replacements=array("look"=>array(),"replace"=>array());
        $nAliases=0;
        for($k=0;$k<$nFields;$k++)
        {
            $fTable=$metaData[$k]->orgtable?$metaData[$k]->orgtable:$metaData[$k]->table;
            $fName=$metaData[$k]->orgname?$metaData[$k]->orgname:$metaData[$k]->name;
            $fNameAlias=$metaData[$k]->name;


            // El campo no pertenece a este modelo.Hay que hacer 2 cosas:
            // 1: Obtener su nombre, y asegurarse de que no contiene caracteres extranios.Es decir, "count(*)" no deberia
            //    estar permitido, y deberia tener un alias.
            if( !preg_match("/^[a-zA-Z][a-zA-Z0-9_]*$/",$fName) )
            {
                $q=str_replace($fName,$fName." as al".$nAliases,$q);
                $replacements["look"][]=$fName;
                $replacements["replace"][]=$fName." as al".$nAliases;

                $fName="al".$nAliases;
                $nAliases++;
                // Por ahora, solo mostramos un warning
                echo ("Atencion.El datasource ".$this->dsName." del objeto ".$this->parentModel->objectName->getNamespaced()." devuelve el campo ".$fName." que deberia tener su propio alias.");
                //continue;
            }
            $fieldType=$metaData[$k]->type;
            $maxlength=$metaData[$k]->length;

            if( !isset($typeConversions[$fieldType]) )
            {
                printWarning("Atencion.En el datasource ".$this->dsName." del objeto ".$modelName." devuelve el campo ".$fName.",de tipo ".$parts[0].", que no se sabe como mapear a nuestros tipos de datos .");
                continue;
            }
            $returnedFields[$fName]=array();
            $tables[]=$fTable;
            $fields[]=array("TABLE"=>$fTable,"FIELD"=>$fName,"ALIAS"=>$fNameAlias,"TYPE"=>$typeConversions[$fieldType],"MAXLENGTH"=>$maxlength);
        }
        return array("query"=>$q,"tables"=>array_unique($tables),"fields"=>$fields,"replacements"=>$replacements);
    }
    
}

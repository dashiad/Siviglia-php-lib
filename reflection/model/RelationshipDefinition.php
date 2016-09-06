<?php

namespace lib\reflection\model;

class RelationshipDefinition extends \lib\reflection\model\BaseRelation
{
    function __construct($name,$parentModel,$definition)
    {
        BaseRelation::__construct($name,$parentModel,$definition);

        // Una relacion simple, siempre apunta a 1 solo registro de una tabla remota.
        if(!isset($this->definition["ROLE"]))
        {                 
            if($this->parentModel->getOwnershipField()==$name)            
                $role="BELONGS_TO";                 
            else
               $role="HAS_ONE";                     
             $this->definition["ROLE"]=$role; 
        }

        if($this->definition["ROLE"]=="BELONGS_TO")
            $this->definition["REQUIRED"]="1";

        if(!isset($this->definition["CARDINALITY"]))
            $this->definition["CARDINALITY"]=1;            
        
             
    }
    function getDefaultInputName($definition)
    {
        // En caso de que la relacion sea a un modelo de tipo "Relacion multiple", se instancia esa relacion.
        // TODO : tener en cuenta las cardinalidades a la hora de seleccionar el input.
        $remModel=$this->getRemoteModel();
        if($remModel->getRole()=="MULTIPLE_RELATION")
            $multiplicity="M:N";        
        else
            $multiplicity=$this->definition["MULTIPLICITY"];
        return "Relation".($multiplicity?str_replace(":","x",$multiplicity):"1x1");
    }
    function getDefaultInputParams($form=null,$actField=null)
    {

        $targetModel=$this->getRemoteModel();
        if($actField!=null){
            // TODO: Aqui se obtiene asi la definicion del campo de la accion, ya que, en caso de llamar a getDefinition(),
            // FieldDef retorna una definicion que sirve para un modelo, no para una accion.
            $actDef=$actField->definition;
            $datasource=$actDef["DATASOURCE"];

        }
        else
        {
            $datasource=array("NAME"=>"FullList","OBJECT"=>$targetModel->objectName->getNormalizedName());
        }
        $ds=$targetModel->getDataSource($datasource["NAME"]);
        if(!$ds)
        {
            var_dump($actDef);
            echo "No se encuentra el datasource ".$datasource["NAME"]." del objeto ".$targetModel->objectName->getNormalizedName();
        }
        $dsDef=$ds->getDefinition();
        $dsFields=$dsDef["FIELDS"];
        $valueFields=array();
        // Primero, se obtienen los campos que definen el VALUE del input
        $targetKeyFields=array_values($this->definition["FIELDS"]);
        if(count($targetKeyFields)==1)
            $valueField=$targetKeyFields[0];
        else
            $valueField=$targetKeyFields;

        // Ahora se obtienen los campos que definen el LABEL del input
        // Por defecto, labelFields va a ser la interseccion entre las labelFields del modelo remoto, y los campos obtenidos por
        // el datasource.
        $modelLabelFields=array_keys($targetModel->getLabelFields());
        $labelFields=array();
        $datasourceModelName=new \lib\reflection\model\ObjectDefinition($datasource["OBJECT"]);
        foreach($dsFields as $key=>$value)
        {
            $fieldModelName=new \lib\reflection\model\ObjectDefinition($value["MODEL"]);

            if($datasourceModelName==$fieldModelName && in_array($value["FIELD"],$modelLabelFields))
                $labelFields[]=$key;
            // TODO : Asegurarse de que el datasource retorna lo que hay en $targetKeyFields
        }
        // Si la interseccion era nula, los $labelFields van a ser todos los campos del datasource.
        if(count($labelFields)==0)
            $labelFields=array_keys($dsFields);        
        


        // NULL_RELATION son los valores de relacion que significa establecer la relacion a 0.
         return array("LABEL"=>$labelFields,"VALUE"=>$valueField,"NULL_RELATION"=>array(-1),"MAX_RESULTS"=>20,"PRE_OPTIONS"=>array(-1=>"Select an option"),"DATASOURCE"=>$datasource);
    }

    function isAlias()
    {
        return false;
    }
    function isDescriptive() {
        return $this->definition["DESCRIPTIVE"] == true;
    }
    function isSearchable(){
        if(!isset($this->definition["SEARCHABLE"]))
            return false;
        return $this->definition["SEARCHABLE"];
    }

    function isLabel() {
        return $this->definition["ISLABEL"] == true;
    }
    static function createRelation($name, $parentModel, $targetObject, $targetField, $relationName = null) {

        $def["TYPE"] = "Relationship";

        if ($relationName == null)
            $relationName = $targetObject;

        if (is_array($targetField)) {
            if (\lib\php\ArrayTools::isAssociative($targetField)) {
                $def["FIELDS"] = $targetField;
            } else {
                $fieldKeys=array_keys($targetField);
                if(count($fieldKeys)>0)
                {
                    foreach ($targetField as $key => $value)
                        $def["FIELDS"][$relationName . "_" . $value] = $value;
                }
                else
                    $def["FIELDS"][$relationName]=$targetField[$fieldKeys[0]];
                
            }
        }

        
        $objNameClass = new \lib\reflection\model\ObjectDefinition($targetObject);
        $objLayer = $objName->layer;
        $objName = $objName->className;
        $def["OBJECT"] = $objNameClass->getNamespaced("compiled");
        return new RelationshipDefinition($name, $parentModel, $def);
    }
    

 
    function createDerivedRelation()
    {  
        
        $targetModel=$this->getRemoteModel();        
        // En caso de que la relacion sea declarada por un objeto A, que es privado de B, la relacion derivada solo se
        // creara si esta relacion apunta a B, o a otro objeto privado de B.
        $localName=$this->parentModel->objectName;
       /* if($localName->isPrivate())
        {
            $targetName=$targetModel->objectName;
            $namespaceModel=$localName->getNamespaceModel();

            if(!
                (($targetName->isPrivate() && $targetName->getNamespaceModel()==$namespaceModel) ||
                 ($targetName->className==$namespaceModel)
                 )
              )
            {             
                return; // No hay que generar la relacion inversa.
            }
        }*/
        $aliases=$targetModel->getAliases();        
        $parentName=$this->parentModel->objectName->getNamespaced();
        foreach($aliases as $key=>$value)
        {
            if($value->isRelation())
            {
                if($value->pointsTo($parentName,$this->name))
                    return;
            }
        }

        
       // Si el objeto que contiene la relacion, es un subtipo del objeto target, 
       // Y la relacion actual apunta a un campo que es indice del objeto remoto,
       // el nombre del alias es exactamente el nombre del tipo actual.
       // Este caso es el siguiente : El objeto A tiene subtipos, y un indice (ai).Uno de los subtipos es B.
       // B, como indice, tiene un campo (bi) que es una relacion al objeto A. Esto debe producir un alias en A, cuyo nombre
       // es exactamente B.

       $subTypes=$targetModel->getSubTypes();
       $aliasName="";
       if($subTypes!==null && in_array($parentName,$subTypes))
       {
           $shouldCheckIndexes=true;
           $fullIndexAliasName=$parentName;
       }
       else
       {
           // Este es el caso de una clase que extiende a otra.
           if($this->parentModel->getExtendedModelName())
           {
               $shouldCheckIndexes=true;
               $fullIndexAliasName="parent";
           }
       }
       
           if($shouldCheckIndexes)
           {
               // Estos son los campos de A, apuntados por este campo de B.
               $values=array_values($this->definition["FIELDS"]);
               // Ahora se obtienen los campos indices de A.
               $indexes=array_keys($targetModel->getIndexFields());
               // Se ve si todos los campos apuntados en la relacion existente en B, existen en la clave de A.
               if(count(array_diff($values,$indexes))==0)
               {
                   $aliasName=$fullIndexAliasName;
               }
           }
       
       if($aliasName=="")
       {
           // Hay que generar un nombre para este alias.
           // Si no existe, primero se intenta con el nombre de la tabla actual.
           $existingAliases=array_keys($targetModel->getAliases());
           // Para crear el alias, no quiero el nombre normalizado.Me vale con className, incluso si es un objeto privado,aunque
           // esto puede provocar colisiones. TODO : Controlar estas colisiones.
           $parentModelName=$this->parentModel->objectName->className;
           if(!in_array($parentModelName,$existingAliases))
               $aliasName=$parentModelName;
           else
               $aliasName=$parentModelName."_".$this->name;
       }
       // Ahora, dependiendo de si el modelo padre de esta relacion es de rol "MULTIPLE_RELATION" o no, creamos una relacion simple,
       // o una relacion inversa.
       $role=$this->parentModel->getRole();
       if($role=="MULTIPLE_RELATION")
       {
           // Hay que ver si la relacion actual es una de las relaciones envueltas en la relacion multiple.
           $parentDef=$this->parentModel->getDefinition();
           $multipleFields=$parentDef["MULTIPLE_RELATION"]["FIELDS"];
           if(in_array($this->name,$multipleFields))
           {
               $newAlias=\lib\reflection\model\aliases\Relationship::createFromRelationship($aliasName,$this->name,$this->parentModel,$this->getRemoteModel());
               $targetModel->addAlias($aliasName,$newAlias);
               $aliasName.="_".$this->name;               
           }
       }

       $newAlias=\lib\reflection\model\aliases\InverseRelation::createInverseRelation($aliasName,
                                                                                          $targetModel,
                                                                                           $parentName,
                                                                                           $this->name);       
       $targetModel->addAlias($aliasName,$newAlias);
       
    }
    function getType()
    {
        $targetObject=\lib\reflection\ReflectorFactory::getModel($this->definition["OBJECT"]);
        foreach($this->definition["FIELDS"] as $key=>$value)
        {
            $remField=$targetObject->getField($value);
            if(!$remField)
            {
                echo "ERROR BUSCANDO CAMPO $value<br>";
                echo "REMOTE FIELDS:<br>";
                var_dump($targetObject);
                $f=$targetObject->getFields();
                echo implode(",",array_keys($f))."<br>";
                var_dump($this->definition);
            }
            $type=$remField->getType();
            // Se sobreescribe el nombre, con el actual.
            $type[$value]->setName($this->name);
            $returned[$this->name]=$type[$value];
        }
        return $returned;
    }
    function getRawType()
    {

        $type=$this->getType();
        
        foreach($this->definition["FIELDS"] as $key=>$value)   
        {
            if(!is_object($type[$key]))
            {
                $h=33;
                $n=55;
            }
            $type2= \lib\model\types\TypeFactory::getType(null,$type[$key]->getDefinition());
        }
        
        return array($this->name=>$type2->getRelationshipType());
    }
}


<?php
  namespace lib\reflection\classes\aliases;
  class Relationship
  {
      var $localFields;
      var $remoteFields;
      var $extraDefinition;
      var $remoteDefinition;
      var $remoteObject;

        function __construct($parentModel,$definition)
        {
            $this->parentModel=$parentModel;
            $this->definition=$definition;
            $this->parseDefinition();
        }

        static function createInverseRelationshipFromRelationship($relationshipName,$relationshipObj,$sourceObj)
        {
            $def["TYPE"]="Relationship";
            $def["OBJECT"]=$sourceObj;

            $targetDef=$relationshipObj->getDefinition();

            if($targetDef["MULTIPLICITY"])
                $def["MULTIPLICITY"]=$targetDef["MULTIPLICITY"];

            if($targetDef["TABLE"])
                $def["TABLE"]=$targetDef["TABLE"];
            
            $fields=$targetDef["FIELD"];
            if(!$fields)
                $fields=$targetDef["FIELDS"];

            if(is_array($fields))
            {
                $def["FIELDS"]=array_flip($fields);
            }
            else
                $def["FIELDS"]=array($fields=>$relationshipName);

            return $def;
        }
        function getTargetObject()
        {
            return $this->definition["OBJECT"];
        }

        function equals($instance)
        {
            $def1=$instance->definition;
            $def2=$this->definition;
            if($def1["TYPE"]!=$def2["TYPE"])
                return false;
            if($def1["OBJECT"]!=$def2["OBJECT"])
                return false;
            if(($def1["TABLE"] || $def2["TABLE"]) && $def1["TABLE"]!=$def2["TABLE"])
                return false;
            return $def1["FIELDS"]==$def2["FIELDS"];
        }


        function getDefinition()
        {
            return $this->definition;
        }
        function isRelation()
        {
            return true;
        }
        function getRemoteObject()
        {
            return $this->remoteObject;
        }
        function parseDefinition()
        {

           
            // Se tienen que calcular que campos hay que crear.Se usa el mismo codigo (copiado) de la clase RelationMxN

             // Se miran los campos involucrados.
            //  Si existen los campos FIELDS[LOCAL] o FIELDS[REMOTE], se usan esos campos para la tabla intermedia.
            //  Si no existe alguno de ellos, se usan las claves del modelo local o remoto.
            $def=$this->definition;

            $remObject=new \lib\reflection\classes\ObjectDefinition($def["OBJECT"]);
            $this->remoteObject=$remObject;
            $remClassName=$remObject->className;

            if($remClassName > $this->parentModel->objectName->className)
            {
                $localP="t1";
                $remoteP="t2";
            }
            else
            {
                $localP="t2";
                $remoteP="t1";
            }

            $local=$def["FIELDS"]["LOCAL"];
        
            $localDef=$this->parentModel->getDefinition();

            if(!$local)
                $local=$localDef["INDEXFIELD"];

            foreach($local as $key=>$value)
            {
                $fieldName=$localP.$value;
                $fields[$fieldName]=\lib\model\types\TypeFactory::getRelationFieldTypeInstance($this->parentModel->objectName->getNamespaced(),$value)->getDefinition();
                $indexes[]=$fieldName;
                $this->localFields[]=$value;
            }

            $remote=$def["FIELDS"]["REMOTE"];
            $remoteDef=\lib\model\types\TypeFactory::getObjectDefinition($def["OBJECT"]);

            

            if(!$remote)
            {
                $remote=$remoteDef["INDEXFIELD"];
            }

            foreach($remote as $key=>$value)
            {
                $fieldName=$remoteP.$value;
                $fields[$fieldName]=\lib\model\types\TypeFactory::getRelationFieldTypeInstance($remObject->getNamespaced(),$value)->getDefinition();
                $indexes[]=$fieldName;
                $this->remoteFields[]=$value;
            }            


        // Primero, hay que ver si las relaciones son unicas o no.Es decir,
        // si dadas las tablas A(a1) y B(b1), la interTable seria A_B(a1,b1).
        // Hay que ver si esas relaciones son unicas o no.
        // En caso de que no lo sean, hay que aniadir una clave unica a la tabla A_B,
        // para identificar univocamente a los objetos.


        if(!$def["RELATIONS_ARE_UNIQUE"])        
        {
            $indexField=$def["TABLE"]."idx";
            $indexes=array(str_replace("_","",$indexField));
            $fields[$indexField]=array("TYPE"=>"UUID");            
        }        

        $this->remoteDefinition=array(
                "TABLE"=>$def["TABLE"],
                "INDEXES"=>$indexes,
                "FIELDS"=>$fields
            );

        // Las definiciones extra se crean solo para MYSQL.
        //if( $serializer->getSerializerType()=="MYSQL" )
        //{
            $this->extraDefinition=array(
                 'ENGINE'=>'InnoDb',
                   'CHARACTER SET'=>'utf8',
                   'COLLATE'=>'utf8_general_ci',
                 'INDEXES'=>array(array('UNIQUE'=>true,
                                  'FIELDS'=>$indexes
                                  ))               
                );
        /*}
        else
        {
            $this->extraDefinition=null;
        }*/
        }


        function hasImplicitTable()
        {
             // Si no se especifica una tabla, se supone que la relacion es con otra clase Model, que
            // ya habra construido su propia tabla.
            if( !$this->definition["TABLE"] )
                return false;
            return true;
        }
        function createStorage($serializer)
        {
            
            if( ! $this->hasImplicitTable())
            {
                return;
            }

            // Se crea una instancia de clase modelo con esta definicion.
            $obj=new FakeModelDefinition($this->remoteDefinition);


            $serializer->createStorage($obj,$this->extraDefinition);
            
        }
  }

  // Clase helper para la creacion de storage

  class FakeModelDefinition
  {
      var $definition;
      var $fields=array();
      function __construct($definition)
      {          
          $this->definition=$definition;

            foreach($this->definition["FIELDS"] as $key=>$value)
            {
                if(\lib\reflection\classes\FieldDefinition::isRelation($value))
                    $this->fields[$key]=new \lib\reflection\classes\RelationshipDefinition($key,$this,$value);
                else
                    $this->fields[$key]=new \lib\reflection\classes\FieldDefinition($key,$this,$value);           
            }
      }
      function getDefinition()
      {
          return $this->definition;
      }
      function getTableName()
      {
          return $this->definition["TABLE"];
      }
      
      
      

  }

?>

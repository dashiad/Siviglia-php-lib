<?php
  namespace lib\reflection\model\aliases;
  class Relationship extends  \lib\reflection\base\AliasDefinition
  {
      var $localFieldNames;
      var $remoteFieldNames;
      var $extraDefinition;
      var $relationTable;
      var $remoteObjectName;
      var $relationTableDefinition;
      var $relationTableLocalFields;
      var $relationTableRemoteFields;
      var $remoteFields;
      var $definition;
      var $relationModelName;
      var $relationsAreUnique;
      
       function __construct($name,$parentModel,$definition)
       {
           parent::__construct($name,$parentModel,$definition);
           if(!isset($this->definition["ROLE"]))
              $this->definition["ROLE"]="HAS_MANY";   
           $this->relationModelName=$this->definition["OBJECT"];
           $this->definition=$definition;
           $this->relationsAreUnique=$definition["UNIQUE_RELATIONS"];
           $this->parseFromModel();
       }
       function getRelationModelName()
       {
           return $this->relationModelName;
       }
       static function createFromRelationship($name,$relationshipName,$relationshipObj,$sourceObj)
       {
           $def["TYPE"]="RelationMxN";
           $def["OBJECT"]=$relationshipObj->objectName->getNormalizedName();
           $def["FIELD"]=$relationshipName;

           // Hay que ver a que otros objetos apunta este modelo relacion.Para ello, de las relaciones que
           // definen la multiple relationship, se elimina la relacion actual, y se ve que relaciones quedan.
           $relDef=$relationshipObj->getDefinition();
           $relFields=$relDef["MULTIPLE_RELATION"]["FIELDS"];
           if(isset($relDef["MULTIPLE_RELATION"]["UNIQUE_RELATIONS"]))
               $relationsAreUnique=$relDef["MULTIPLE_RELATION"]["UNIQUE_RELATIONS"];
           else
               $relationsAreUnique=true;
           
           $diff=array_values(array_diff($relFields,array($relationshipName)));
           
               $remField=$relationshipObj->getField($diff[0]);
               $remModel=$remField->getRemoteModelName();
           $def["REMOTE_MODEL"]=$remModel;
           $def["ROLE"]="HAS_MANY";
           $def["MULTIPLICTY"]="M:N";
           $def["CARDINALITY"]=100;
           $def["UNIQUE_RELATIONS"]=$relationsAreUnique?true:false;           
           return new Relationship($name,$sourceObj,$def);
       }
       // No genera ninguna relacion derivada.
       function createDerivedRelation()
       {

       }
       function getRelationTableLocalFields()
       {
           if($this->relationTableLocalFields)
            return $this->relationTableLocalFields;
           $field=$this->definition["FIELD"];
           $indexes=$this->parentModel->getIndexFields();
           $keys=array_keys($indexes);
           return array($keys[0]=>$field);
       }

       function getRelationTableRemoteFields()
       {

           if($this->relationTableRemoteFields)
               return $this->relationTableRemoteFields;

           $aliases=array();
           $instance=$this->getRemoteInstance();
           $aliases=$instance->getAliases();


           foreach($aliases as $key=>$value)
           {
               if(is_a($value,'lib\reflection\model\aliases\Relationship'))
               {
                   $def=$value->getDefinition();
                   $tempObjName=new \lib\reflection\model\ObjectDefinition($def["REMOTE_MODEL"]);
                   if($def["TYPE"]=="RelationMxN" && $tempObjName->getNamespaced()==$this->parentModel->__objName->getNamespaced())
                   {
                       // Teoricamente, son el mismo objeto.
                       $this->relationTableRemoteFields=$value->getRelationTableLocalFields();
                       break;
                   }
               }
           }
           return $this->relationTableRemoteFields;
       }

        function getRemoteMappedFields()
        {
            $instance=$this->getRemoteInstance();
            $info=array();
            foreach($this->relationTableRemoteFields as $key=>$value)
            {
                $info[$key]=$instance->getField($key);
            }
            return $info;            
        }
       
        function getLocalMappedFields()
        {
            $vals=array();
            $inst=$this->getRelationTable();
            foreach($this->relationTableLocalFields as $key=>$value)
            {
                $vals[$value]=$inst->getField($value);
            }
            return $vals;
        }

        function equals($instance)
        {
            $def1=$instance->definition;
            $def2=$this->definition;
            if($def1["TYPE"]==$def2["TYPE"] && $def1["TABLE"]==$def2["TABLE"])
                return true;
            return false;
        }

        function getMultiplicity()
        {
            return "M:N";
        }
        function relationsAreUnique()
        {
            return $this->relationsAreUnique;
        }
        function getCardinality()
        {
            return $this->definition["CARDINALITY"];
        }
        function isRelation()
        {
            return true;
        }
        function getRemoteInstance()
        {
            return \lib\reflection\ReflectorFactory::getModel($this->definition["REMOTE_MODEL"]);
        }
        function getRemoteObjectName()
        {
            $obj=new \lib\reflection\model\ObjectDefinition($this->definition["REMOTE_MODEL"]);
            return $obj->className;
        }

        function getRelationTableDefinition()
        {
            return $this->relationTableDefinition;
        }

        function getLocalMapping()
        {
            return $this->getRelationTableLocalFields();
        }
        function getRemoteMapping()
        {
            return $this->getRelationTableRemoteFields();
        }
        function getRemoteFieldNames()
        {
            return array_keys($this->getRelationTableRemoteFields());
        }
        function pointsTo($modelName,$fieldName)
        {            

            $objDef=new \lib\reflection\model\ObjectDefinition($this->definition["OBJECT"]);
            if(!$objDef->equals($fieldName))
                return false;

            if(!is_array($fieldName))
                $fieldName=array($fieldName);

            $remoteFields=array_keys($this->getRemoteMapping);
            if(count(array_diff($remoteFields,$fieldName))==0)
                return true;
            return false;
            
        }
        function getRelationTable()
        {
            return $this->relationTable;
        }
       
        function parseFromModel()
        {
            $def=$this->definition;
            $remObject=new \lib\reflection\model\ObjectDefinition($def["REMOTE_MODEL"]);
            
            $relObject=\lib\reflection\ReflectorFactory::getModel($def["OBJECT"]);
            $this->remoteObjectName=$remObject->getNormalizedName();
            $this->relationTable=$relObject->getTableName();
            $relDef=$relObject->getDefinition();
            $relfields=$relDef["MULTIPLE_RELATION"]["FIELDS"];
            foreach($relfields as $value)
            {
                $relFieldDef=$relDef["FIELDS"][$value];
                $pointedField=$relFieldDef["FIELDS"][$value];

                if($this->parentModel->objectName->equals($relFieldDef["OBJECT"]))
                {
                    $this->localFieldNames[]=$pointedField;
                    $this->relationTableLocalFields[$pointedField]=$value;
                    $def["FIELDS"]["LOCAL"][]=$pointedField;
                }
                else
                {
                    $this->remoteFieldNames[]=$pointedField;
                    $this->relationTableRemoteFields[$pointedField]=$value;
                    $def["FIELDS"]["REMOTE"][]=$pointedField;
                }
            }            
        }
        
        function getDefaultInputName($definition)
        {
            return "RelationMxN";
        }
        // El problema para construir el input es saber desde donde se esta llamando a esta relacion;
        // Esta relacion puede ser invocada por cualquiera de las dos tablas interrelacionadas.
        // Por ello, el form que invoca este metodo, debe pasar su instancia, de forma que sea posible
        // identificar de que lado estamos.

        function getFormInput($form,$name,$fieldDef,$inputDef=null)
        {
            // The field definition is ignored, and rebuilt.
            $formClass=$form->parentModel->objectName->getNormalizedName();
         
            if(!$inputDef["TYPE"])
                $inputDef["TYPE"]="/types/inputs/".$this->getDefaultInputName();
            $relDef["MODEL"]=$this->parentModel->objectName->getNormalizedName();
            $relDef["FIELD"]=$this->getName();
             

            return parent::getFormInput($form,$name,$relDef,$inputDef);
        }
        function getDefaultInputParams($form=null,$actDef=null)
        {
            $role=$form->getRole();
            
            $remoteModel= $this->getRemoteInstance();
             if($remoteModel)
             {              
                $remkeys=array_keys($remoteModel->getIndexFields());                   
                $labels=array_keys($remoteModel->getLabelFields());
                $labels=array_values(array_diff($labels,$remkeys));
                $params["LABEL"]=$labels;
                
                if($role=="DeleteRelation")
                {
                    // Si el rol es DeleteRelation, los valores son el campo indice del objeto relacion.
                    $remoteModel=\lib\reflection\ReflectorFactory::getModel($this->getRelationModelName());
                    $remoteFieldsDef=$remoteModel->getIndexFields();
                }
                else
                    $remoteFieldsDef=$this->getRelationTableRemoteFields();

                $params["VALUE"]=array_keys($remoteFieldsDef);
             }
             return $params;
        }

        function generateActions($isAdmin)
        {
            if($isAdmin)
            {
                $prefixL="admin";
                $prefixU="Admin";
            }

            // Las acciones se aniaden al modelo relacion, no a nosotros mismos.
            $targetModelName=$this->getRelationModelName();
            $targetModel=\lib\reflection\ReflectorFactory::getModel($targetModelName);
            // La clave de Add y Set, son el campo de la tabla relacion, que apunta al modelo actual.
            // Eso esta en el campo FIELD.
            $relField=$this->definition["FIELD"];
            
            $localFields = $this->parentModel->getIndexFields();
            $requiredFields=array($this->name=>$this);
            $baseName=ucfirst($relField);

            /*$requiredFields = $targetModel->getRequiredFields();
            $optionalFields = $targetModel->getOptionalFields();
            // Se eliminan de los requiredfields el campo que usamos como indice (el local)
            unset($requiredFields[$relField]);

            // Accion Add
            // Es importante para Add, saber si en la relacion multiple estan permitidas las repeticiones o no.
            // Esto determinara que tipo de  listado va a ser necesario.
            $action=new \lib\reflection\actions\ActionDefinition($prefixU."Add".$baseName,$targetModel);
            if($action->mustRebuild())          
            {
                  $newActions[]=$action->create("AddRelationAction", 
                                     $localFields, $requiredFields,
                                     $optionalFields,null,$isAdmin,array("MODEL"=>$this->parentModel->objectName->getNormalizedName(),
                                                                         "FIELD"=>$this->name));
            }*/            
            if($this->relationsAreUnique())
            {
                $action=new \lib\reflection\actions\ActionDefinition($prefixU."Set".$baseName,$this->parentModel);
                if($action->mustRebuild())          
                {
                      $newActions[]=$action->create("SetRelationAction", 
                                         $localFields, $requiredFields,
                                         array(),null,$isAdmin,$this->name);
                }
            }
            
            if(!$this->relationsAreUnique())
            {
                // Accion Delete
                $action=new \lib\reflection\actions\ActionDefinition($prefixU."Delete".$baseName,$targetModel);
                if($action->mustRebuild())          
                {
                      $newActions[]=$action->create("DeleteRelationAction", 
                                         $localFields, $requiredFields,
                                         array(),null,$isAdmin,$this->name);
                }
            }
            
          return $newActions;
        }
      function getDatasourceCreationCallback()
      {
            return "createFromMxNRelation";           
      }
      function getRemoteFieldInstances()
      {            
            return $this->getRemoteMappedFields();
      }
      function getRelationFields()
      {
          return $this->relationTableLocalFields;
      }
      function getRemoteModelName()
      {
      
          return $this->getRemoteObjectName();
      }
      function getDataSources($targetModel=null,$baseName=null)
      {
          $mapping=$this->getLocalMapping();
          $remote=array_values($mapping);
          $targetModel=\lib\reflection\ReflectorFactory::getModel($this->definition["OBJECT"]);
          $name=$baseName?$baseName:$remote[0];
          return parent::getDataSources($targetModel,$name);
      }
      function getExtraConditions()
      {
          return isset($this->definition["CONDITIONS"])?$this->definition["CONDITIONS"]:null;
      }
  }


?>

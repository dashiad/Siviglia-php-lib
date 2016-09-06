<?php
  namespace lib\reflection\model\aliases;
  class RelationMxNInverseRelation extends \lib\reflection\base\AliasDefinition
  { 
      var $localFieldNames; var $remoteFieldNames; 
      var $targetObj;
      var $targetField;
      var $aliasType;
      
      function __construct($name,$parentModel,$definition) 
      {
          parent::__construct($name,$parentModel,$definition); 
          $this->targetObj=$this->getRemoteInstance();
          $this->targetField=$this->targetObj->getFieldOrAlias($this->definition["FIELD"]);
          if(!isset($this->definition["ROLE"]))
              $this->definition["ROLE"]="HAS_MANY";
      } 
      function isAlias(){ return true;} 
      function createDerivedRelation()
      {
          return null;
      }
      function isRelation()
      {
          return true;
      }
      function getMultiplicity()
      {
         return "M:N";
      }
      function getRelationTableLocalFields()
      {
          return $this->targetField->getRelationTableRemoteFields();
      }
      function getRelationTableRemoteFields()
      {
          return $this->targetField->getRelationTableLocalFields();
      }
      function getLocalMapping()
      {
          return $this->targetField->getRemoteMapping();
      }
      function getRemoteObjectName()
        {
            $obj=new \lib\reflection\model\ObjectDefinition($this->definition["OBJECT"]);
            return $obj->className;
        }

      function getLocalFields()
      {
          $map=$this->getLocalMapping();
          $results=array();
          foreach($map as $key=>$value)
              $results[$key]=$this->parentModel->getFieldOrAlias($key);
          return $results;
      }
      function getRemoteMapping()
      {
          return $this->targetField->getLocalMapping();
      }
      function getRemoteFields()
      {
          $map=$this->getRemoteMapping();
          $results=array();
          $remoteObj=$this->getRemoteInstance();
          foreach($map as $key=>$value)
              $results[$key]=$remoteObj->getFieldOrAlias($key);
          return $results;
      }
      function getRemoteInstance()
      {
          return \lib\reflection\ReflectorFactory::getModel($this->definition["OBJECT"]);
      }
      function getRelationTable()
      {
          return $this->targetField->getRelationTable();
      }
      function getRelationTableName()
      {
        return $this->targetField->getRelationTableName();
      }

      function generateActions($isAdmin)
      {
          $prefixU=$isAdmin?"Admin":"";
          $relValue=$this;
          $localFields =$this->getLocalFields();          
          // Los campos remotos, desde esta relacion inversa, son los campos locales originales.  
          $remoteFields=array($this->name=>$this);
          
          $remoteModel=$this->getRemoteInstance();
          
          $remoteLayer=$remoteModel->getLayer();
          
          $relationName=ucfirst($remoteModel->objectName->getNormalizedName())."_".$this->definition["FIELD"];
          
          $actions=array("Add","Delete","Set");
          foreach($actions as $key)
          {
              $curAct=new \lib\reflection\actions\ActionDefinition($prefixU.$key.$relationName,$this->parentModel);
              if($curAct->mustRebuild())          
                  $curAct->create($key."Relation",                                  
                                  $localFields, 
                                  array(),$remoteFields,  $remoteModel,$isAdmin,$this->name);
              $newActions[]=$curAct;
          }
          return $newActions;
          
      }       
      function pointsTo($modelName,$fieldName)
      {
          $remObj=new \lib\reflection\model\ObjectDefinition($this->definition["OBJECT"]);

          if(!$remObj->equals($modelName))
              return false;

          if(!is_array($fieldName))
              $fieldName=array($fieldName);
          $remoteNames=array_keys($this->getRemoteMapping());

          $int=\lib\php\ArrayTools::compare($remoteNames,$fieldName);
          return $int===0;          
      }
      function getDefaultInputName()
      {
          return "RelationMxN";
      }
      function getFormInput($form,$name,$fieldDef,$inputDef)
      {          
            // The field definition is ignored, and rebuilt.
            $formClass=$form->parentModel->objectName->getNormalizedName();
         
            if(!$inputDef["TYPE"])
                $inputDef["TYPE"]="/types/inputs/".$this->getDefaultInputName();
            $relDef["MODEL"]=$this->parentModel->objectName->getNormalizedName();
            $relDef["FIELD"]=$this->definition["FIELD"];
            $remoteModel= $this->getRemoteInstance();
             if($remoteModel)
             {              
                 if(!$inputDef["PARAMS"]["LABEL"])
                 {   
                     $remkeys=array_keys($remoteModel->getIndexFields());                   
                     $labels=array_keys($remoteModel->getLabelFields());
                     $labels=array_values(array_diff($labels,$remkeys));
                     $inputDef["PARAMS"]["LABEL"]=$labels[0];
                 }
                 $remoteFieldsDef=$this->getRelationTableRemoteFields();
                 if(!$inputDef["PARAMS"]["VALUE"])
                 {
                          $inputDef["PARAMS"]["VALUE"]=array_keys($remoteFieldsDef);
                 }                 
              } 

            return parent::getFormInput($form,$name,$relDef,$inputDef);
        }
      function getFormInputErrors($definition)
      {
          return array();
      }
      function getDefaultInputParams()
        {
            $remoteModel= $this->getRemoteInstance();
             if($remoteModel)
             {              
                $remkeys=array_keys($remoteModel->getIndexFields());                   
                $labels=array_keys($remoteModel->getLabelFields());
                $labels=array_values(array_diff($labels,$remkeys));
                $params["LABEL"]=$labels;
                $remoteFieldsDef=$this->getRelationTableRemoteFields();
                $params["VALUE"]=array_keys($remoteFieldsDef);
             }
             return $params;
        }
       function getDatasourceCreationCallback()
       {
            return "createFromInverseMxNRelation";           
       }
       function getRelationFields()
       {
          return $this->relationTableLocalFields;
       }
       function getRemoteModelName()
       {
           return $this->getRemoteObjectName();
       }
  }
  

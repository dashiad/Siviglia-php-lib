<?php
  namespace lib\reflection\model\aliases;
  class InverseRelation extends \lib\reflection\model\BaseRelation
  {
      var $localFieldNames;
      var $remoteFieldNames;
      var $aliasType;
      var $targetField;
      var $targetModel;
      var $localMapping;
      var $remoteMapping;
      var $datasources=null;
      function __construct($name,$parentModel,$definition) 
      {
          
          parent::__construct($name,$parentModel,$definition); 
          $this->targetModel=$this->getRemoteModel();
          $remNames=array_values($this->getRemoteFieldNames());
          $remFName=$remNames[0];
          
          $this->targetField=$this->targetModel->getField($remFName); 
      }
       
      function isAlias(){ return true;}
      
      function createInverseRelation($name,$parentModel,$targetObject,$relName)
      {
          echo "Creando relacion inversa $relName para ".$parentModel->objectName->className."<br>";
          $def=array("TYPE"=>"InverseRelation",
                     "OBJECT"=>$targetObject,
                     "FIELD"=>$relName);
          $remModel=\lib\reflection\ReflectorFactory::getModel($def["OBJECT"]);
          $field=$remModel->getField($relName);
          $role=$field->getRole();
          $multiplicity=$field->getMultiplicity();
          
          if($role!="HAS_MANY")
          {
              if($multiplicity=="1:1")
              {
                  $def["ROLE"]="HAS_ONE";
                  $def["MULTIPLICITY"]="1:1";
                  $def["CARDINALITY"]=1;
              }
              else
              {
                  // Lo que no es posible en las relaciones inversas, es conocer la cardinalidad
                  // de la relacion.Lo tendria que especificar siempre el usuario.
                  $def["ROLE"]="HAS_MANY";
                  $def["MULTIPLICITY"]="1:N";
                  $def["CARDINALITY"]=100;
              }
          }
          return new InverseRelation($name,$parentModel,$def);
          
      }
      
      function getMultiplicity()
      {
          $mult=$this->targetField->getMultiplicity();
          return implode(":",array_reverse(explode(":",$mult)));
      }
      
        function getLocalFieldInstances()
        {
            // La relacion puede ser simple o multiple.
            // Es decir, el objeto remoto (una relacion), puede
            // componerse de varios campos.Se obtienen los campos
            // que son apuntados, en el modelo actual, por el modelo
            // remoto (la relacion).
            $fields=$this->targetField->getRemoteFields();
            foreach($fields as $curF)
                $result[$curF]=$this->parentModel->getField($curF);
            return $result;
        }
        function getRemoteFieldInstances()
        {        
                $fields=$this->targetField->getLocalFieldNames();
                foreach($fields as $curF)
                    $result[$curF]=$this->targetModel->getField($curF);
                return $result;
        }
                
        function createDerivedRelation()
        {
            return null;
        }
        function pointsTo($modelName,$fieldName)
       {
          $remObj=new \lib\reflection\model\ObjectDefinition($this->definition["OBJECT"]);
          
          if($modelName!=$remObj->equals($modelName))
              return false;
          
          if(!is_array($fieldName))
              $fieldName=array($fieldName);
          $remoteNames=array_keys($this->getRemoteFieldInstances());
          $int=\lib\php\ArrayTools::compare($remoteNames,$fieldName);
          return $int===0;          
      }
      function generateActions()
      {
          // En principio, sin acciones.
          return array();
      }
      function getDatasourceCreationCallback()
      {
            return "createFromInverseRelation";           
      }

      function getDataSources()
        {
           $callbackName=$this->getDatasourceCreationCallback();        

            if($this->datasources!=null)
                return $this->datasources;
            $perms=array("_PUBLIC_");
            $isadmin=false;

            $multiplicity=$this->targetField->getMultiplicity();

            if($multiplicity=="1:1")
            {
                $innerDs=new \lib\reflection\datasources\DatasourceDefinition($this->name,$this->parentModel);

                  if($innerDs->mustRebuild())            
                      $innerDs->$callbackName($this,"MxNlist","INNER",$isadmin,$perms);
                  $this->datasources[]=$innerDs;

            }
            else
            {

              $fullName="Full".ucfirst($this->name);
              $fullDs=new \lib\reflection\datasources\DatasourceDefinition($fullName,$this->parentModel);
              if($fullDs->mustRebuild())
                  $fullDs->$callbackName($this,"MxNlist","LEFT",$isadmin,$perms);

              $this->datasources[]=$fullDs;
         
              $notName="Not".ucfirst($this->name);
            
              $notDs=new \lib\reflection\datasources\DatasourceDefinition($notName,$this->parentModel);
              if($notDs->mustRebuild())
                  {
                  $notDs->$callbackName($this,"MxNlist","OUTER",$isadmin,$perms);

                  }
              $this->datasources[]=$notDs;
             }
             return $this->datasources;
        }
      function getDefaultInputName($definition)
      {
          return "RelationMxN";
      }

      function getFormInput($form,$name,$fieldDef,$inputDef=null)
      {          
          // Se supone que aqui solo se llama, si esta inverse relationship apunta a un objeto
          // con el rol "MULTIPLE_RELATION".
          $remoteModel=$this->getRemoteModel();
          $role=$remoteModel->getRole();
          if($role!="MULTIPLE_RELATION")          
              return "";
            
          $formClass=$form->parentModel->objectName->getNormalizedName();
          if(!$inputDef["TYPE"])
                $inputDef["TYPE"]="/types/inputs/".$this->getDefaultInputName();

          $relDef["MODEL"]=$this->parentModel->objectName->getNormalizedName();
          $remfields=array_values($this->definition["FIELDS"]);                   
          $remoteField=$remfields[0];
          $relDef["FIELD"]=$this->name;

          // Se tiene que obtener el objeto con el que nos relaciona el modelo intermedio.
          $def=$remoteModel->getDefinition();
          $mult=$def["MULTIPLE_RELATION"]["FIELDS"];
          foreach($mult as $curF)
          {
              if($curF==$remoteField)continue;
              // Este es el campo en el modelo intermedio, que apunta a la tabla remota.   
                 $remRemField=$curF;
          }

          if(!$inputDef["PARAMS"]["LABEL"])
               $inputDef["PARAMS"]["LABEL"]=$this->getDefaultInputLabels($remoteModel,$remRemField);
          
          if(!$inputDef["PARAMS"]["VALUE"])
                $inputDef["PARAMS"]["VALUE"]=array($remRemField);
                           
            return parent::getFormInput($form,$name,$relDef,$inputDef);
        }

      function getFormInputErrors($definition)
      {
          return array();
      }
      function getDefaultInputParams($form=null,$actDef=null)
        {
            $p=$this->definition;
            $remfields=array_values($this->definition["FIELDS"]);                   
            $remoteField=$remfields[0];
            $remoteModel=$this->getRemoteModel();
            $role=$remoteModel->getRole();
            if($role!="MULTIPLE_RELATION")          
                return "";
            $def=$remoteModel->getDefinition();
            $mult=$def["MULTIPLE_RELATION"]["FIELDS"];
            foreach($mult as $curF)
            {
              if($curF==$remoteField)continue;
              // Este es el campo en el modelo intermedio, que apunta a la tabla remota.   
                 $remRemField=$curF;
            }
          $params["LABEL"]=$this->getDefaultInputLabels($remoteModel,$remRemField);
          $params["VALUE"]=array($remRemField);
          return $params;
        }
      function getDefaultInputLabels($remoteModel,$remField)
      {
            // remoteModel es el objeto intermedio.remRemModel es el objeto remoto.               
               $remRemModel=$remoteModel->getField($remField)->getRemoteModel();
               $remkeys=array_keys($remRemModel->getIndexFields());
               $labels=array_keys($remRemModel->getLabelFields());
               $labels=array_values(array_diff($labels,$remkeys));
               return $labels[0];
      }
      function getExtraConditions()
      {
          return isset($this->definition["CONDITIONS"])?$this->definition["CONDITIONS"]:null;
      }
      
  }
  

<?php
namespace lib\reflection\model\aliases;
// Esta clase se usa para generar los datasources , acciones, etc, necesarios para relaciones MxN, donde existe un modelo intermedio.
// La relacion MxN existe entre un objeto A y B, y se almacena en la tabla A_B
class FakeRelationship extends Relationship
{
  function __construct($name,$multipleModel)
  {
     $this->name=$name;
     $this->multipleModel=$multipleModel;
  }
  function setSideFromField($ownerSide)
  {
      $this->initialize($ownerSide);
  }
  // $ownerModel es un objectDefinition
  function setSideFromModel($ownerModel)
  {
     $def=$this->multipleModel->getDefinition();
     $defMul=$def["MULTIPLE_RELATION"];
     $this->fieldNames=$defMul["FIELDS"];
     foreach($this->fieldNames as $value)
     {
         $field=$this->multipleModel->getField($value);
         if($ownerModel->equals($field->getRemoteObjectName()))
             return $this->initialize($value);
     }
  }
  function initialize($ownerSide)
  {
     $def=$this->multipleModel->getDefinition();
     $defMul=$def["MULTIPLE_RELATION"];
     $this->fieldNames=$defMul["FIELDS"];
     $this->multipleModelName=''.$this->multipleModel->objectName;
     foreach($this->fieldNames as $value)
     {
         $field=$this->multipleModel->getField($value);
         $remFields=$field->getRemoteFieldNames();         
         $arr=array();
         $arr[$remFields[0]]=$value;
         if($value==$ownerSide)
         {
             $this->parentModel=$field->getRemoteModel();
             $this->relationTableLocalFields=$arr;

         }
         else
         {
             $this->relationTableRemoteFields=$arr;
             $this->remoteObjectName=$field->getRemoteObjectName();
         }
     }

  }
  function getRelationTableLocalFields()
  {
      return $this->relationTableLocalFields;
  }
  function getRelationTableRemoteFields()
  {
      return $this->relationTableRemoteFields;
  }
  function getRelationTable()
  {
      return $this->parentModel->getTableName();
  }
  function getRemoteObjectName()
  {
      return $this->remoteObjectName;
  }
  function getRemoteInstance()
  {
      return \lib\reflection\ReflectorFactory::getModel($this->getRemoteObjectName());
  }
  function getDataSources()
  {

           $callbackName="createFromMxNRelation";

            if($this->datasources!=null)
                return $this->datasources;
            $perms=array("_PUBLIC_");
            $isadmin=false;

            $innerDs=$this->parentModel->getDataSource($this->name);
              if(!$innerDs)
                  $innerDs=new \lib\reflection\datasources\DatasourceDefinition($this->name,$this->parentModel);

              if($innerDs->mustRebuild())            
                  $innerDs->$callbackName($this,"MxNlist","INNER",$isadmin,$permissions,$this->multipleModelName);
              $this->datasources[]=$innerDs;


              $fullName="Full".ucfirst($this->name);
              $fullDs=$this->parentModel->getDataSource($fullName);
              if(!$fullDs)
                  $fullDs=new \lib\reflection\datasources\DatasourceDefinition($fullName,$this->parentModel);

              if($fullDs->mustRebuild())
                  $fullDs->$callbackName($this,"MxNlist","LEFT",$isadmin,$permissions,$this->multipleModelName);

              $this->datasources[]=$fullDs;

              $notName="Not".ucfirst($this->name);
              $notDs=$this->parentModel->getDataSource($notName);
              if(!$notDs)              
                  $notDs=new \lib\reflection\datasources\DatasourceDefinition($notName,$this->parentModel);

              if($notDs->mustRebuild())
                  $notDs->$callbackName($this,"MxNlist","OUTER",$isadmin,$permissions,$this->multipleModelName);
              
             $this->datasources[]=$notDs;
             return $this->datasources;
  }
  function generateActions($isAdmin)
        {
            // Las acciones a generar se van a aniadir a cada uno de los objetos relacionados por este objeto MULTIPLE_RELATION.
            // Para ello, a cada objeto hay que aniadir una action que tiene como campos indices su propio index,
            // y como campo a establecer, el alias de ese objeto (inverse relationship), que apunta al multiplemodel.

            if($isAdmin)
            {
                $prefixL="admin";
                $prefixU="Admin";
            }
            // Estos son los campos 
            $localFields = $this->parentModel->getIndexFields();
            // Ahora, hay que obtener los aliases del parentModel
            $aliases = $this->parentModel->getAliases();
            foreach($aliases as $key=>$value)
            {
                $def=$value->getDefinition();
                if(isset($def["OBJECT"]))
                {
                    if($this->multipleModel->objectName->equals($def["OBJECT"]))
                    {
                        // Todo : hacer mas comprobaciones de campos.
                        $relationName=$key;
                        $remoteFields=array($key=>$value);
                        break;
                    }
                }
              
            }

            $actions=array("Add","Delete","Set");
            $newActions=array();            
            foreach($actions as $key)
            {
              $action=new \lib\reflection\actions\ActionDefinition($prefixU.$key.$relationName,$this->parentModel);
              if($action->mustRebuild())          
              {
                  $newActions[]=$action->create($key."Relation", 
                                     $localFields, array(),
                                     $remoteFields,  $remoteModel,$isAdmin,$relationName);
              }
            }
            return $newActions;
        }
  
}

?>

<?php
namespace lib\model;
/*
        Sus indexFields deben ser relaciones a las claves del objeto padre
*/
/**
 *  
 *      HAY QUE HACER QUE EN LOS TYPED, SE GENERE UN ALIAS QUE APUNTE AL TIPO BASE. 
 * 
 * 
 */
class MultipleModel extends BaseModel
{
    protected $relatedModel=null;
    protected $relatedModelName=null;
    protected $allowRelay=true;
        function __setRelatedModelName($name)
        {
            $this->relatedModelName=$name;
        }
        function __getRelatedModel()
        {
            if(!$this->relatedModel)
            {
                $remModel=parent::__getAlias($this->relatedModelName);
                if($remModel->count()==0)                
                    $remModel[0]=BaseModel::getModelInstance($this->relatedModelName);
                $this->relatedModel=$remModel[0];                
                $this->relatedModel->__allowRelay(false);
            }
            return $this->relatedModel;            
        }
        
        function __getField($fieldName)
        {
            
            if(isset($this->__fieldDef[$fieldName]) || $this->__relayAllowed==false)
            {
                return parent::__getField($fieldName);
            }
            $related=$this->__getRelatedModel();
            return $related->__getField($fieldName);
        }

        function __get($varName)
        {
            try
            {                
                return BaseModel::__get($varName);
            }catch(\Exception $e)
            {                   
                if($this->__relayAllowed==false)
                    throw $e;
                $related=$this->__getRelatedModel();
                return $related->__get($varName);
            }
        }

        function __set($varName,$varValue)
        {

            try
            {
                BaseModel::__set($varName,$varValue);
            }catch(\lib\model\BaseTypeException $e)
            {
                if($e->getCode()==\lib\model\BaseTypedException::ERR_NOT_A_FIELD)
                {
                    if($this->__relayAllowed==false)
                        throw $e;
                    $related=$this->__getRelatedModel();
                    return $related->{$varName}=$varValue;
                }
                else
                    throw $e;
            }
        }     
        function & __getFieldDefinition($fieldName)
        {
            try
            {
                parent::__getFieldDefinition($fieldName);
            }
            catch(BaseModelException $e)
            {
                if($this->__relayAllowed==false)
                    throw $e;
                $related=$this->__getRelatedModel();
                return $related->__getFieldDefinition($fieldName);
            }
       }
       function copy(& $remoteModel)
       {
           parent::copy($remoteModel);
           if($this->__relayAllowed==false)
               return;
           $related=$this->__getRelatedModel();
           $related->copy($remoteModel);
       }
        
     function __call($name,$arguments)
     {         
        try
        {
            return BaseModel::__call($name,$arguments);
        }
        catch(\Exception $e)
        {            
            if($this->__relayAllowed==false)
                throw $e;
            $related=$this->__getRelatedModel();
            return call_user_func_array(array($related,$name),$arguments);
            
        }        
    }

     function getStateField()
     {
         // La prioridad la tiene el objeto derivado.
         if($this->__relayAllowed)
         {
             $related=$this->__getRelatedModel();
             $state=$related->getStateField();
             if(isset($state))
                 return $state;
         }
         
         if(isset($this->__objectDef["STATES"]["FIELD"]))
             return $this->__objectDef["STATES"]["FIELD"];
         return null;         
     }

     function getStates()
     {
         if($this->__relayAllowed==false)
             return parent::getStates();
         try
         {
             $related=$this->__getRelatedModel();
             return $related->getStates();
         }
         catch(BaseModelException $e)
         {             
             if(isset($this->__objectDef["STATES"]))
                 return $this->__objectDef["STATES"];
             throw new BaseModelException(BaseModelException::ERR_NO_STATUS_FIELD);
         }
     }

     function getStateId($stateName)
     {
         if($this->__relayAllowed==false)
             return parent::getStateId($stateName);
         try
         {
             $related=$this->__getRelatedModel();
             return $related->getStateId();
         }
         catch(BaseModelException $e)
         {
             if(!isset($this->__objectDef["STATES"]))
                 throw new BaseModelException(BaseModelException::ERR_NO_STATUS_FIELD);

             return $this->__getField($this->__objectDef["STATES"]["FIELD"])->getValue();         
         }
     }

     function getStateLabel($stateId)
     {
         if($this->__relayAllowed==false)
             return parent::getStateLabel($stateId);
         try{
             $related=$this->__getRelatedModel();
             return $related->getStateLabel();
         }
         catch(BaseModelException $e)
         {
             if(!isset($this->__objectDef["STATES"]))
                 throw new BaseModelException(BaseModelException::ERR_NO_STATUS_FIELD);

             return $this->__getField($this->__objectDef["STATES"]["FIELD"])->getType()->getLabel();         
         }
     }

     function getStateTransitions($stateId, $permstr)
     {
         if($this->__relayAllowed==false)
             return parent::getStateTransitions($stateId,$permstr);
         try
         {
             $related=$this->__getRelatedModel();
             $trans=$related->getStateTransitions($stateId, $permstr);
         }
         catch(BaseModelException $e)
         {
             return parent::getStateTransitions($stateId,$permstr);
         }
     }

     function getState()
     {
         if($this->__relayAllowed==false)
             return parent::getState();
         try
         {
            $related=$this->__getRelatedModel();
             return $related->getState();
         }
         catch(BaseModelException $e)
         {
             return parent::getState();
         }         
     }

     function onChangeState($next)
     {
         if($this->__relayAllowed==false)
             return parent::onChangeState($next);

         $related=$this->__getRelatedModel();
         if($related->getStateField()!==null)
             return $related->onChangeState($next);
         else
             return parent::onChangeState($next);         
     }

     function getOwner()
     {
         if (isset($this->__objectDef["OWNERSHIP"]))
             return $this->{$this->__objectDef["OWNERSHIP"]};
         if(!$this->__relayAllowed)
             return null;

         $related=$this->__getRelatedModel();
         return $related->getOwner();
     }

     function is_equal_to(& $model)
     {
         if (!$this->isLoaded() || !$model->isLoaded())
         {
             return false;
         }
         if ($this->__objName != $model->__objName)
             return false;

         foreach ($this->__fieldDef as $key => $value)
         {
             $curField = $this->__getField($key);
             $remField = $model->__getField($key);
             if ($curField->equals($remField)
                     && !in_array($key, (array) $this->__objectDef["INDEXFIELDS"]))
             {
                 // Solo se permite que sea distinta la primary key.
                 return false;
             }
         }
         if($this->__relayAllowed)
         {
             $related=$this->__getRelatedModel();
             return $related->is_equal_to($model);
         }
         return true;
     }
}

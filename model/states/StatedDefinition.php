<?php
namespace lib\model\states;

use lib\model\types\BaseTypeException;

class StatedDefinition
{
        var $definition;
        var $hasState;
        var $stateField;
        var $model;
        var $onlyDefault;
        var $stateFieldObj=null;
        var $stateType;
        var $oldState=null;
        var $newState=null;
        var $changingState=false;
        function __construct(& $model)
        {
            $this->model=$model;
            $this->definition= $model->getDefinition();
            $this->enable();
        }
        function setOldState($state)
        {
            //$this->oldState=$this->getStateLabel($state);
            $this->oldState = $state;
            $this->oldStateLabel = $this->getStateLabel($state);
        }
        function setNewState($state)
        {
            $this->newState=$state;
        }

        function getNewState()
        {
            if($this->newState)
                return $this->newState;
            return $this->stateType->getValue();
        }
        function getOldState()
        {
             if($this->oldState)
                 return $this->oldState;
            return $this->stateType->getValue();
        }

        function reset()
        {
            $this->oldState=null;
            $this->newState=null;
        }

        function disable()
        {
            $this->hasState=false;
        }
        function enable()
        {
            $this->hasState=isset($this->definition["STATES"])?true:false;
            if($this->hasState)
            {
                $this->stateField=$this->definition["STATES"]["FIELD"];
                $this->stateFieldObj=$this->model->__getField($this->stateField);
                $this->stateType=$this->stateFieldObj->getType();
            }
        }
        function getCurrentState()
        {
                if(!$this->hasState)
                    return null;

            if($this->stateType->hasOwnValue())
                return $this->stateType->get();
            return $this->getDefaultState();
        }
        function getStateField()
        {
            if($this->hasState)
                return $this->definition["STATES"]["FIELD"];
            return null;
        }
        function hasStates()
        {
            return $this->hasState;
        }
        function getStates()
        {
            if($this->hasState)
                return $this->definition["STATES"]["STATES"];
            return null;
        }
        function getDefaultState()
        {
            if(!$this->hasState)
                return null;
            if($this->stateType->getDefaultState()!==null)
                return $this->stateType->getDefaultState();

            if($this->definition["STATES"]["DEFAULT_STATE"])
                {
                    $position=array_search($this->definition["STATES"]["DEFAULT_STATE"],
                                           array_keys($this->definition["STATES"]["STATES"])
                                           );
                    if($position!==false)
                    return $position;
                }
            return 0;
        }
        function getStateFieldObj()
        {
            return $this->stateFieldObj;
        }
        function getStateId($name)
        {
            return $this->stateType->getValueFromLabel($name);
        }
        function getStateLabel($id)
        {
            if(!is_numeric($id))
                return $id;
            $labels= $this->stateType->getLabels();
            return $labels[$id];
        }
        function getCurrentStateLabel()
        {
            return $this->getStateLabel($this->getCurrentState());
        }
        function checkState()
        {
            if(!$this->hasState)
                return true;
            if($this->newState==null)
                return true;

            if(!isset($this->definition["STATES"]["STATES"][$this->newState]) ||
                !isset($this->definition["STATES"]["STATES"][$this->newState]["FIELDS"]) ||
                !isset($this->definition["STATES"]["STATES"][$this->newState]["FIELDS"]["REQUIRED"]))
                return true;
            $st=& $this->definition["STATES"]["STATES"][$this->newState]["FIELDS"]["REQUIRED"];
            foreach($st as $cF)
            {
                $field=$this->model->__getField($cF);
                if(!$field->is_set())
                {
                    throw new \lib\model\BaseTypedException(\lib\model\BaseTypedException::ERR_REQUIRED_FIELD,array("field"=>$cF));
                }
            }

        }
        function isRequired($fieldName)
        {
            if($this->hasState==false)
                return $this->model->__getField($fieldName)->isDefinedAsRequired();

            return $this->isRequiredForState($fieldName,$this->getNewState());
        }
        function isEditable($fieldName)
        {
            if($this->hasState==false)
                return true;
            if($fieldName==$this->stateField)
                return true;

            return $this->isEditableInState($fieldName,$this->getOldState());
        }
        function isFixed($fieldName)
        {
            if($this->hasState==false)
                return false;
            return $this->isFixedInState($fieldName,$this->getNewState());
        }
        function isRequiredForState($fieldName,$stateName)
        {
            if(!$this->hasState)
                return $this->model->__getField($fieldName)->isRequired();

            if($this->existsFieldInStateDefinition($stateName,$fieldName,"REQUIRED"))
                return true;
            return $this->model->__getField($fieldName)->isDefinedAsRequired();

        }
        function isEditableInState($fieldName,$stateName)
        {
            if(!$this->hasState)
                return true;
            if($fieldName==$this->stateField)
                return true;
            return $this->existsFieldInStateDefinition($stateName,$fieldName,"EDITABLE",true);
        }
        function isFixedInState($fieldName,$stateName)
        {
            if(!$this->hasState)
                return true;
            return $this->existsFieldInStateDefinition($stateName,$fieldName,"FIXED");
        }

        // Dependiendo de si el $group existe o no, querriamos que la funcion devolviera una cosa u otra.
        // Por ejemplo, si preguntamos si un cierto campo es REQUIRED dentro de un estado, y ese estado no define
        // REQUIRED, queremos que devuelva false.
        // Pero si en vez de REQUIRED preguntamos por EDITABLE, queremos de devuelva true.
        function existsFieldInStateDefinition($stateName,$fieldName,$group,$defResult=false)
        {
            $st=& $this->definition["STATES"]["STATES"][$stateName];
            if(!isset($st["FIELDS"]))
                return $defResult;
            if(!isset($st["FIELDS"][$group]))
                return false;
            return in_array($fieldName,$st["FIELDS"][$group]) || in_array("*",$st["FIELDS"][$group]);
        }

    function isChangingState()
    {
        return $this->changingState;
    }
    function changeState($next)
    {
        $this->changingState=true;
        if($next==$this->newState)
            return;
        // por ahora, hacemos esto...
        if($this->newState)
            throw new \lib\model\BaseTypedException(\lib\model\BaseTypedException::ERR_REJECTED_CHANGE_STATE,array("current"=>$this->getCurrentState(),"new"=>$next,"middle"=>$this->newState));

        $this->setOldState($this->getOldState());
        $states = $this->getStates();
        $result = true;
        $actualState = $this->oldState;
        if($this->oldState===$next && $this->oldState!==null) {
            $this->changingState=false;
            return true;
        }
        $this->setNewState($next);

        $oldId=$this->oldState;
        $newId=$this->newState;
        if(!isset($this->definition["STATES"]["STATES"][$newId]))
        {
            $this->model->__getField($this->stateField)->set($newId);
            $this->changingState=false;
            return;
        }
        $definition=$this->definition["STATES"]["STATES"][$newId];
        // Se ve si el estado actual es final o no.
        if(isset($this->definition["STATES"]["STATES"][$oldId]["IS_FINAL"]))
        {
            $this->changingState=false;
            throw new \lib\model\BaseTypedException(\lib\model\BaseTypedException::ERR_CANT_CHANGE_FINAL_STATE,array("current"=>$actualState,"new"=>$next));
        }
        if(isset($definition["ALLOW_FROM"]))
        {
            if(array_search($oldId,$definition["ALLOW_FROM"])===false)
            {
                if(isset($definition["REJECT_TO"][$newId]))
                {
                    $this->executeCallbacks("REJECT_TO",$newId,$oldId);
                    $this->changingState=false;
                    throw new \lib\model\BaseTypedException(\lib\model\BaseTypedException::ERR_REJECTED_CHANGE_STATE,array("current"=>$actualState,"new"=>$next));
                }
                else
                {
                    $this->changingState=false;
                    throw new \lib\model\BaseTypedException(\lib\model\BaseTypedException::ERR_CANT_CHANGE_STATE_TO,array("current"=>$actualState,"new"=>$next));
                }

            }
        }
        try
        {
            $result=$this->executeCallbacks("TESTS",$newId,$oldId);
        }catch(\Exception $e)
        {
            $this->changingState=false;
            throw $e;
        }
        if(!$result)
        {
            throw new \lib\model\BaseTypedException(\lib\model\BaseTypedException::ERR_CANT_CHANGE_STATE,array("current"=>$actualState,"new"=>$next));
        }
        $this->executeCallbacks("ON_LEAVE",$oldId,$newId);
        $this->executeCallbacks("ON_ENTER",$newId,$oldId);
        $this->model->__getField($this->stateField)->set($newId);
        $this->changingState=false;
    }

    function executeCallbacks($type,$state,$refState)
    {
        $result=true;
        if(!isset($this->definition["STATES"]['STATES'][$state]["LISTENERS"][$type]))
            return true;
        $def=$this->definition["STATES"]['STATES'][$state]["LISTENERS"][$type];
        $callbacks=$this->getStatedDefinition($def,$refState);
        foreach ($callbacks as $callback)
        {
            if($type=="TESTS" and $result==false)
                return $result;

            if(!is_array($callback))
            {
                if(isset($this->definition["STATES"]["LISTENER_TAGS"]))
                {
                    $callback=$this->definition["STATES"]["LISTENER_TAGS"][$callback];
                    if(!is_array($callback))
                    {
                        $callback=array("METHOD"=>$callback,"OBJECT"=>"SELF");
                    }
                }
                else
                {
                    throw new \lib\model\BaseTypedException(\lib\model\BaseTypedException::ERR_INVALID_STATE_CALLBACK,array("listenerTag"=>$callback));
                }
            }


            $obj = $callback['OBJECT'];
            $method = $callback['METHOD'];

            if(!$obj || $obj=="SELF")
                $obj=$this->model;
            // Atencion aqui! No se comprueba que el metodo exista, ya que si $obj es un ExtendedModel B, que extiende A , aunque B no posea un cierto metodo, puede
            // ser que exista en A, y eso se va a resolver via __call, por lo que method_exists devolveria false.

                if(is_object($obj) || $obj == "SELF")
                {
                    $param=isset($callback["PARAMS"])?$callback["PARAMS"]:array();
                    if(!is_array($param))
                    {
                        $param=array($param);
                    }

                    //echo "Ejecutando metodo $method<br>";

                    $tempResult = call_user_func_array(array($this->model,$method),$param);
                    $result = $result && $tempResult;
                }
                else
                {
                    $instance = new $obj();

                    $result = $result && $instance->$method($type, $this->model, $this->getOldState(), $this->getNewState(),$callback);
                }
        }
        return $result;
    }

    function getStateTransitions($stateId)
    {
        if (!$this->hasState)
            return null;

        if(isset( $this->definition["STATES"]["STATES"][$this->getStateLabel($stateId)]))
            return $this->definition["STATES"]["STATES"][$this->getStateLabel($stateId)];
        return null;
    }

    // Metodo para comprobar si el objeto puede pasar del estado A al B.
    function canTranslateTo($newStateId)
    {
        $currentState=$this->getCurrentState();
        $transitions=array_keys($this->getStateTransitions($currentState,'_PUBLIC_'));
        if($transitions===null)
            return true;
        return in_array($newStateId,$transitions);
    }

    function getStatedDefinition($statedDef,$stateToCheck)
    {
        if(isset($statedDef["STATES"]))
        {
            if(isset($statedDef["STATES"][$stateToCheck]))
                return $statedDef["STATES"][$stateToCheck];
            if(isset($statedDef["STATES"]["*"]))
                return $statedDef["STATES"]["*"];
            throw new \lib\model\BaseTypedException(\lib\model\BaseTypedException::ERR_NO_STATE_DEFINITION,array("state"=>$stateToCheck));
        }
        return $statedDef;
    }

}

?>

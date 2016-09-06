<?php
namespace lib\action;
class ActionResult
{
    var $fieldErrors=array();
    var $inputErrors=array();
    var $permissionError=false;
    var $isOk=true;
    var $globalErrors=array();
    var $model=null;
    function reset()
    {
        $this->isOk=true;
        $this->fieldErrors=array();
        $this->inputErrors=array();
        $this->globalErrors=array();
        $this->permissionError=false;
    }
    function setCorrupted()
    {
        $this->isOk=false;
    }
    function addFieldInputError($field,$input,$value,$exception)
    {
        $this->addFieldTypeError($field,$value,$exception);
        /*$this->isOk=false;
        $code=$exception->getCode();
        $this->inputErrors[$field][$exception->getCodeString()][$code]=array("input"=>$input,"value"=>$value,"code"=>$code);*/
    }
    function addFieldTypeError($field,$value,$exception)
    {
        
        $this->isOk=false;
        $code=$exception->getCode();        
        $this->fieldErrors[$field][$exception->getCodeString()][$code]=array("value"=>$value,"code"=>$code);
    }
    function addGlobalError($exception)
    {
        $this->isOk=false;
        $code=$exception->getCode();
        $this->globalErrors[$exception->getCodeString()]=array("code"=>$code,"params"=>$exception->getParams());
    }
    function isOk()
    {
        return $this->isOk;
    }
    function addPermissionError()
    {
        $this->permissionError=true;
        $this->isOk=false;
    }
    function serialize()
    {
        return array($this->permissionError,$this->fieldErrors,$this->inputErrors,$this->globalErrors,$this->isOk);
    }
    function unserialize($data)
    {
        list($this->permissionError,$this->fieldErrors,$this->inputErrors,$this->globalErrors,$this->isOk)=$data;
    }
    function getFieldErrors($fieldName=null)
    {
        if($fieldName!=null)
            return $this->fieldErrors[$fieldName];
        return $this->fieldErrors;
    }
    function getGlobalErrors()
    {
        return $this->globalErrors;
    }
    function setModel($model)
    {
        $this->model=$model;
    }
    function getModel()
    {
        return $this->model;
    }

}
?>

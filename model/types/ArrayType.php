<?php
  namespace lib\model\types;
  class ArrayTypeException extends \lib\model\types\BaseTypeException
  {
      const ERR_ERROR_AT=1;
      const ERR_INVALID_OFFSET=2;

      const TXT_ERROR_AT="Se encontro un error en la posicion %index%";
      const TXT_INVALID_OFFSET="Offset no valido";
  }
  class ArrayType extends BaseType implements \ArrayAccess
  {
      var $subTypeDef;
          
      function __construct($def,$neutralValue=null)
      {
          $this->subTypeDef=$def["elements"];
          parent::__construct($def,$neutralValue);          
      }
      function getSubTypeDef()
      {
          return $this->subTypeDef;
      }
      function setValue($val)
      {
          if($val===null || !isset($val))
          {
              $this->valueSet=false;
              $this->value=null;
          }
          $this->valueSet=true;
          $this->value=$val;      
      }            
      function validate($value)
      {     
        if(!is_array($value))
                $value=array($value);                            
         $remoteType=TypeFactory::getType(null,$this->subTypeDef,null);
         for($k=0;$k<count($value);$k++)
         {
             try
             {
                $remoteType->validate($value[$k]);
             }
             catch(\Exception $e)
             {
                 throw new ArrayTypeException(ArrayTypeException::ERR_ERROR_AT,array("index"=>$k,"exception"=>$e));
             }
         }           
         return true;                                            
      }                      
      function getValue()
      {         
          if($this->valueSet)
            return $this->value; 
          if(isset($this->definition["default"]))
            return explode(",",$this->definition["default"]);
          return null;          
      }
      function count()
      {
          if($this->valueSet)
              return count($this->value);
          return false;
      }
      function __toString()
      {
         return implode(",",$this->value);              
      }

      function offsetExists($index)
      {
          if(!$this->valueSet)
              return false;
          return isset($this->value[$index]);        
      }

      function offsetGet($index)
      {
          if(!$this->valueSet || $index > count($this->value))
              throw new ArrayTypeException(ArrayTypeException::ERR_INVALID_OFFSET,array("index"=>$index));
          return $this->value[$index];        
      }
      function offsetSet($index,$newVal)
      {
      }
      function offsetUnset($index)
      {

      }
      function getApplicableErrors()
      {
          $errors=parent::getApplicableErrors();
          $errors[get_class($this)."Exception"][ArrayTypeException::ERR_ERROR_AT]=ArrayTypeException::TXT_ERROR_AT;
          $subType=TypeFactory::getType(null,$this->subTypeDef,null);
          $errorsSubType=$subType->getApplicableErrors();
          return array_merge($errors,$errorsSubType);
      }

  }

  class ArrayTypeMeta
  {
      function getMeta($type)
      {
          $def=$type->getDefinition();
          $subType=$def["ELEMENTS"];
          $def["ELEMENTS"]=\lib\model\types\TypeFactory::getTypeMeta($subType);
          return $def;
      }
  }

  class ArrayTypeHTMLSerializer
  {
      function serialize($type)
      {
          if($type->hasValue())return $type->getValue();
		  return "";
      }
      function unserialize($type,$value)
      {
          $type->validate($value);
          $type->setValue($value);
      }
  }


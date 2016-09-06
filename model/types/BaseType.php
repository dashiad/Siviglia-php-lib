<?php namespace lib\model\types;

  use lib\model\BaseException;
use lib\model\BaseTypedException;

class BaseTypeException extends \lib\model\BaseException{
      
      const ERR_UNSET=1;
      const ERR_INVALID=2;
      const ERR_TYPE_NOT_FOUND=3;
      const ERR_INCOMPLETE_TYPE=4;
      const ERR_SERIALIZER_NOT_FOUND=7;
      const ERR_TYPE_NOT_EDITABLE=8;
      const ERR_TYPE_IS_REQUIRED=9;
      const ERR_NOT_A_FIELD=10;

      const TXT_UNSET="El campo no puede estar vacío";
      const TXT_INVALID="El valor no es valido para este campo";
      const TXT_TYPE_NOT_FOUND="Tipo no encontrado";
      const TXT_INCOMPLETE_TYPE="Tipo no completo";
      const TXT_SERIALIZER_NOT_FOUND="Serializador no encontrado";
      const TXT_TYPE_NOT_EDITABLE="Tipo no editable";
      const TXT_TYPE_IS_REQUIRED="Requerido";
      const TXT_NOT_A_FIELD="No es un campo";
      const REQ_UNSET="REQUIRED";

      var $params;
      public function __construct($code,$params=null)
      {           
          $this->params=$params;       
          parent::__construct($code,$params);
      }


}

  abstract class BaseType
  {      
      var $valueSet=false;
      var $value;
      var $definition;
      var $flags=0;


      const TYPE_SET_ON_SAVE=0x1;
      const TYPE_SET_ON_ACCESS=0x2;
      const TYPE_IS_FILE=0x4;
      const TYPE_REQUIRES_SAVE=0x8;
      const TYPE_NOT_EDITABLE=0x10;
      const TYPE_NOT_MODIFIED_ON_NULL=0x20;
      const TYPE_REQUIRES_UPDATE_ON_NEW=0x40;

      function __construct($def,$neutralValue=null)
      {
          $this->definition=$def;
          if($neutralValue!==null)          
            $this->setValue($neutralValue);                    
      }      

      function setFlags($flags)
      {
          $this->flags|=$flags;
      }
      function getFlags()
      {
          return $this->flags;
      }

      function setValue($val)
      {
          if($this->flags & BaseType::TYPE_NOT_EDITABLE)
              throw new BaseTypeException(BaseTypeException::ERR_TYPE_NOT_EDITABLE);
          //if($this->validate($val))
          //{
            if($val===null || !isset($val))
            {
              $this->valueSet=false;
              $this->value=null;
            }
            else
            {
              $this->valueSet=true;
              $this->value=$val;      
            }
          //}
      }
      
      function validate($value)
      {
          if($value===null)
          {
              if($this->isRequired() && !$this->hasDefaultValue())
                    throw new BaseTypeException(BaseTypeException::ERR_TYPE_IS_REQUIRED);
          }
          return true;
      }

      function postValidate($value)
      {
          return true;
      }
      function hasValue()
      {
          return $this->valueSet || $this->hasDefaultValue() || ($this->flags & BaseType::TYPE_SET_ON_SAVE) || ($this->flags & BaseType::TYPE_SET_ON_ACCESS);
      }
      function hasOwnValue()
      {
          return $this->valueSet;
      }
      function copy($type)
      {
          if($type->hasValue())
          {
              $this->valueSet=true;
              $this->setValue($type->getValue());
          }
          else
          {
              $this->valueSet=false;
              $this->value=null;
          }
      }
      function equals($value)
      {          
          if($this->value===null)
              return false;
          return $this->value==$value;
      }
      // Warning : sin validacion.Acepta cualquier cosa.Usado por los formularios cuando un campo es erroneo.
      function __rawSet($value)
      {
          $this->value=$value;
          $this->valueSet=true;
      }
      function set($value)
      {
          if(is_object($value) && get_class($value)==get_class($this))
              return $this->copy($value);
          return $this->setValue($value);
      }
      function is_set()
      {
          if($this->valueSet)return true;

          if(!($this->flags & BaseType::TYPE_SET_ON_SAVE) &&
             !($this->flags & BaseType::TYPE_SET_ON_ACCESS))
              return false;
          return true;
      }
      function isRequired()
      {
          return isset($this->definition["required"]) && $this->definition["required"]==true;
      }
      function clear()
      {
          $this->valueSet=true;
          $this->value=null;
      }
      function isEditable()
      {
          return !($this->flags & BaseType::TYPE_NOT_EDITABLE);
      }
      function get()
      {
          return $this->getValue();
      }
      function getValue()
      {
          if($this->valueSet)
            return $this->value; 
          if($this->hasDefaultValue())
            return $this->getDefaultValue();
          return null;          
      }
      function __toString()
      {
          if(!$this->valueSet)
          {
              return "";
          }
          return (string)$this->value;
      }
      function hasDefaultValue()
      {
          return isset($this->definition["default"]) && $this->definition["default"]!="NULL" && $this->definition["default"]!==null && $this->definition["default"]!=="";
      }
      function getDefaultValue()
      {
          if(!isset($this->definition["default"]))
              return false;
          $def=strtolower($this->definition["default"]);
          if($def==="null")
              return false;
          return $this->definition["default"];
      }
      function setDefaultValue($val)
      {
          $this->definition["default"]=$val;
      }
      function getRelationshipType()
      {
          return $this;
      }
      function getDefinition()
      {
          if(!isset($this->definition["type"]))
          {
              $parts=explode("\\",get_class($this));
              $this->definition["type"]=preg_replace("/Type$/","",$parts[count($parts)-1]);
          }
          return $this->definition;
      }
      function isEmpty()
      {
          return $this->valueSet==false || $this->value==="" || $this->value===null;
      }
      function getApplicableErrors()
      {
          $errors=array();
          $cl=get_class($this);
          $typeList=array_flip(array_merge(array($cl),array_values(class_parents($this))));
          $setErrors=array();
          foreach($typeList as $key=>$value)
          {
              $parts=explode("\\",$value);
              $className=$parts[count($parts)-1];
              $exceptionClass=$value."Exception";
              if( !class_exists($exceptionClass) )
                  continue;

               return $exceptionClass::getPrintableErrors($this,$this->definition);
          }
          return array();
      }
  }

  class BaseTypeMeta
  {
      function getMeta($type)
      {
          return $type->getDefinition();
      }
  }


  class BaseTypeHTMLSerializer
  {
      function serialize($type)
      {
          if($type->hasValue())return $type->getValue();
		  return "";
      }
      function unserialize($type,$value)
      {
          if($value!==null)
          {
            $type->validate($value);
            $type->setValue($value);
          }
      }
  }

  interface ISaveableType
  {
       function onSave($model);
       function onSaved($model);
  }
?>

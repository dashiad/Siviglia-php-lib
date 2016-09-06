<?php
namespace lib\model\types;


class CompositeType extends BaseType
{
      var $__types;
      var $__definition;
      var $__subTypeDef;
      var $__fields;
      var $__dirty=false;
      function __construct($definition,$subTypeDef,$values=array())
      {
         $this->__subTypeDef=$subTypeDef;
         $this->__fields=& $this->__subTypeDef["FIELDS"];
         foreach($this->__fields as $key=>$value)
             $this->__types[$key]=\lib\model\types\TypeFactory::getType(null,$value,isset($values[$key])?$values[$key]:false);  
         $definition["FIELDS"]=$subTypeDef["FIELDS"];
         BaseType::__construct($definition,false);       
      }
      function getFields()
      {
          return array_keys($this->__fields);
      }      
         
      function __set($fieldName,$value)
      {
          if(!$this->__types[$fieldName])
              throw new BaseTypeException(BaseTypeException::ERR_NOT_A_FIELD,array("field"=>$fieldName));
          if($this->__types[$fieldName]->equals($value))
              return;
          $this->__dirty=true;
          $this->__types[$fieldName]->setValue($value);          
      }
      function __get($fieldName)
      {
          return $this->__types[$fieldName]->get();
      }
      function get()
      {
          return $this;
      }
      
      function setValue($arr)
      {
          if(!$arr)
              return;
          
          $this->__dirty=true;
          foreach($this->__types as $key=>$value)
          {
              if(isset($arr[$key]))
              {        
                  $value->setValue($arr[$key]);
              }
              else
              {
                  if($this->definition[$key]["required"])
                      throw new BaseTypeException(BaseTypeException::ERR_INCOMPLETE_TYPE,array("req"=>$key));
              }
          }
          $this->valueSet=true;
      }
      function validate($arr)
      {
          foreach($this->__types as $key=>$value)
          {
              if(isset($arr[$key]))
                  $value->validate($arr[$key]);
              else
              {
                  if($this->definition[$key]["required"])
                      throw new BaseTypeException(BaseTypeException::ERR_INCOMPLETE_TYPE,array("req"=>$key));
              }
          }
      }
      function equals($values)
      {
      
          foreach($this->__types as $key=>$value)
          {
              if(!$value->equals($values[$key]))
              {
                  return false;
              }
          }          
          return true;
      }
      function hasValue()
      {
          foreach($this->__types as $key=>$value)
          {
              if(!$value->hasValue() AND $this->definition[$key]["REQUIRED"])
                  return false;
          }
          return true;

      }
      function is_set()
      {
          foreach($this->__types as $key=>$value)
          {
              if(!$value->is_set() AND $this->definition[$key]["REQUIRED"])
                  return false;
          }
          return true;
      }

      function isDirty()
      {
           foreach($this->__types as $key=>$value)
          {
              if(!$value->is_set() AND $this->definition[$key]["REQUIRED"])
                  return false;
          }
          return false;
      }   
      function clean()
      {
          $this->__dirty=false;
      }
      function getValue()
      {          
          foreach($this->__types as $key=>$value)
          {
              
              $fDef=$this->__fields[$key];
              if(!$value->hasValue())
              {
                  
                  if($fDef["REQUIRED"])
                  {
                        $flags=$value->flags;
                        if(!($flags & BaseType::TYPE_SET_ON_SAVE))
                        {
                            if($flags & BaseType::TYPE_SET_ON_ACCESS)
                            {
                                $results[$key]=$value->getValue();
                            }
                            else
                                BaseTypeException(BaseTypeException::INCOMPLETE_TYPE,array("field"=>$key));
                        }
                  }
              }
              else
              {                  
                  $results[$key]=$value->getValue();                                
              }
          }
          return $results;          
      }

      function getSubTypes()
      {
          return $this->__types;
      }    
      
}


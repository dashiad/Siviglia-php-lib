<?php namespace lib\model\types;
  class AutoIncrementType extends IntegerType
  {
      function __construct($def,$value=null)
      {
          IntegerType::__construct(array("TYPE"=>"AutoIncrement","MIN"=>0,"MAX"=>9999999999),$value);
          $this->setFlags(BaseType::TYPE_SET_ON_SAVE);
      }
            
      function validate($value)
      {
          return true;
      }  
      function setValue($val)
      {          
          IntegerType::setValue($val);
      }
      function getRelationshipType()
      {
          return new IntegerType(array("MIN"=>0,"MAX"=>9999999999));
      }    
  }


  class AutoIncrementTypeHTMLSerializer extends BaseTypeHTMLSerializer
  {
      
      function unserialize($type,$value)
      {
          if($value!==null && is_numeric($value)) {
              $inted=intval($value);
              $type->setValue($inted);
          }
      }
  }



?>

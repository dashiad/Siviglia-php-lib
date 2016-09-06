<?php namespace lib\model\types;
 use lib\model\types\BaseType;
  class IntegerTypeException extends BaseTypeException {
      const ERR_TOO_SMALL=100;
      const ERR_TOO_BIG=101;
      const ERR_NOT_A_NUMBER=102;

      const TXT_TOO_SMALL='Valor demasiado pequeño';
      const TXT_TOO_BIG='Valor demasiado grande';
      const TXT_NOT_A_NUMBER='Debes introducir un número';

      const REQ_TOO_SMALL='MIN';
      const REQ_TOO_BIG='MAX';
  }
  class IntegerType extends BaseType
  {
      function __construct($def,$value=null)
      {
          BaseType::__construct($def,$value);          
      }
      function validate($value)
      {
          if($value===null)
              return true;
          $value=trim($value);
          $res=BaseType::validate($value);
          if(!preg_match("/^(?:[0-9]+)+$/",$value))
              throw new IntegerTypeException(IntegerTypeException::ERR_NOT_A_NUMBER);

          if(isset($this->definition["MIN"]))
          {
              if($value < intval($this->definition["MIN"]))
                  throw new IntegerTypeException(IntegerTypeException::ERR_TOO_SMALL);
          }
          if(isset($this->definition["MAX"]))
          {
              if($value > intval($this->definition["MAX"]))
                throw new IntegerTypeException(IntegerTypeException::ERR_TOO_BIG);
          }
          return true;
      }
  }  

   class IntegerHTMLSerializer extends BaseTypeHTMLSerializer
   {
      function serialize($type)
      {
          // Aqui habria que meter escapeado si la definition lo indica.
          if($type->hasValue())
              return htmlentities($type->getValue(),ENT_NOQUOTES,"UTF-8");
          return "";
      }
      function unserialize($type,$value)
      {
          if($value!==null && is_numeric($value))
          {
            $inted=intval($value);
            $type->setValue($inted);
          }
      }
   }


?>

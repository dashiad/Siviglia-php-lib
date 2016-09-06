<?php namespace lib\model\types;  
  class StringTypeException extends BaseTypeException {
      const ERR_TOO_SHORT=100;
      const ERR_TOO_LONG=101;
      const ERR_INVALID_CHARACTERS=102;

      const TXT_TOO_SHORT="El campo debe tener al menos %min% caracteres";
      const TXT_TOO_LONG="El campo debe tener un máximo de %max% caracteres";
      const TXT_INVALID_CHARACTERS="Valor incorrecto";

      const REQ_TOO_SHORT="MINLENGTH";
      const REQ_TOO_LONG="MAXLENGTH";
      const REQ_INVALID_CHARACTERS="REGEXP";
  }
  class StringType extends BaseType
  {
      function __construct($def,$neutralValue=null)
      {
            BaseType::__construct($def,$neutralValue);
      }
         
      
      function validate($val)
      {     
          if($val===null || !isset($val) || $val==='' || $val==='%')
              return true;
        $res=BaseType::validate($val);     
        if($res!==true)
            return $res;
        
         $len=strlen($val);
         if(isset($this->definition["MINLENGTH"]))
         {            
            if($len < $this->definition["MINLENGTH"])
            {
                throw new StringTypeException(StringTypeException::ERR_TOO_SHORT,array("min"=>$this->definition["MINLENGTH"]));
            }
         }
		 
         if(isset($this->definition["MAXLENGTH"]))
         {	 
            if($len > $this->definition["MAXLENGTH"])
            {
                throw new StringTypeException(StringTypeException::ERR_TOO_LONG,array("max"=>$this->definition["MAXLENGTH"]));
            }
                
         }
         if(isset($this->definition["REGEXP"]))
         {
             if(!preg_match($this->definition["REGEXP"],$val))
             {
                throw new StringTypeException(StringTypeException::ERR_INVALID_CHARACTERS);
             }
         }                              
         return true;                                              
      }
      static function normalize($cad)
      {

          $cad=str_replace(array("á","é","í","ó","ú","Á","Ë","Í","Ó","Ú","Ñ"),array("a","e","i","o","u","a","e","i","o","u","ñ"),$cad);
          $cad=str_replace(array(".",",","-")," ",$cad);
          $cad=strtolower($cad);
          $cad=str_replace(array("#","_"),"",$cad);
          $cad=preg_replace("/  */"," ",$cad);
          return $cad;
      }
      static function correctEncoding($cad)
      {
          return \lib\php\Encoding::fixUTF8($cad);
      }
  }

  class StringTypeHTMLSerializer extends BaseTypeHTMLSerializer
   {
      function serialize($type)
      {
          // Aqui habria que meter escapeado si la definition lo indica.
          if($type->valueSet)
              return htmlentities($type->getValue(),ENT_NOQUOTES,"UTF-8");
          return "";
      }
      function unserialize($type,$value)
      {
          if($value!==null && $value!="NULL" && $value!="null")
          {
          // Habria que ver tambien si esta en UTF-8 
           if($type->definition["TRIM"])
               $value=trim($value);

            // Escapeado -- Anti-Xss?
            $type->setValue($value);

          }
      }
   }


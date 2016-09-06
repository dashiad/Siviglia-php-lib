<?php namespace lib\model\types;
include_once(LIBPATH."/model/types/String.php");
  class PasswordType extends StringType
  {
      function __construct($def=array(),$value=false)
      {
          if(!isset($def["TYPE"]))
            $def["TYPE"]="Password";
          if(!isset($def["MINLENGTH"]))
            $def["MINLENGTH"]=2;
          if(!isset($def["MAXLENGTH"]))
            $def["MAXLENGTH"]=32;
          if(!isset($def["REGEXP"]))
            $def["REGEXP"]='/^[A-Za-z\d_]{2,16}$/i';
          $def["TRIM"]=true;
          $this->unserialized=false;
          StringType::__construct($def,$value);
      }

    function setValue($val)
      {
          $this->unserialized=false;
          parent::setValue($val);

      }
      function setAsUnserialized()
      {
           $this->unserialized=true;
      }
      function validate($val)
      {
          if(!$this->unserialized)
          {
            return parent::validate($val);
          }
          return true;


      }
      function getEncrypted($v=null)
      {
          return md5($this->getSalt().($v?$v:$this->value));
      }
      function isEncrypted()
      {
          return $this->deserialized;
      }
      function setRandomValue()
      {
          $length=6;
          $str = 'abcdefghijkmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
          for ($i = 0, $passwd = ''; $i < $length; $i++)
                  $passwd .= substr($str, mt_rand(0, strlen($str) - 1), 1);
          $this->setValue($passwd);
          return $passwd;
      }
      function compare($cad)
      {
          if(is_object($cad) && get_class($cad)=='\lib\model\types\Password')
              return $cad->value==$this->value;
          return $cad==$this->getEncrypted($this->value);
      }
      function getSalt()
      {
          return isset($this->definition["SALT"])?$this->definition["SALT"]:'';
      }
  }

  class PasswordMeta extends \lib\model\types\BaseTypeMeta
  {
      function getMeta($type)
      {
          $def=$type->getDefinition();
          unset($def["SALT"]);
          return $def;
      }
  }


?>

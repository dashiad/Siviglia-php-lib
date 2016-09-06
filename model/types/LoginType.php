<?php namespace lib\model\types;
  class LoginType extends StringType
  {
      function __construct($def,$value = -1)
      {
          $def=array(
            "TYPE"=>"Login",
            "MINLENGTH"=>4,
            "MAXLENGTH"=>15,
            "REGEXP"=>'/^[a-z\d_]{3,15}$/i',
            "ALLOWHTML"=>false,
            "TRIM"=>true
          );          
          StringType::__construct($def,$value);
      }       
  }
?>

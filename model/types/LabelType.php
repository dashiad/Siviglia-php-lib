<?php namespace lib\model\types;
  class LabelType extends StringType
  {
      function __construct($def,$value=false)
      {
          StringType::__construct(array("TYPE"=>"Label","MAXLENGTH"=>50),$value);
      }                
  }
?>

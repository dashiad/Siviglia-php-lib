<?php namespace lib\model\types;
  class ColorType extends StringType
  {
      function __construct($def,$value=false)
      {
          StringType::__construct(array("TYPE"=>"Color","MAXLENGTH"=>10),$value);
      }
  }
?>

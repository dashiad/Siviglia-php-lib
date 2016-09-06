<?php namespace lib\model\types;
  class NameType extends StringType
  {
      function __construct($def,$value=false)
      {
          StringType::__construct(array("TYPE"=>"Name","MAXLENGTH"=>100),$value);
      }
      static function normalize($cad)
      {
          $cad=StringType::normalize($cad);
          $cad=str_replace(array("mª","Mª"),array("MARIA","maria"),$cad);
          return $cad;

      }
  }
?>

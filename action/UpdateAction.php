<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 15/06/14
 * Time: 12:15
 */

namespace lib\action;
include_once(LLIBPATH."/action/DataSourceAction.php");

class UpdateAction extends DataSourceAction{
   function process()
   {
       $replacements=array();
       if(isset($this->definition["PARAMS"]))
       {
           foreach($this->definition["PARAMS"] as $key=>$value)
           {
               if($this->{"*".$key}->hasValue())
                   $replacements["{%".$key."%}"]=array("k"=>$key,"v"=>$this->{$key});
           }
       }
       $this->source->getValue()
       // Se obtiene la lista de campos
       $fixedFields=array();
       $paramFields=array();
       foreach($this->definition["UPDATE"] as $key=>$value)
       {
           if(isset($replacements[$value]))
           {

           }
       }

   }
} 
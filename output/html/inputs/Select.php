<?php
namespace lib\output\html\inputs;
class Select extends DefaultInput
{
        function unserialize($val)
        {
             if(isset($this->inputDef))
             {
                 
                 if(isset($this->inputDef["PARAMS"]["NULL_RELATION"]))
                 {                     
                     if(in_array(intval($val),$this->inputDef["PARAMS"]["NULL_RELATION"]))
                     {
                         $this->value=$val;
                         // No se establece a isset.
                         return;
                     }
                 }
             }
                $this->isSet=true;
                $this->value=$val;
        }

}


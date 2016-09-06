<?php
namespace lib\output\html\inputs;
class Checkbox extends DefaultInput
{
        function unserialize($val)
        {
                $this->isSet=true;
                if($val!==null)
                        $this->value=1;
                else
                        $this->value=0;
        }
        

}


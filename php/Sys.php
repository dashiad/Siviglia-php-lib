<?php
namespace lib\php;
function dumpTrace($exception=null)
{
    $trace_text="";
    $trace=$exception?$exception->getTrace():debug_backtrace();
    // Las dos ultimas llamadas no me interesan, son del log.
    $j=0;
    foreach($trace as $i=>$call){
        if($j<2)
        {
            $j++;
            continue;
        }
        $j++;
        if(method_exists($call['object'],"__toString"))
            $call['object']=$call['object']->__toString();
        else
            $call['object'] = 'CONVERTED OBJECT OF CLASS '.get_class($call['object']);

        if (is_array($call['args'])) {
            foreach ($call['args'] AS &$arg) {
                if(method_exists($call['object'],"__toString"))
                    $call['object']=$call['object']->__toString();
                else
                    $call['object'] = 'CONVERTED OBJECT OF CLASS '.(is_object($call['object'])?get_class($call['object']):$call['object']);
            }
        }

        $trace_text[$i-2] = "#".($i-2)." ".$call['file'].'('.$call['line'].') ';
        $trace_text[$i-2].= (!empty($call['object'])?$call['object'].$call['type']:'');
        $trace_text[$i-2].= $call['function'].'('.implode(', ',$call['args']).')';
    }
    
    return implode("<br>",$trace_text);    
}
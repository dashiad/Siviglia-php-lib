<?php
namespace lib\reflection\plugins;

class ExtJsGenerator extends \lib\reflection\SystemPlugin
{

    function __construct()
    {
    }
       
    function SAVE_SYSTEM($level)
    {
        if($level!=1)return;
        printPhase("Generando codigo Dojo");
        $this->iterateOnModels('createOutput');
    }
    function createOutput($layer,$objName,$model)
    {
        $dojoClass=new \lib\reflection\js\dojo\DojoGenerator($model);
        $dojoClass->generate();
        
    }
}



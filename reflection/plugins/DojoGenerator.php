<?php
namespace lib\reflection\plugins;

class DojoGenerator extends \lib\reflection\SystemPlugin
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
        include_once(LIBPATH."/reflection/js/dojo/DojoGenerator.php");
        $dojoClass=new \lib\reflection\js\dojo\DojoGenerator($model);
        $dojoClass->save();
        
    }
}

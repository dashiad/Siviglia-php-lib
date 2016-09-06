<?php
namespace lib\reflection\plugins;

class InitialDataProcessor extends \lib\reflection\SystemPlugin {

    function SAVE_SYSTEM($level)
    {
        if($level!=1)return;
        printPhase("Guardando datos por defecto");
        $this->iterateOnModels("saveInitialData");
    }
    function saveInitialData($layer,$name,$object)
    {
        $object->saveInitialData();
    }
}

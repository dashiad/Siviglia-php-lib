<?php
namespace lib\templating\html\inputs;

class InputFactory
{    
    static function getInputController($inputName,$inputType,$fieldDef,$inputDef)
    {
        $targetFile=LIBPATH."/output/html/inputs/".ucfirst(strtolower($inputType)).".php";
        if(is_file($targetFile))
        {
            include_once($targetFile);
            $className='\lib\output\html\inputs\\'.ucfirst(strtolower($inputType));
            return new $className($inputName,$fieldDef,$inputDef);
        }
        include_once(LIBPATH."/output/html/inputs/DefaultInput.php");
        return new \lib\output\html\inputs\DefaultInput($inputName,$fieldDef,$inputDef);
    }
}

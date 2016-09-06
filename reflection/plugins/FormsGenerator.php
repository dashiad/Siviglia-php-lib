<?php
namespace lib\reflection\plugins;

class FormsGenerator extends \lib\reflection\SystemPlugin {
    // Se tienen que generar formularios para dos cosas:
    // 1 : Acciones del modelo
    // 2 : Filtros de datasources
    // 3 : Formularios custom

    function REBUILD_ACTIONS($level)
    {
       
        if( $level!=2 )return;
        printPhase("Generando Formularios sobre acciones");
        $this->iterateOnModels("buildForms");
    }
    function buildForms($layer,$objName,$modelDef)
    {
        printSubPhase("Generando formularios de ".$objName);
                
        $actions=$modelDef->getActions();        
        foreach($actions as $name=>$action)
        {                    
            $formInstance=new \lib\reflection\html\forms\FormDefinition($name,$action);
            if($formInstance->mustRebuild())
                $formInstance->create();
            else
                $formInstance->initialize();
             
             $formInstance->saveDefinition();
             $formInstance->generateCode();
        }
                    
            
     }

}

?>

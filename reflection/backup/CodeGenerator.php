<?php
namespace lib\reflection\plugins;

class CodeGenerator extends \lib\reflection\SystemPlugin {


    function SAVE_SYSTEM($sys,$level)
    {
        if( $level!=2 )
            return;

        printPhase("Generando Clases Modelo");
        // Comienza la generacion de controladores.Por cada una de las acciones, y de las vistas, hay que generar
        // codigo de 1) chequeo de estado, 2) chequeo de permisos.
        // Hay que cargar la clase controladora existente, compararla con el codigo generado, y hacer un merge con la clase existente.

        global $APP_NAMESPACES;
        foreach($APP_NAMESPACES as $key=>$curLayer)
        {
            printSubPhase("Generando modelos de ".$curLayer);
            $objs=$sys->objectDefinitions[$curLayer];
            foreach($objs as $objName=>$modelDef)
            {
                                
                printItem("Generando $objName");
                $modelClass=$sys->classes[$curLayer][$objName]["MODEL"];   
                $modelClass->generate();                                
            }

        }
    }
}

?>

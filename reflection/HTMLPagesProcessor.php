<?php 
namespace lib\reflection\plugins;

class HTMLPagesProcessor extends \lib\reflection\SystemPlugin {

    function SAVE_SYSTEM($sys,$level)
    {

        global $APP_NAMESPACES;
        if($level!=1)return;

        printPhase("Generando acciones");

        $layers=$APP_NAMESPACES;
        foreach($layers as $index=>$curLayer)
        {
            foreach($sys->objectDefinitions[$curLayer] as $key=>$value)
            {
                // Se generan paginas en los siguientes paths:
                // 1) Por cada objeto,tanto de app como web:
                //    Listar todos
                //    Ver todos
                //    Editar uno
                //    Vista de administracion
                //    Listado de administracion
                // 2) Si existe un campo "owner",
            }
        }
     }
?>

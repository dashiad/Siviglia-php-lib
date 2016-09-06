<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 22/07/15
 * Time: 18:54
 */

namespace lib\reflection;


/* Una clase es un ManagedSourceCode si existe una especificacion en reflection con metadata de como editarla, o
   generarla.Esa especificacion se devuelve como una cadena en la llamada a getSourceTemplate
*/
interface ManagedSourceCode {
    function getSourceTemplate();
}
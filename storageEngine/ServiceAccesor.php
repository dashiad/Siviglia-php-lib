<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 03/02/2016
 * Time: 16:35
 */

namespace lib\storageEngine;


class ServiceAccesor
{
    function getResource($name,$definition,$parameters=array())
    {
        if(!isset($definition))
        {
            throw new \Exception("Se pide un recurso no existente a la pagina web:$name");
        }
        $type=$definition["NAME"];
        $service=$this->environment->get($type);
        \lib\php\ParametrizableString::applyRecursive($definition,$parameters);
        return $service->query($definition["QUERY"],$definition["PARAMS"]);
    }
}
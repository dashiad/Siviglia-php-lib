<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 30/07/15
 * Time: 18:58
 */

namespace lib\user;

interface Base {
    function isLogged();
    function getEffectiveLanguage();
}

interface Anonymous extends Base{

}

interface Logged extends Base{
    function getId();
    function getType();
}
interface UserFactory {
    function getUser($request);
}

class SimpleFactory implements UserFactory {
    function getUser($request)
    {
        return new SimpleAnonymous();
    }
}
// Esta clase es la minima requerida para las necesidades minimas del sistema.
class SimpleAnonymous implements Anonymous{
    var $langIso=null;
    function isLogged(){return false;}
    function getEffectiveLanguage()
    {
        if($this->langIso)
            return $this->langIso;

        $project=\Registry::retrieve(\Registry::PROJECT);
        $langs=$project->getAllowedLanguages();
        if(count($langs)==1)
        {
                $this->langIso=$langs[0];
        }
        else
        {
            $project=\Registry::retrieve(\Registry::PROJECT);
            $langs=$project->getLanguages();
            $reqLang=\Registry::retrieve(\Registry::REQUEST_LANGUAGE);
            if(in_array($reqLang,$langs))
            {
                $this->langIso=$reqLang;
            }
            else
                $this->langIso=$project->getDefaultLanguage();
        }
        return $this->langIso;
    }
}
<?php namespace lib\model\types;
class StreetType extends StringType {
    function __construct(& $definition,$value=null)
    {
		$definition['MINLENGTH']=2;
		$definition['MAXLENGTH']=200;
        StringType::__construct($definition,$value);
    }
    static function normalize($cad)
    {
        $cad=String::normalize($cad);

        $cad=str_replace(array("Nº","nº"),array("",""),$cad);
        //$cad=$this->basicStringFilter($cad);
        $cad=str_replace(array("calle/"),"",$cad);
        $cad=str_replace(array(
                "avenida de","s/n","calle","plaza","pza","ps.","pse","pz","º","ª","c/","avda.","avda","avd","avenida"),"",$cad
        );
        if(substr($cad,0,2)=="c ")
            $cad=substr($cad,2);
        if(substr($cad,0,2)=="av ")
            $cad=substr($cad,3);
        $cad=str_replace(array(" local "," chalet "," piso "," urbanizacion "," urb "," bj "," bajo "," bloque "," portal "," bloq "," bl "," esc "," escalera "," n "," pta "," puerta "," numero ")," ",$cad);
        $cad=str_replace(array(" izquierda "," izqd "," izq ")," izq ",$cad);
        $cad=str_replace(array(" derecha "," dcha "," drch ")," dcha ",$cad);
        $prefixes=array("avnd ","carrer ","paseo ","ronda ","urb ","urbanizacion ","ctra ","carretera ","can ","rd ","cl ","av ","avinguda ","travesia ","trv ","rambla ","plz ","pl ");
        for($k=0;$k<count($prefixes);$k++)
        {
            if(strpos($cad,$prefixes[$k])===0)
                $cad=str_replace($prefixes[$k],"",$cad);
        }
        return $cad;

    }
}

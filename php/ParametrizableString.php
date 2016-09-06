<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 28/08/15
 * Time: 13:05
 */

namespace lib\php;
use lib\model\BaseException;
class ParametrizableStringException extends BaseException
{
    const ERR_MISSING_REQUIRED_PARAM=1;
    const ERR_MISSING_REQUIRED_VALUE=2;
    const TXT_MISSING_REQUIRED_PARAM="Missing parameter [%param%]";
    const TXT_MISSING_REQUIRED_VALUE="Missing value [%param%]";
}


class ParametrizableString
{
    const BASEREGEXP='/\[\%(?:(?:(?<simple>[^: ,%]*)\%\])|(?:(?<complex1>[^: ,]*)|(?<complex2>[^:]*)):(?<body>.*?(?=\%\]))\%\])/';
    const BODYREGEXP='/{\%(?:(?<simple>[^%:]*)|(?:(?<complex>[^:]*):(?<predicates>.*?(?=\%}))))\%}/';
    const PARAMREGEXP='/(?<func>[^|$ ]+)(?:\||$|(?: (?<params>[^|$]+)))/';
    const SUBPARAMREGEXP="/('[^']*')|([^ ]+)/";
    // Si en source tenemos una cadena del tipo:
    // "[%param: a={%param%}%] [%!param2: b=0%]"
    // Y en $params tenemos array("param"=>2)
    // La salida de esta funcion, debe ser: a=2 b=0
    // Si ponemos en params array("param"=>2,"param2"=>1), la salida es sólo a=2
    // El modificador "!" delante del nombre del parametro, indica "haz esto si no está definido"
    static function getParametrizedString($source,$params,$unusedReplacement="")
    {
        if(!$params)
            $params=array();
        /*$start='\[\%';
        $end='\%\]';
        $simpleTag='(?<simple>[^: ,%]*)';
        $complexTag1='(?<complex1>[^: ,]*)';
        $complexTag2='(?<complex2>[^:]*)';
        $joinedComplex="(?:".$complexTag1."|".$complexTag2.")";
        $simpleTagRegexp=$start.$simpleTag.$end;
        $body=':(?<body>.*(?=\%\]))';
        $complexTagRegexp=$joinedComplex.$body.$end;

        $fullRegex="/".$start."(?:(?:".$simpleTag.$end.")|".$complexTagRegexp.")/";
        echo $fullRegex;*/
        $f=function($matches) use ($params){
            return ParametrizableString::parseTopMatch($matches,$params);
        };
        return preg_replace_callback(ParametrizableString::BASEREGEXP,$f,$source);
    }
    static function parseTopMatch($match,$params)
    {

        $t=$match["simple"];
        if($t)
        {
            if(!isset($params[$t]))
            {
                throw new ParametrizableStringException(ParametrizableStringException::ERR_MISSING_REQUIRED_PARAM,array("param"=>$t));
            }
            return $params[$t];
        }
        $t=$match["complex1"];
        $t1=$match["complex2"];
        $mustInclude=false;
        $body='';

        if($t)
        {
            if($t[0]=="!")
            {
                if(!isset($params[substr($t,1)]))
                    $mustInclude=true;
            }
            else
            {
                if(isset($params[$t]))
                    $mustInclude=true;
            }
        }
        else
        {
            $mustInclude=ParametrizableString::parseComplexTag($t1,$params);
        }
        if($mustInclude)
        {
            $f2=function($m2) use ($params){
                return ParametrizableString::parseBody($m2,$params);
            };
            $body=preg_replace_callback(ParametrizableString::BODYREGEXP,$f2,$match["body"]);
        }
        return $body;
    }
    static function parseBody($match,$params)
    {
        $v=$match["simple"];
        if($v)
        {
            if(!isset($params[$v]))
            {
                throw new ParametrizableStringException(ParametrizableStringException::ERR_MISSING_REQUIRED_VALUE,array("param"=>$v));

            }
            return $params[$v];
        }
        $tag=$match["complex"];
        $cVal=isset($params[$tag])?$params[$tag]:null;

        preg_match_all(ParametrizableString::PARAMREGEXP,$match["predicates"],$matches);
        $nMatches=count($matches[0]);
        for($k=0;$k<$nMatches;$k++)
        {
            $func=$matches["func"][$k];
            $args=$matches["params"][$k];
            if($func=="default")
            {
                if($cVal===null)
                    $cVal=trim($args,"'");
                continue;
            }
            if($args=="")
            {
                if($cVal===null)
                {
                    throw new ParametrizableStringException(ParametrizableStringException::ERR_MISSING_REQUIRED_VALUE,array("param"=>$v));
                }
                $cVal=$func($cVal);
                continue;
            }
            // Hay varios parametros.Hacemos otra regex para obtenerlos.
            preg_match_all(ParametrizableString::SUBPARAMREGEXP,$args,$matches2);
            $pars=array();
            $nPars=count($matches2[0]);
            for($j=0;$j<$nPars;$j++)
            {
                $arg=$matches2[1][$j]?trim($matches2[1][$j],"'"):$matches2[2][$j];
                if($arg=="@@")
                    $pars[]=$cVal;
                else
                    $pars[]=$arg;
            }
            $cVal=call_user_func_array($func,$pars);
        }
        return $cVal;
    }
    static function parseComplexTag($format,$params)
    {
        $parts=explode(",",$format);
        $nParts=count($parts);
        for($k=0;$k<$nParts;$k++)
        {
            $cf=$parts[$k];
            $sParts=explode(" ",$cf);
            $negated=$sParts[0][0]=="!";
            if($negated)
                $tag=substr($sParts[0],1);
            else
                $tag=$sParts[0];

            if(count($sParts)==1)
            {
                // Solo esta el tag.En caso de que este negado, y exista, devolvemos false.
                if($negated)
                {
                    if(isset($params[$tag]))
                    {
                        return false;
                    }
                    // Si no esta el tag,y esta negado, continuamos, no hay que procesar mas nada
                    continue;
                }
            }
            // Si no esta el tag actual, lanzamos excepcion.
            if(!isset($params[$tag]))
                throw new ParametrizableStringException(ParametrizableStringException::ERR_MISSING_REQUIRED_PARAM,array("param"=>$tag));

            $result=false;
            switch($sParts[1])
            {
                case "is":{
                    $fName="is_".$sParts[2];
                    $result=$fName($params[$tag]);
                }break;
                case "!=":{
                    $result=($params[$tag]!=$sParts[2]);
                }break;
                case "==":{
                    $result=($params[$tag]==$sParts[2]);
                }break;
                case ">":{
                    $result=($params[$tag]>$sParts[2]);
                }break;
                case "<":{
                    $result=($params[$tag]<$sParts[2]);
                }break;
            }
            if($negated)
                $result=!$result;
            if(!$result)
                return false;
        }
        return true;
    }

    static function getParametrizedStringArray($sourceArray,$params,$unusedReplacement="")
    {
        $result=array();
        foreach($sourceArray as $key=>$value)
        {
            $result[$key]=ParametrizableString::getParametrizedString($value,$params,$unusedReplacement);
        }
        return $result;
    }
    static function applyRecursive(& $sourceArray,$params,$unsetOnException=0)
    {
        foreach($sourceArray as $key=>$value)
        {
            if(is_array($value))
                ParametrizableString::applyRecursive($sourceArray[$key],$params,$unsetOnException);
            else {
                try {
                    $sourceArray[$key] = ParametrizableString::getParametrizedString($value, $params);
                    if($unsetOnException)
                    {
                        if(count(array_keys($sourceArray))==0)
                            unset($sourceArray[$key]);
                    }
                }catch(\Exception $e)
                {
                    if($unsetOnException)
                        unset($sourceArray[$key]);
                }
            }
        }
    }

}
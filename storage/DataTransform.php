<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 02/09/2016
 * Time: 12:30
 */

namespace scLib\data;
include_once(PROJECTPATH."/lib/php/ParametrizableString.php");

class DataTransform
{
    var $transforms;
    function __construct($transforms)
    {
        $this->transforms=$transforms;
    }
    function transform(& $line)
    {
        foreach($line as $key=>$value)
        {
            if(isset($this->transforms[$key])) {
                $d = $this->transforms[$key];
                if (isset($d["value"]))
                    $line[$key] = $this->transformValue($d["value"], $value, $line);
                if(isset($d["generate"]))
                    $line=array_merge($line,$this->generateFields($d["generate"],$value,$line));
            }
            if(isset($this->transforms["*"]))
            {
                if(isset($d["generate"]))
                {
                    $line=array_merge($line,$this->generateFields($d["generate"],'',$line));
                }
            }
        }
    }
    function transformValue($def,$value,$line)
    {
        if($def["method"])
            return $this->{$def["method"]}($value,$line);
        if($def["paramString"])
        {
            return \scLib\php\ParametrizableString::getParametrizedString($def["paramString"],$line);
        }
    }

    function generateFields($def,$value,$line)
    {
        $results=array();
        foreach($def as $k=>$v)
        {
            if(isset($v["method"])) {
                $results[$k]=$v($line);
                continue;
            }
            if(isset($v["regexp"]))
            {
                if(is_array($v["regexp"])) {
                    $reg = $v["regexp"]["reg"];
                    if(isset($v["regexp"]["val"]))
                        $dest=$v["regexp"]["val"];
                    else
                        $dest="\$1";
                    $default=$v["regexp"]["default"];
                }
                else {
                    $reg = $v["regexp"];
                    $dest = "\$1";
                    $default="";
                }
                if(preg_match($reg,$value,$matches))
                {
                    for($j=1;$j<count($matches);$j++)
                        $dest=str_replace("\$".$j,$matches[$j],$dest);
                    $results[$k]=$dest;
                }
                else
                    $results[$k]=$default;
            }
            else
                $results[$k]=$v["value"];
        }
        return $results;
    }

}
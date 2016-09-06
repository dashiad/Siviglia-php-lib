<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 16/09/15
 * Time: 12:12
 */

namespace lib\php;


class ArrayTools {
    static function isAssociative($array)
    {
        for($k = 0, reset($array) ; $k === key($array) ; next($array), $k++);
            return !is_null(key($array));
    }
    // Convierte un array asociativo anidado, en un array asociativo no anidado, donde la clave
    // es el path completo de keys separados por puntos.
    // Ej: input: array("a"=>array("b"=>"c"))
    // output: array("a.b"=>"c")

    static function flattenArray($arr,& $result,$prefix="")
    {
        foreach($arr as $key=>$value)
        {
            if(is_array($value))
                ArrayTools::flattenArray($value,$result,$prefix.$key.".");
            else
                $result[$prefix.$key]=$value;

        }
        return $result;
    }
    static function unflattenArray($arr,& $result)
    {
        foreach($arr as $key=>$value)
        {
            $parts=explode(".",$key);
            $cur = & $result;
            foreach($cur as $k1=>$v1)
            {
                $cur=& $cur[$v1];
            }
            $cur=$value;
        }
    }
    static function findFlatten($key,$arr,$keySeparator=".")
    {
        $parts=explode($keySeparator,$key);
        $current=& $arr;
        foreach($parts as $val)
        {
            if(!isset($current[$val]))
                return null;
            $current=& $current[$val];
        }
        return $current;
    }
    // Hace un merge en profundidad, mergeando arrays posicionales por concatenacion, y diccionarios por sustitucion.
    public static function merge(array $arr1, array $arr2)
    {
        if (empty($arr1)) {
            return $arr2;
        } else if (empty($arr2)) {
            return $arr1;
        }

        foreach ($arr2 as $key => $value) {
            if (is_int($key)) {
                $arr1[] = $value;
            } elseif (is_array($arr2[$key])) {
                if (!isset($arr1[$key])) {
                    $arr1[$key] = array();
                }

                if (is_int($key)) {
                    $arr1[] = ArrayTools::merge($arr1[$key], $value);
                } else {
                    $arr1[$key] = ArrayTools::merge($arr1[$key], $value);
                }
            } else {
                $arr1[$key] = $value;
            }
        }

        return $arr1;
    }
    static function  array_diff_assoc_recursive($array1, $array2)
    {
        foreach($array1 as $key => $value){

            if(is_array($value)){
                if(!isset($array2[$key]))
                {
                    $difference[$key] = $value;
                }
                elseif(!is_array($array2[$key]))
                {
                    $difference[$key] = $value;
                }
                else
                {
                    $new_diff = ArrayTools::array_diff_assoc_recursive($value, $array2[$key]);
                    if($new_diff != FALSE)
                    {
                        $difference[$key] = $new_diff;
                    }
                }
            }
            elseif((!isset($array2[$key]) || $array2[$key] != $value) && !($array2[$key]===null && $value===null))
            {
                $difference[$key] = $value;
            }
        }
        return !isset($difference) ? 0 : $difference;
    }
} 
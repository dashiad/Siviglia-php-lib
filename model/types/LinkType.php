<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 11/05/14
 * Time: 11:46
 */

namespace lib\model\types;
class Link extends StringType
{
    function __construct($def,$value = -1)
    {
        $def=array(
            "TYPE"=>"Link",
            "MINLENGTH"=>4,
            "MAXLENGTH"=>255,
            "ALLOWHTML"=>false,
            "TRIM"=>true
        );
        StringType::__construct($def,$value);
    }
    static function linkify($str)
    {
        $str = mb_strtolower($str, 'utf-8');
        $str=preg_replace(
            array(
                '/[\x{0105}\x{0104}\x{00E0}\x{00E1}\x{00E2}\x{00E3}\x{00E4}\x{00E5}]/u',
                '/[\x{00E7}\x{010D}\x{0107}\x{0106}]/u',
                '/[\x{010F}]/u',
                '/[\x{00E8}\x{00E9}\x{00EA}\x{00EB}\x{011B}\x{0119}\x{0118}]/u',
                '/[\x{00EC}\x{00ED}\x{00EE}\x{00EF}]/u',
                '/[\x{0142}\x{0141}\x{013E}\x{013A}]/u',
                '/[\x{00F1}\x{0148}]/u',
                '/[\x{00F2}\x{00F3}\x{00F4}\x{00F5}\x{00F6}\x{00F8}\x{00D3}]/u',
                '/[\x{0159}\x{0155}]/u',
                '/[\x{015B}\x{015A}\x{0161}]/u',
                '/[\x{00DF}]/u',
                '/[\x{0165}]/u',
                '/[\x{00F9}\x{00FA}\x{00FB}\x{00FC}\x{016F}]/u',
                '/[\x{00FD}\x{00FF}]/u',
                '/[\x{017C}\x{017A}\x{017B}\x{0179}\x{017E}]/u',
                '/[\x{00E6}]/u',
                '/[\x{0153}]/u'),
            array('a', 'c', 'd', 'e', 'i', 'l', 'n', 'o', 'r', 's', 'ss', 't', 'u', 'y', 'z', 'ae', 'oe'),
            $str);


        // Remove all non-whitelist chars.
        return preg_replace(array('/[^a-zA-Z0-9\s\'\:\/\[\]-]/', '/[\s\'\:\/\[\]-]+/', '/[ ]/', '/[\/]/'),
            array('', ' ', '-', '-'), trim($str));
    }

}
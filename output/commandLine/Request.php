<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 15/06/2016
 * Time: 13:22
 */

namespace lib\output\commandLine;
include_once(__DIR__."/../../Request.php");

class Request extends \Request
{
    public function initialize()
    {
        global $argv;
        $arguments=$argv;
    }
    public function offsetExists($offset)
    {
        return false;
    }

    public function offsetGet($offset)
    {
        return "";
    }

    public function offsetSet($offset, $value)
    {
    }

    public function offsetUnset($offset)
    {
    }

    function getParameters()
    {
        return array();
    }

    function getActionData()
    {
        return array();
    }

    function getClientData()
    {
        return array();
    }

    function getOutputType()
    {
        return "html";
    }

}
<?php
namespace lib\reflection\js\extJs;
class Form
{
    var $name;
    var $action;
    function __construct($name,$action)
    {
        $this->name=$name;
        $this->action=$action;
    }
    static function create($name,$action)
    {
    }

}

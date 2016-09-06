<?php
/**
 * Created by PhpStorm.
 * User: Usuario
 * Date: 5/12/13
 * Time: 20:35
 */

namespace lib\php;


class FPMManager {
    var $stack;
    static $instance;
    private function __construct()
    {
        $this->stack=array();

    }
    static function getInstance()
    {
        if(!FPMManager::$instance)
        {
            FPMManager::$instance=new FPMManager();
        }
        return FPMManager::$instance;
    }
    function addTask($instance,$method,$params)
    {
        if (function_exists('fastcgi_finish_request')) {
            $this->stack[]=array($instance,$method,$params);
        }
        else {
            call_user_func_array(array($instance,$method),$params);
        }
    }
    function runWorkers()
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        for($k=0;$k<count($this->stack);$k++)
        {
            $el=$this->stack[$k];
            call_user_func_array(array($el[0],$el[1]),$el[2]);
        }
    }
} 

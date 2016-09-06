<?php

abstract class Request implements \ArrayAccess
{
    var $parameters;
    var $client;
    var $clientType;
    var $urlCandidate;
    static $instance;

    private function __construct()
    {                

    }
    static function getInstance($renew=false)
    { 
        if( Request::$instance!=null && !$renew) {
            return Request::$instance;
        }

       if(defined("STDIN")) {
           include_once(LIBPATH."/output/commandLine/Request.php");
           Request::$instance=new \lib\output\commandLine\Request();
       }
       else {
           // suponemos http
           include_once(LIBPATH."/output/html/HTMLRequest.php");
            Request::$instance=new \lib\output\html\HTMLRequest();
       }
        return Request::$instance;
    }
    abstract function initialize();
    abstract function getParameters();
    abstract function getActionData();
    abstract function getClientData();
    abstract function getOutputType();
}


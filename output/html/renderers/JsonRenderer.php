<?php
namespace lib\output\html\renderers;

class JsonRenderer
{
    public function render($page, $requestedPath, $extraParams)
    {
        header('Content-Type: application/json');

        global $oCurrentUser;

        if(!$page->definition["SOURCES"])
        {
            die(json_encode(array("success"=>0,"errCode"=>1,"errText"=>'No sources for this path')));
        }

        $sources=$page->definition["SOURCES"];

        foreach($sources as $key=>$definition)
        {
            switch($definition["ROLE"])
            {
                case 'action':
                {
                    include_once(LIBPATH."/output/json/JsonAction.php");
                    $actionName=$definition["NAME"];
                    $object=$definition["OBJECT"];
                    // Atencion...habria aqui que primero hacer un JsonAction::fromPost ?
                    $action=new \lib\output\json\JsonAction($object,$actionName);
                    echo $action->execute();
                }break;
                default:{
                    include_once(LIBPATH."/output/json/JsonDataSource.php");
                    $ds=new \lib\output\json\JsonDataSource($definition["OBJECT"],$definition["NAME"],$page,$extraParams,$definition["ROLE"]);
                    echo $ds->execute();
                }break;
            }
        }
        \Registry::$registry["PAGE"]=$page;
    }
}
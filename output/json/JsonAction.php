<?php
namespace lib\output\json;
/**
 * Created by JetBrains PhpStorm.
 * User: Usuario
 * Date: 23/07/13
 * Time: 2:20
 * To change this template use File | Settings | File Templates.
 */
class JsonAction  {
    function __construct($objectName,$actionName)
    {
        $this->objectName=$objectName;
        $this->actionName=$actionName;
    }
    static function fromPost()
    {
        global $request;
        $data=$request->actionData;
        $json=$data['json'];
        $data=json_decode(trim($data['json']),true);
        if(!$data)
        {
            $cad=str_replace('\"','"',$json);
            $data=json_decode($cad,true);
            if(!$data)
                error_log("NULO AL DECODIFICARLO:".json_last_error());
        }
        \Registry::$registry["action"]=$data;
        //$actionName=str_replace(array(".","/"),'',$_POST["name"]);
        //$objectName=str_replace(array(".","/"),'',$_POST["object"]);
        $actionName=$data["name"];
        $objectName=$data["object"];
        //if(!strpos($actionName,"Action"))
        //    $actionName.="Action";
        if(isset($data["keys"]))
        {
            foreach($data["keys"] as $curKey=>$curValue)
                \Registry::$registry["action"]["FIELDS"][$curKey]=$curValue;
        }
        return new JsonAction($objectName,$actionName);
    }

    function execute()
    {
        include_once(PROJECTPATH."/lib/action/Action.php");
        $actionName=$this->actionName;
        $object=$this->objectName;

        if($actionName=="" || $object=="")
            return false; // TODO : Redirigir a pagina de error.

        $actionResult=new \lib\action\ActionResult();

        $formInfo=\lib\output\html\Form::getFormPath($object,$actionName);
        $className=$formInfo["CLASS"];
        $classPath=$formInfo["PATH"];

        // Se incluye la definicion del formulario.
        include_once($classPath);
        $curForm=new $className($actionResult);
        $curForm->resetResult();
        $result=$curForm->process(false);
        if($result->isOk())
        {
            return json_encode($this->composeResultOk($result,$curForm));
        }
        return json_encode(array(
            "result"=>0,"error"=>1,"action"=>$actionResult
        ));
    }
    function composeResultOk($actionResult,$curForm)
    {
        $model=$actionResult->getModel();
        if(!$model)
        {
            // No hay modelo.Posiblemente fue una accion "Delete"
            $result=array("result"=>1,"error"=>0,"action"=>$actionResult,"data"=>null,"start"=>0,"end"=>0,"count"=>0);
        }
        else
        {
            $objName=$model->__getFullObjectName();
            $outputDatasource = 'View';
            $def = $curForm->getDefinition();
            if($def['OUTPUT_DATASOURCE']) {
                $outputDatasource = $def['OUTPUT_DATASOURCE'];
            }
            $ds=\lib\datasource\DataSourceFactory::getDataSource($model->__getFullObjectName(), $outputDatasource);
            //$ds=\lib\datasource\DataSourceFactory::getDataSource($model->__getFullObjectName(), "View");
            $ds->setParameters($model);
            $ds->fetchAll();
            $iterator=$ds->getIterator();

            $result=array(
                "result"=>1,
                "error"=>0,
                "action"=>$actionResult,
                "data"=>$iterator->getFullData(),
                "start"=>$ds->getStartingRow(),
                "end"=>$ds->getStartingRow()+$iterator->count(),
                "count"=>$ds->count()
            );
        }
        return $result;
    }
}
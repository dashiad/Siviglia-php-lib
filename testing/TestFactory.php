<?php
class TestFactoryException extends \lib\model\BaseException
{
    const ERR_GLOBAL_ERROR_RAISED=1;
    const ERR_FIELD_ERROR_RAISED=2;
}
class TestFactory
{
    var $definition;
    var $startupData;
    var $testClass;
    var $modelName;
    var $basePath;
    var $initialized=false;
    var $outputTerminal=false;
    var $lineSeparator="<br>";
    var $bdDirty=true;
    function __construct($modelName, $outputTerminal=false)
    {

        $this->modelName=$modelName;
        $this->outputTerminal=$outputTerminal;
        if ($this->outputTerminal) {
            $this->lineSeparator="\n";
        }

        $obj=new \lib\reflection\model\ObjectDefinition($modelName);
        $this->basePath=$obj->getDestinationFile("tests/Tests.php");
        require_once($this->basePath);

        //Si el objeto es compuesto nos quedamos solo con la última parte
        if (strpos($modelName, '/') !== FALSE) {
            $baseModelName = substr($modelName, strpos($modelName, '/')+1);
        }
        else {
            $baseModelName = $modelName;
        }

        require_once($obj->getDestinationFile($baseModelName.".php"));
        $testClass=$obj->getNamespaced()."\\Tests\\Tests";
        $this->testClass=new $testClass();
        $this->definition=$this->testClass->tests;
        if($this->definition["SERIALIZERS"])
        {
            global $SERIALIZERS;
            $SERIALIZERS=$this->definition["SERIALIZERS"];
        }
        if(method_exists($this->testClass,"initialize"))
        {
            $this->testClass->initialize();
        }


        //No es necesario porque se inicializa en executeTests
        //$this->loadData();
    }

    function loadData()
    {
        if(!$this->bdDirty)
            return;
        $this->bdDirty=false;

        if ($this->outputTerminal) {
            echo "LOADING DATA\n";
        }
        else {
            echo "LOADING DATA<hr>";
        }

        if(isset($this->definition["IMPORTDATA"]))
        {
            foreach($this->definition["IMPORTDATA"] as $val)
            {
                /*
                   TODO: Cuidado con los bucles circulares
                */
                $oF=new TestFactory($val, $this->outputTerminal);
                $oF->loadData();
            }
        }
        if($this->definition["INITIALDATA"])
        {
            foreach($this->definition["INITIALDATA"] as $value)
            {
                $serializer=$value["SERIALIZER"];
                try{
                    $ser=\lib\storage\StorageFactory::getSerializerByName($serializer,false);
                }catch(\lib\storage\Mysql\MysqlException $e)
                {
                    if($e->getCode()!=\lib\storage\Mysql\MysqlException::ERR_NO_DB)
                        throw $e;
                }
                $serDefinition=$this->definition["SERIALIZERS"][$serializer];
                $dbName=$serDefinition["ADDRESS"]["database"];
                if($ser->existsDataSpace($dbName))
                    $ser->destroyDataSpace($dbName);
                $ser->createDataSpace($dbName);
                $mysqlPath=$this->definition["MYSQLPATH"];
                $commandline=$mysqlPath." -u ".$serDefinition["ADDRESS"]["user"].($serDefinition["ADDRESS"]["password"]?" -p".$serDefinition["ADDRESS"]["password"]:"")." -h ".$serDefinition["ADDRESS"]["host"]." ".$dbName["NAME"];
                $commandline.=" < ".dirname($this->basePath)."/".$value["DUMPFILE"];
                echo $commandline;
                $execRes=exec($commandline);
                $ser->useDataSpace($dbName["NAME"]);

            }
        }
    }
    var $variableNames;
    function storeVariable($name,$value)
    {
        global $verbose_mode;

        if (isset($verbose_mode) && $verbose_mode) {
            echo "SETTING $name as $value".$this->lineSeparator;
        }

        $this->variableNames[$name]=$value;
    }
    function getVariable($val)
    {
        global $verbose_mode;

        if($val==null)
            return $val;
        $a=$this->variableNames;

        $returned= preg_replace_callback('/\\[@([^@]*)@\\]/',function($matches) use ($a)
        {
            return $a[$matches[1]];
        },$val);

        if (isset($verbose_mode) && $verbose_mode) {
            echo "ASKED FOR $val, returned $returned".$this->lineSeparator;
        }

        return $returned;
    }
    function execTest($test,$doCheck=true,$verbose=true)
    {
        if(isset($test["REINITIALIZE"]))
        {
            $this->loadData();
        }

        $testArr=$this->definition["TESTS"];
        if(isset($test["REPLAY"])) {
            $gReplay=array();
            $tReplay=array();
            if (isset($test["REPLAY"]["GROUPS"]) && $test["REPLAY"]["GROUPS"]) {
                $gReplay = $this->extractTestFromGroups($test["SHORT_NAME"], $test["REPLAY"]["GROUPS"]);
            }
            if (isset($test["REPLAY"]["TESTS"]) && $test["REPLAY"]["TESTS"]) {
                $tReplay = $this->extractTestFromNames($test["SHORT_NAME"], $test["REPLAY"]["TESTS"]);
            }

            $testsToReplay=array_unique(array_merge($gReplay, $tReplay));
            //No se puede ordenar, pues los test deben ejecutarse en el orden que se indica
            //sort($testsToReplay);
            foreach($testsToReplay as $tr) {
                echo "Re-ejecutando test $tr : ".$this->str_color($testArr[$tr]['SHORT_NAME'], 'blue').' # '.$testArr[$tr]["DESCRIPTION"].$this->lineSeparator;
                $res=$this->execTest($testArr[$tr],false,false);
                if($res["success"]==0)
                {
                    echo $this->lineSeparator."------ Error al re-ejecutar el test $tr --".$this->lineSeparator;
                    echo $this->str_color("ERROR :: ".$res["msg"].$this->lineSeparator.$this->lineSeparator.$this->lineSeparator, 'red');
                    exit();
                }
            }
        }
        // Se ejecutan posibles queries de inicializacion del test
        if(isset($test["SETUP"]))
        {
            for($k=0;$k<count($test["SETUP"]);$k++)
            {
                $cQ=$test["SETUP"][$k];
                $serializerName=$cQ["SERIALIZER"];
                if(!$serializerName)
                    $serializer=\lib\storage\StorageFactory::getDefaultSerializer();
                else
                    $serializer=\lib\storage\StorageFactory::getSerializerByName($serializerName);
                $serializer->getConnection()->insert($this->getVariable($cQ["QUERY"]));

            }
        }

        if(isset($test["OBJECT"]))
            $obj=$test["OBJECT"];
        else
        {
            if(isset($test["MODEL"]))
                $obj=\getModel($test["MODEL"]);
            else
                $obj=\getModel($this->modelName);

            // Si el test no esta llamando a un metodo local (LOCALMETHOD), inicializamos el objeto

            if(!isset($test["LOCALMETHOD"]))
            {
                $staticCall=true;

                if(isset($test["KEYS"]))
                {
                    $staticCall=false;
                    foreach($test["KEYS"] as $key=>$value)
                    {
                        $obj->{$key}=$this->getVariable($value);
                    }
                    $obj->loadFromFields();
                }
            }
        }


        $params=$test["PARAMS"]?$test["PARAMS"]:array();
        $newParams=array();
        foreach($params as $value)
        {
            $newParams[]=$this->getVariable($value);
        }
        $params=$newParams;
        // Si no se espera un resultado, es posible que simplemente tengamos que volver
        if(!isset($test["RESULT"]))
        {
            if(!isset($test["LOCALMETHOD"]) && !isset($test["METHOD"]) && !isset($test["ACTION"]))
                return array("success"=>1);
        }
        $result=$test["RESULT"];

        //if($test["REINITIALIZE"])
        //    $this->loadData();

        // Se obtiene la conexion.
        if(is_object($obj))
            $conn=$obj->__getSerializer("WRITE")->getConnection();
        else
        {
            $tmpInst=getModel($test["OBJECT"]);
            $conn=$tmpInst->__getSerializer("WRITE")->getConnection();
        }
        $conn->query("SET global general_log = 1;");
        $conn->query("SET global log_output = 'table';");
        $conn->query("TRUNCATE mysql.general_log");

        $testResult=false;
        $exceptionHandled=false;

        try
        {
            if(isset($test["METHOD"]))
            {
                $timeStart=microtime(true);
                $callResult=call_user_func_array(array($obj,$test["METHOD"]),$params);
                $timeEnd=microtime(true);
            }
            if(isset($test["LOCALMETHOD"]))
            {
                $timeStart=microtime(true);
                // Se pasa la factoria de testing al metodo, para que pueda publicar variables.
                $params[]=$this;
                $callResult=call_user_func_array(array($this->testClass,$test["LOCALMETHOD"]),$params);
                $timeEnd=microtime(true);
            }

            if(isset($test["ACTION"]))
            {
                $actionName=$test["ACTION"]["NAME"];
                $keys=$test["ACTION"]["KEYS"];
                $paramFields=$test["ACTION"]["PARAMS"];

                $action=\lib\action\Action::getAction($obj->__getFullObjectName(),$actionName);
                $definition=$action::$definition;
                if(isset($definition["INDEXFIELDS"]))
                    $definition["FIELDS"]=array_merge($definition["FIELDS"],$definition["INDEXFIELDS"]);
                $params=new \lib\model\BaseTypedObject($definition);
                if(isset($keys))
                {
                   foreach($keys as $key=>$value)
                   {
                        $keys[$key]=$this->getVariable($value);
                        $params->{$key}=$keys[$key];
                   }
                }
                if(isset($paramFields))
                {
                    foreach($paramFields as $key=>$value)
                    {
                        if(isset($definition["FIELDS"][$key]))
                            $params->{$key}=$this->getVariable($value);
                    }
                }
                $actionResult=new \lib\action\ActionResult();
                //$action->setModel($obj);
                $timeStart=microtime(true);
                $action->process($keys,$params,$actionResult,null);
                $timeEnd=microtime(true);
                if(!$actionResult->isOk())
                {
                    $fieldErrors=$actionResult->getFieldErrors();
                    if(count($fieldErrors)>0)
                    {
                        foreach($fieldErrors as $ii=>$jj)
                        {
                            throw new TestFactoryException(TestFactoryException::ERR_FIELD_ERROR_RAISED,array("field"=>$ii,"value"=>$jj));
                        }
                    }
                    $globalErrors=$actionResult->getGlobalErrors();
                    if(count($globalErrors)>0)
                    {
                        foreach($globalErrors as $ii=>$jj)
                        {
                            $exceptionName=explode("::",$ii);
                            $className=$exceptionName[0];
                            throw new $className($jj["code"]);
                            //throw new TestFactoryException(TestFactoryException::ERR_GLOBAL_ERROR_RAISED,array("key"=>$ii,"value"=>$jj));
                        }
                    }
                }
                $obj=$actionResult->getModel();
            }
            // Despues de realizar todas las acciones, si existe el objeto, se recarga.
            if(isset($obj) && is_object($obj))
                $obj->reload();

            if(isset($obj) && $test["SAVE_KEY"])
            {
                if(!is_object($obj))
                {
                    // Esto ocurre cuando en vez de MODEL se especifica OBJECT.En ese caso, no tenemos
                    // instancia, y se ejecuta una llamada a un metodo estatico.
                    // Si la llamada es estatica, y se pide un SAVE_KEY, se supone que el metodo estaticto ha
                    // retornado una instancia del modelo (es una factoria)
                    $m=$callResult;
                }
                else
                {
                    $m=$obj;
                }
                $keyName=$test["SAVE_KEY"];
                $keys=$m->__getKeys();
                $realKeys=$keys->get();
                $keyskeys=array_keys($realKeys);
                $v=$realKeys[$keyskeys[0]];
                if(is_object($v))
                    $v=$v->__getRaw();

                $this->storeVariable($keyName,$v);
            }
            if(isset($obj) && $test["SAVE_FIELD"])
            {
                for($k=0;$k<count($test["SAVE_FIELD"]);$k++)
                {
                    $v=$obj->{$test["SAVE_FIELD"][$k]["FIELD"]};
                    if(is_object($v))
                        $v=$v->__getRaw();

                    $this->storeVariable($test["SAVE_FIELD"][$k]["NAME"],$v);
                }
            }
        }catch(Exception $e)
        {

            $class=get_class($e);
            if(!isset($result["EXCEPTION"]))
                $testResult=array("success"=>0,"msg"=>"Got exception ".$class." code ".$e->getCode().", params:".$e->getParamsAsString());
            else
            {
                if($class==$result["EXCEPTION"]["CLASS"] && $e->getCode()==$result["EXCEPTION"]["CODE"])
                {
                    $exceptionHandled=true;
                    $testResult=true;
                }
                else
                    $testResult=array("success"=>0,
                        "msg"=>"Expected exception ".$result["EXCEPTION"]["CLASS"]." with code ".$result["EXCEPTION"]["CODE"].", but got exception ".$class." with code ".$e->getCode().",".$e->getParamsAsString());
            }
        }

        if ($verbose) {
            $conn->query("SET global general_log = 0;");
            $cur=$conn->cursor("SELECT command_type,argument from mysql.general_log");
            echo $this->lineSeparator."Queries:";
            $n=0;
            while($arr=$conn->fetch($cur))
            {
                echo $n.":".$arr["argument"].$this->lineSeparator;
                $n++;
            }
            echo $this->lineSeparator.$this->lineSeparator."Execution time:".($timeEnd-$timeStart).$this->lineSeparator.$this->lineSeparator;
        }
        // Ponemos bddirty a true, para que REINITIALIZE=1 recargue la base de datos.al ejecutar cualquier accion, suponemos que ya se ha ensuciado la BD
        $this->bdDirty=true;
        if(is_array($testResult))
            return $testResult;

        if($doCheck==false)
            return array("success"=>1);

        // Se esperaba una excepcion, pero no ha ocurrido.
        if($result["EXCEPTION"] && !$exceptionHandled)
        {
            return array("success"=>0,
                "msg"=>"Expected exception ".$result["EXCEPTION"]["CLASS"]." with code ".$result["EXCEPTION"]["CODE"].", but no exception was thrown");
        }
        if($result["FUNCTION"])
        {
            foreach($result["FUNCTION"] as $val)
            {
                $params=array();
                if(isset($val["PARAMS"]))
                {
                    foreach($val["PARAMS"] as $key=>$value)
                    {
                        $params[]=$this->getVariable($value);
                    }
                }
                $callResult=call_user_func_array(array($this->testClass,$val["NAME"]),$params);
                if($callResult==1)
                {
                    $testResult=true;
                }
                else
                    return array("success"=>0,"msg"=>$callResult);
             }
        }

        if($result["DATA"])
        {
            foreach($result["DATA"] as $key=>$value)
            {
                $model=\getModel($key);
                $table=$model->__getTableName();
                $qP="SELECT COUNT(*) as N FROM $table WHERE ";
                foreach($value as $cRow)
                {
                    $qS="";
                    $qSA=array();
                    foreach($cRow["FIELDS"] as $key=>$value)
                    {
                        // Si la key comienza por @, no se toca su valor.Sirve para poder crear funciones, ">","<","IS NOT NULL",etc
                         if($key[0]=="@")
                             $qSA[]=substr($key,1)." ".$this->getVariable($value);
                        else
                            $qSA[]=$key."='".mysql_escape_string($this->getVariable($value))."'";
                    }
                    $qS=$qP.implode(" AND ",$qSA);
                    $serializer=$model->__getSerializer("READ");
                    $myConn=$serializer->getConnection();
                    $res=$myConn->select($qS);
                    if($res[0]["N"]!=$cRow["EXPECTED"])
                    {
                        return array("success"=>0,
                        "msg"=>"Expected ".$cRow["EXPECTED"]." rows , but got ".$res[0]["N"]." from the following query :".$qS);
                    }
                }
            }

        }
        if($testResult==true)
            return array("success"=>1);
        return array("success"=>1,"msg"=>"No verification method specified");
    }

    function executeTests($startTest=null,$endTest=null, $verbose=true)
    {
        $testDef=$this->definition;
        $this->loadData();
        $testArr=$testDef["TESTS"];
        if($testDef["USER_ID"])
        {
            global $oCurrentUser;
            $oCurrentUser=new \backoffice\WebUser(\lib\storage\StorageFactory::getDefaultSerializer());
            $oCurrentUser->loadById($testDef["USER_ID"]);
        }
        echo "Comenzando tests:".$this->lineSeparator."-------------------------------------------------------------".$this->lineSeparator;
        $nTests=count($testArr);
        $nValid=0;
        $invalids=array();
        if($startTest==null)
            $startTest=0;
        else
        {
            if(!is_numeric($startTest))
            {
                $newStart=0;
                for($k=0;$k<$nTests;$k++)
                {
                    if(isset($testArr[$k]["SHORT_NAME"]) && $startTest==$testArr[$k]["SHORT_NAME"])
                        $newStart=$k;
                }
                $startTest=$newStart;
            }

        }
        if($endTest==null)
        {
            $endTest=$nTests;
        }
        else
        {
            if(!is_numeric($endTest))
            {
                $newEnd=$nTests;
                for($k=0;$k<$nTests;$k++)
                {
                    if(isset($testArr[$k]["SHORT_NAME"]) && $endTest==$testArr[$k]["SHORT_NAME"])
                        $newEnd=$k;
                }
                $endTest=$newEnd;
            }
        }

        for($k=$startTest;$k<$endTest;$k++)
        {
            echo "Test [".($k)."]/[".($nTests-1)."] ";
            if ($testArr[$k]["SHORT_NAME"]) {
                echo $this->str_color($testArr[$k]["SHORT_NAME"], 'blue')." # ";
            }
            if($testArr[$k]["DESCRIPTION"])
                echo $testArr[$k]["DESCRIPTION"];
            echo $this->lineSeparator."-------------------------------------------------------------".$this->lineSeparator;

            $result=$this->execTest($testArr[$k], true, $verbose);

            if($result["success"]==0)
            {
                $result["INDEX"]=$k;
                echo $this->str_color("ERROR :: ".$result["msg"].$this->lineSeparator.$this->lineSeparator.$this->lineSeparator, 'red');
                die();
                $invalids[]=$result;
            }
            else
            {
                echo "OK!!".$this->lineSeparator.$this->lineSeparator;
                $nValid++;
            }
        }
        echo $this->lineSeparator."-------------------------------------------------------------".$this->lineSeparator;

        echo "Resultado: Tests OK: ".$this->str_color($nValid, 'green')." Tests NO OK: ".$this->str_color(count($invalids),'red').$this->lineSeparator;
        if(count($invalids)>0)
        {
            echo $this->lineSeparator."-------------------------------------------------------------".$this->lineSeparator;
            echo $this->str_color("TESTS NO VALIDOS : ".$this->lineSeparator, 'red');
            for($k=0;$k<count($invalids);$k++)
            {
                echo $this->str_color("TEST ".$invalids[$k]["INDEX"].": ".$invalids[$k]["msg"].$this->lineSeparator, 'red');
            }
        }
    }

    function str_color($str, $color)
    {
        $aux = '';

        switch ($color) {
            case 'blue':
                $aux = "\033[36m" . $str . "\033[37m";
                break;
            case 'red':
                $aux = "\033[31m" . $str . "\033[37m";
                break;
            case 'green':
                $aux = "\033[33m" . $str . "\033[37m";
                break;
            default:
                $aux = $str;
        }

        return $aux;
    }

    function extractTestFromGroups($testName, $groups)
    {
        $testDef=$this->definition;
        $groupsArr=$testDef["TESTS_GROUPS"];
        $result=array();

        foreach($groups as $gr) {
            $items = $groupsArr[$gr];
            $result=array_merge($result, $this->extractTestFromNames($testName, $items));
        }

        return $result;
    }

    function extractTestFromNames($testName, $tests)
    {
        $testDef=$this->definition;
        $testArr=$testDef["TESTS"];
        $result=array();
        $max=$this->getTestNumberFromName($testName);

        if (!$tests)
            return $result;

        foreach($tests as $test) {
            for($i=0;$i<$max;$i++) {
                if ($testArr[$i]['SHORT_NAME']==$test) {
                    $result[]=$i;
                }
            }
        }

        return $result;
    }

    function getTestNumberFromName($name)
    {
        $testDef=$this->definition;
        $testArr=$testDef["TESTS"];

        for($i=0;$i<count($testArr);$i++) {
            if ($testArr[$i]['SHORT_NAME']==$name) {
                return $i;
            }
        }

        return 0;
    }
}

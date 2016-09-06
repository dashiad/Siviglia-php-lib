<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 26/03/15
 * Time: 21:10
 *
 * La configuracion que recibe es:
 * array
 * (
 *     "BUNDLES"=>array("<bundleName>"=>"<base path donde almacenar los ficheros>", .....),
 *     "DOCUMENT_ROOT"=>"<document root de la web>"
 *     "WEB_ROOT"=>"<url base de la web>"
 * )
 */
include_once(dirname(__FILE__)."/../../Plugin.php");

/*
 *
 * Declaracion de dependencia
[@DEPENDENCY]
   [_BUNDLE]Global[#]

   [_CONTENTS]
       [_META][_MODEL]Bag[#][#]
       [_CSS][_FILE]a/b/c[#][#]
			 [_CODE]...[#]
       [_SCRIPT][_FILE]q/h/i[#][#]
				[_CODE]...[#]
       [_WIDGET]
             [_OBJECT]Bag[#]
             [_NAME]ViewBag[#]
       [#]
       [_MODEL]Bag[#]
    [#]
[#]

[@DEPENDENCY]
   [_PHASE]Headers[#] // [_PHASE]BODYSTART[#] [_PHASE]BODYEND[#]
[#]

// Configuracion
"BUNDLES"=>array(

    "Global"=><localizacion de los ficheros>
)
"MACROS"=>array("<rep>"=>"<value>")
"DOCUMENT_ROOT"=><path al document root de la web>
"WEB_ROOT"=><url de la web>
"WIDGET_PATH"=>array("/es_mobile","/es","/") <-- Lista de carpetas dentro de los objetos donde buscar widgets

 */

class DEPENDENCY_BUNDLE {
    var $resources=array();
    var $layoutManager=null;
    static $bundlePaths=array();
    var $refTime;
    function __construct($name,$basePath,$documentRoot,$webRoot="")
    {
        $this->name=$name;
        $this->basePath=$basePath;
        $this->documentRoot=$documentRoot;
        $this->usedFiles=array();
        $this->webRoot=$webRoot;
        srand(microtime(true));
    }
    function setManager($manager)
    {
        $this->layoutManager=$manager;
    }
    function addResource($type,$code,$info,$phase=null)
    {
        if(!$phase)
        {
            if($type=="CSS" || $type=="SCRIPT")
                $phase="HEADERS";
            else
                $phase="BODYSTART";
        }
        $this->resources[$phase][$type][]=array($code,$info);
    }
    function save($phase)
    {
        $destPath=$this->documentRoot."/".$this->basePath;
        if(!is_dir($destPath))
        {
            mkdir($destPath,0777,true);
        }
	$baseText="";
        if(isset($this->resources[$phase]))
        {
            $this->refTime=time();
            $cssText="";
            $jsText="";
            $htmlText="";
            $res=$this->resources[$phase];
            $returnText="";
            foreach($res as $key=>$value)
            {
                $text="";
                for($j=0;$j<count($value);$j++)
                {
                    $cur=$value[$j];
                    // LO SIGUIENTE ES DEMASIADO PROBLEMATICO PARA MANEJAR LOS WIDGETS
                    // Hay que evitar que se incluya varias veces el mismo fichero
                    // Se mira si ese hash ya se ha incluido.
                    //if(isset($this->usedFiles[$cur[1][2].$key]) )
                    //    continue;
                    //Si no se ha incluido, se incluye, y se marca.
                    //    $this->usedFiles[$cur[1][2].$key]=1;

                    // Se añade el comentario de inicio.
                    $text.=$cur[1][0];
                    if($cur[0]!==null){
                        if(!defined("DEVELOPMENT") || DEVELOPMENT==0)
                        // Codigo inline;
                        $text.=$cur[0];
                        else
                        {
                            switch($key)
                            {
                                case "SCRIPT":{
                                     $returnText.="<script type=\"text/javascript\">\n".$cur[0]."</script>\n";

                                }break;
                                case "CSS":{
                                    $returnText.="<style type=\"text/css\">\n".$cur[0]."</style>\n";

                                }break;
                                case "HTML":
                                {
                                    $returnText.=$cur[0];

                                }break;
                            }
                            continue;
                        }
                    }
                    else
                    {
                        // Codigo en fichero.
                        // Hay que aniadir este fichero como dependencia del layout actual.
                        $parsed=0;
                        $url=null;
			            if(!defined("DEVELOPMENT") || DEVELOPMENT==0)
			            {
                        	$this->layoutManager->addDependency($cur[1][3]);
                            if(isset($cur[1][3]))
                            {
                        	    if(!is_file($cur[1][3]))
                        	    {
                            		var_dump($cur);
                            		die("La dependencia ".$cur[1][3]." no se encuentra.");
                        	    }
                        	    $text.=file_get_contents($cur[1][3]);
                                $parsed=1;
                            }
                            else
                            {
                                // Es un recurso externo, que no especifica FILE.
                                $url=$cur[1][1][0];
                            }
			            }
			            if(!$parsed)
			            {

                            if($url==null)
                            {
                                if($cur[1][4])
                                    $url=$cur[1][4];
                                else
                                    $url=str_replace($this->documentRoot,"",$cur[1][0]);
                            }
                            switch($key)
                            {
                                case "CSS":
                                {
                                    $returnText.='<link rel="stylesheet" type="text/css" href="'.$url.'"/>'."\n";
                                }break;
                                case "SCRIPT":
                                {
                                    $returnText.='<script type="text/javascript" src="'.$url.'" ></script>'."\n";
                                }break;
                                case "HTML":
                                {
                                }
                            }
                            continue;

			            }
                    }
                    // Se añade el comentario de fin
                    $text.=$cur[1][1];
                }
                // Se guarda el fichero, segun la fase
                if($key!="HTML")
                {
                    $suffix="";
                    switch($key)
                    {
                        case "CSS":
                        {
                            $suffix=".css";
                        }break;
                        case "SCRIPT":
                        {
                            $suffix=".js";
                        }
                    }
                    $url="";
                    if($text!="")
                    {

                        $cTime=$this->refTime;
                        $newName=$this->name."-".$phase."-";
                        $baseNewFileName=$destPath."/".$newName;
		/*
		Si se crea un fichero bundle con todo el js y el css, y se le pone un nombre distinto
tras cada regeneracion de cache, puede ocurrir lo siguiente:
	Dadas 2 plantillas, A y B,  A es regenerada en el momento 1 , y su codigo incluye un <script> cuyo src termina en ....-1.js
	B es regenerada en el momento 2, y genera lo mismo con -2.js.Resultado: la plantilla A intenta cargar un js que ya no existe.
	Por ello, lo que se hace, es meter ese numero (1 o 2), en un fichero, y hacer que las plantillas carguen ese fichero para ver cual es el sufijo actual

		***/
		
			if(!DEPENDENCY_BUNDLE::$bundlePaths[$this->basePath])
			{
				$baseText="<?php \$__serialized__bundle__".$this->name."=file_get_contents('".$this->basePath."/bundle_".$this->name.".srl');?>";
				file_put_contents($this->basePath."/bundle_".$this->name.".srl",$cTime);	
				DEPENDENCY_BUNDLE::$bundlePaths[$this->basePath]="__serialized__bundle__".$this->name;
			}
			$includeCode="<?php echo \$__serialized__bundle__".$this->name.";?>";
		/****/	
                        $this->cleanOldFiles($destPath,$newName,$suffix);

                        $fileNameToInclude=$baseNewFileName.$includeCode.$suffix;
                        $fileName=$baseNewFileName.$cTime.$suffix;
                        $url=str_replace($this->documentRoot,"",$fileNameToInclude);
                        if($url[0]!="/")
                            $url="/".$url;
                        file_put_contents($fileName,$text);
                    }
                    $url=$this->webRoot.$url;
                }
                    switch($key)
                    {
                        case "CSS":
                        {
                            $returnText.='<link rel="stylesheet" type="text/css" href="'.$url.'"/>'."\n";
                        }break;
                        case "SCRIPT":
                        {
                            $returnText.='<script type="text/javascript" src="'.$url.'" ></script>'."\n";
                        }break;
                        case "HTML":
                        {
                            $returnText.=$text;
                        }
                    }
                }
                return $baseText.$returnText;
            }
        return "";
    }
    function cleanOldFiles($destPath,$baseFileName,$suffix)
    {
        $op=opendir($destPath);
        while($curFile=readdir($op))
        {
            if(preg_match("/^".$baseFileName.".*".$suffix.'$/',$curFile))
            {
                @unlink($destPath."/".$curFile);
            }
        }
    }
}

class DEPENDENCY extends Plugin {

    static $bundles=null;
    static $usedWidgets=null;
    static $usedModels=null;
    function __construct($parentWidget,$layoutContents,$layoutManager)
    {
        $this->parentWidget=$parentWidget;
        $this->layoutContents=$layoutContents;
        $this->layoutManager=$layoutManager;
        $this->params=$this->layoutManager->getPluginParams("DEPENDENCY");
        if(DEPENDENCY::$bundles==null)
        {
            DEPENDENCY::$bundles=array();
            if(!isset($this->params["BUNDLES"]))
                die("No existen BUNDLES en la configuracion del plugin DEPENDENCY");
            foreach($this->params["BUNDLES"] as $key=>$value)
            {
                DEPENDENCY::$bundles[$key]=new DEPENDENCY_BUNDLE($key,$value,$this->params["DOCUMENT_ROOT"],isset($this->params["WEB_ROOT"])?$this->params["WEB_ROOT"]:"");
                DEPENDENCY::$bundles[$key]->setManager($this->layoutManager);
            }
        }
    }




    function initialize()
    {

    }
    function parse()
    {
        $spec=$this->parseNode($this->layoutContents,true);
        $currentNode=array("TYPE"=>"HTML","TEXT"=>"");

        $bundle=$this->getNodesByTagName("BUNDLE",$spec);
        if(count($bundle)>0)
        {
            $currentNode["TEXT"].="<!-- @DEPENDENCY LIST-->";
            $returnValue=array($currentNode);

            $this->currentBundle=$bundle[0];
            $contents=$this->getNodesByTagName("CONTENTS",$spec);
            $contents=$contents[0];
            for($j=0;$j<count($contents);$j++)
            {

                    $method="parse_".$contents[$j][0];
                    $returnValue=array_merge($returnValue,$this->$method($this->mergeNodes($contents[$j][1]),$this->currentBundle));

            }
            return $returnValue;
        }
        $phase=$this->getNodesByTagName("PHASE",$spec);
        if(count($phase) > 0)
        {
            $currentNode["TEXT"]="<!-- @DEPENDENCY PHASE ".strtoupper($phase[0])."-->";
        }
        return array($currentNode);
    }
    function parse_CSS($spec,$bundle)
    {
        if(!isset(DEPENDENCY::$bundles[$bundle]))
        {
            die("UNKNOWN DEPENDENCY BUNDLE: $bundle");
        }
        $info=$this->parse_file($spec,"CSS");

        if(!$info[2])
            $info[2]="HEADERS";
        DEPENDENCY::$bundles[$bundle]->addResource("CSS",$info[0],$info[1],$info[2]);
        return array();
    }
    function parse_SCRIPT($spec,$bundle)
    {
        $info=$this->parse_file($spec,"SCRIPT");
        if(!$info[2])
            $info[2]="HEADERS";
        DEPENDENCY::$bundles[$bundle]->addResource("SCRIPT",$info[0],$info[1],$info[2]);
        return array();
    }
    function parse_JSMODEL($spec,$bundle)
    {
        include_once(LIBPATH."/reflection/Meta.php");
        $objName=$spec["OBJECT"][0];
        // Cuando se carga un modelo, hay que meter tanto su meta, como la instancia del fichero.
        include_once(PROJECTPATH."/lib/reflection/model/ObjectDefinition.php");
        $Obj=new \lib\reflection\model\ObjectDefinition($objName);
        $srcFile=$Obj->getDestinationFile("/js/Model.js");

        // Se marca este modelo como ya usado.
        if(DEPENDENCY::$usedModels[$srcFile])
            return array(array("TYPE"=>"HTML","TEXT"=>""),array("TYPE"=>"HTML","TEXT"=>""));
        DEPENDENCY::$usedModels[$srcFile]=1;

        $canonical=$Obj->getFullNormalizedName(".");
        $mInstance=new ModelMetaData($objName);
        $meta=$mInstance->definition;
        $code="Cache.add('".$canonical.".Meta.Model',".json_encode($meta).");";

        $info=$this->__getFileHash("SCRIPT",$srcFile);

        DEPENDENCY::$bundles[$bundle]->addResource("SCRIPT",$code,$info,"HEADERS");

        // El fichero del modelo en si, se obtiene de un parse_SCRIPT, para que asi se
        // meta como dependencia de la plantilla
        // Lo que no se mete como dependencia de la plantilla, es el meta del modelo.
        $modelFileSpec=array("FILE"=>array($srcFile));
        if(isset($spec["URL"]))
            $modelFileSpec["URL"]=$spec["URL"];
        return $this->parse_SCRIPT($modelFileSpec,$bundle);
    }
    function parse_DATASOURCE($spec,$bundle)
    {
        $cacheable=true;
        $objName=$spec["OBJECT"][0];
        $dsName=$spec["DATASOURCE"][0];
        $cacheName=$spec["NAME"][0];
        $params=$spec["PARAMS"][0];
        $parsedParams=array();
        if($params)
        {
            // Si un parametro comienza con "$", se supone que es una variable php.
            // Esto hace que sea no cacheable.
            for($k=0;$k<count($params);$k++)
            {
                $key=$params[$k][0];
                $value=$params[$k][1];
                $node=$params[$k][2];


                $parsedParams[$key]=$value;
                if(preg_match('/<\?php echo \$([^;]*);\?>/',$value,$results))
                {
                    // Esto no funcionaria para variables globales...
                    $cacheable=false;
                    $varName=$results[1];
                    // Antes de llegar al plugin, el parser ha convertido cosas del tipo
                    // [_id_user]$id_user[#]
                    //  en
                    // <?php echo $v263_id_user;
                    // Hay que eliminar ese prefijo, ya que el PHPElement que se crea de retorno,
                    // lo va a meter tambien.Si no se quitara,acabaria duplicado.
                    $parts=explode("_",$varName);
                    if(count($parts)>1)
                    {
                        array_shift($parts);
                        $varName=implode("_",$parts);
                    }
                    $parsedParams[$key]='$'.$varName;
                }
                else
                {
                    ob_start();
                    eval("?>".$parsedParams[$key]);
                    $newValue=ob_get_clean();
                    $parsedParams[$key]=$newValue;
                }

            }
        }
        $fileName=$this->__getCurrentFileNameEquivalent();
        $fileName.=("DATASOURCE".$objName.$dsName);
        $sum=md5($fileName);
        $commentTextStart=" begin $sum";
        $commentTextEnd=" end $sum";
        $commentStart="/*!  ".$commentTextStart." [DATASOURCE] $fileName */\n";
        $commentEnd="\n/*! ".$commentTextEnd." $fileName */\n";
        $info=array($commentStart,$commentEnd,$sum,null);


        $f2=rand(0,100000);

        if($cacheable)
        {
            // Si es un datasource cacheable,podemos obtener su valor ahora, y
            // guardarlo como una dependencia JS
            include_once(LIBPATH."/output/json/JsonDataSource.php");
            $instance=new lib\output\json\JsonDataSource($objName,$dsName,(object)$parsedParams);
            $encoded=$instance->execute();
            $fullcode="Cache.add('".$cacheName."',".$encoded.");";

            DEPENDENCY::$bundles[$bundle]->addResource("SCRIPT",$fullcode,$info,"HEADERS");
            return array();
        }
        else
        {
            $returned=array();
            // Hay que preparar un PHPNode
            $code='<?php include_once(LIBPATH."/output/json/JsonDataSource.php");';
            $code.='$instance'.$f2.'=new lib\output\json\JsonDataSource("'.$objName.'","'.$dsName.'",array(';
            $phpparams=array();
            foreach($parsedParams as $key=>$value)
            {
                $phpparams[]='"'.$key.'"=>"'.$value.'"';
            }
            $code.=implode(",",$phpparams)."));\n";
            $code.='$result'.$f2.'=$instance'.$f2.'->execute(); ?>';
            // Se mete esto como un bloque PHP
            $returned[]=array("TYPE"=>"PHP","TEXT"=>$code);
            // Ahora hay un problema:
            // Los siguientes elementos se van a meter en el sitio donde se *declare*
            // las dependencias, y no donde se *vuelque* la fase asociada (headers,etc).En general, si solo se devuelven
            // HTMLElement y PHPElement, el resultado de esta dependencia no va a ser codigo que se ejecute en la secuencia
            // que aparece en [@DEPENDENCY], sino que se ejecutara en la *declaracion* de las dependencias, y no en
            // su lugar correspondiente dentro de la fase del bundle.
            // Por otro lado, no se puede poner simplemente un <script>, ya que, en ese caso, si no estamos en desarrollo,
            // en el fichero js del bundle, tendremos codigo php que se necesita para volcar el datasource.
            // Solucion: el codigo php se mete en una funcion javascript,que se crea inline (como un HTMLElement), y
            // lo que se mete en el bundle, es la llamada a esa funcion.
            // Primero se necesita un nombre de funcion aleatorio.

            $funcName="sf".rand(0,100000);

            $returned[]=array("TYPE"=>"HTML","TEXT"=>"\n\n<script>function ".$funcName."(){Cache.add('".$cacheName."',");
            $returned[]=array("TYPE"=>"PHP","TEXT"=>"<?php echo \$result".$f2.";?>");
            $returned[]=array("TYPE"=>"HTML","TEXT"=>");}</script>");
            $fullcode=$funcName."();";
            DEPENDENCY::$bundles[$bundle]->addResource("SCRIPT",$fullcode,$info,"HEADERS");
            return $returned;
        }
    }
    function parse_WIDGET($spec,$bundle)
    {
        if(isset($spec["OBJECT"]))
        {
            $obj=$spec["OBJECT"][0];
            include_once(PROJECTPATH."/lib/reflection/model/ObjectDefinition.php");
            $obj=new \lib\reflection\model\ObjectDefinition($obj);
            if(isset($this->params["WIDGET_PATH"]))
                $path=$this->params["WIDGET_PATH"];
            else
                $path=array("/");


            foreach($path as $key=>$value)
            {
                $srcFile=$obj->getDestinationFile("/js/".$value."/".$spec["NAME"][0].".wid");
                if(is_file($srcFile))
                    break;
            }
            if(!is_file($srcFile))
            {
                die("Widget no encontrado : Objeto:".$spec["OBJECT"][0]." Widget:".$spec["NAME"][0]);
            }
        }
        else
        {
             $srcFile=$spec["FILE"][0];
            if(!is_file($srcFile))
            {
                die("Widget no encontrado : Path:".$srcFile);
            }
        }
        if(DEPENDENCY::$usedWidgets[$srcFile])
            return array(new \CHTMLElement(""),"",new \CHTMLElement(""));

        DEPENDENCY::$usedWidgets[$srcFile]=1;
        $srcContents=file_get_contents($srcFile);
        $oParser=new CWidgetGrammarParser("subwidgetFile",1,null,$this->layoutManager);
        $result=$oParser->compile($srcContents,$this->layoutManager->getLang(),$this->layoutManager->getTargetProtocol());


        // Se crean dos nodos html para despues poder cortar el contenido, y moverlo a su fase correspondiente.
        $uuid=uniqid();
        $pre=array("TYPE"=>"HTML","TEXT"=>"<!-- HTML_DEPENDENCY $bundle BODYSTART $uuid -->");
        $post=array("TYPE"=>"HTML","TEXT"=>"<!-- HTML DEPENDENCY END $uuid -->");
        $layout=array("TYPE"=>"HTML","TEXT"=>$result);
        return array($pre,$layout,$post);
    }

    function parse_file($spec,$type)
    {
        $phase=null;

        if($spec["PHASE"])
        {
            $phase=$spec["PHASE"][0];
        }
        $code=null;
        $info=null;
        $file=null;
        if($spec["CODE"])
        {
            $info=$this->__getFileHash($type);
            $code=$this->__getUntaggedContents($spec["CODE"][0],$type=="CSS"?"style":"script");
        }
        $macros=$this->params["MACROS"];
        $mkeys=array_keys($macros);
        $mvalues=array_values($macros);
        if($spec["FILE"])
        {
            $file=str_replace($mkeys,$mvalues,$spec["FILE"][0]);
            if(!is_file($file))
            {
                die("No se encuentra la dependencia ".$file);
            }
            $info=$this->__getFileHash($type,$file);
            // El path tiene que ser absoluto
        }
        // Si no hay especificado un FILE, sino solo una URL, $info tendra solo 1 elemento.
        if($spec["URL"])
        {
            $info[]=str_replace($mkeys,$mvalues,$spec["URL"][0]);
        }
        else
            $info[]="";

        return array($code,$info,$phase);
    }

    function __getFileHash($type,$fileName=null)
    {
        $subPath="";
        if(!$fileName)
        {

            $fileName=$this->__getCurrentFileNameEquivalent();
        }
        else
        {
            $subPath=$fileName;
            $basePath=$fileName;
            $fullPath=realPath($basePath);
            $fileName=$fullPath;

        }
        $sum=md5($type.$fileName);
        $commentTextStart=" begin $sum";
        $commentTextEnd=" end $sum";
        $commentStart="/*!  ".$commentTextStart." [$type] $fileName */\n";
        $commentEnd="\n/*! ".$commentTextEnd." $fileName */\n";
        return array($commentStart,$commentEnd,$sum,$fileName);
    }
    function __getCurrentFileNameEquivalent()
    {
        $layoutName=str_replace($this->layoutManager->getBasePath(),"",$this->layoutManager->getLayout());
        $parts=explode(".",$layoutName);
        unset($parts[count($parts)-1]);
        $curWidget=$this->layoutManager->currentWidget;
        $subPath=str_replace($this->layoutManager->getBasePath(),"",str_replace("//","/",$curWidget["FILE"]));
        $p=basename($subPath);
        $p2=realpath(dirname($subPath));
        $subPath=$p2."/".$p;
        return $subPath;
    }
    function __getUntaggedContents($text,$tag)
    {
        if(is_array($text))
        {
            $h=11;
            $q=20;
        }
        $text=trim($text);
        if($text[0]=="<" && $text[strlen($text)-1]==">")
        {
            $text=substr($text,strpos($text,">")+1);
            $text=substr($text,0,-(strlen($tag)+3));
        }
        return $text;
    }

    function postParse($contents)
    {
        // Primero, se cortan todas las dependencias HTML, y se meten en los bundles correctos.
        // <!-- HTML_DEPENDENCY $bundle BODYSTART $uuid -->
        // <!-- HTML DEPENDENCY END $uuid -->
        $res=preg_match_all("/<!-- HTML_DEPENDENCY ([^-]+) ([^-]+) ([^-]+) -->/",$contents,$matches);
        $nMatches=count($matches[0]);
        $htmlDeps=array();
        for($k=0;$k<$nMatches;$k++)
        {
            // Hay que tener cuidado con las dependencias "nested".Hay que resolver primero las mas
            // internas.Esto hace que se detecten todas las dependencias HTML, se calcule su longitud,
            // se ordenen de mayor a menor longitud, y se vuelquen en orden de menor tamaño a mayor
            $info=array(
                "text"=>$matches[0][$k],
                "bundle"=>$matches[1][$k],
                "phase"=>$matches[2][$k],
                "uuid"=>$matches[3][$k],
                "start"=>strpos($contents,$matches[0][$k]),
                "textEnd"=>"<!-- HTML DEPENDENCY END ".$matches[3][$k]." -->"
            );
            $info["end"]=strpos($contents,$info["textEnd"]);
            $info["endLen"]=strlen($info["textEnd"]);
            $info["len"]=$info["end"]-$info["start"];
            $htmlDeps[]=$info;
        }
        // Ahora, se ordenan
        if(count($htmlDeps)>0)
        {
            usort($htmlDeps,function($a,$b){
                if($a["len"]==$b["len"])
                    return 0;
                return($a["len"]<$b["len"])?-1:1;
            });
            // Se van cortando, una a una, las dependencias html.
            for($k=0;$k<count($htmlDeps);$k++)
            {
                $i=$htmlDeps[$k];
                $start=strpos($contents,$i["text"]);
                $end=strpos($contents,$i["textEnd"]);
                $len=($end-$start)+$i["endLen"];
                $htmlContent=substr($contents,$start,$len);
                if(!isset(DEPENDENCY::$bundles[$i["bundle"]]))
                {
                    die("Se intenta introducir informacion de dependencia a un bundle no existente: ".$i["bundle"]);
                }
                DEPENDENCY::$bundles[$i["bundle"]]->addResource("HTML",$htmlContent,
                        array("","",md5($htmlContent),null),$i["phase"]);
                $contents=str_replace($htmlContent,"",$contents);
            }
        }

        // "<!-- @DEPENDENCY PHASE ".strtoupper($spec["PHASE"][0])."-->";
        $res=preg_match_all("/<!-- @DEPENDENCY PHASE ([^-]*)-->/",$contents,$matches);
        $nMatches=count($matches[0]);
        for($k=0;$k<$nMatches;$k++)
        {
            $phase=trim($matches[1][$k]);
            $text="";
            foreach(DEPENDENCY::$bundles as $key=>$value)
            {
                $text.=$value->save($phase);
            }
            $contents=str_replace($matches[0][$k],$text,$contents);
        }
        // Se limpian todos los bundles, para permitir que otras llamadas a renderizar plantillas, comiencen con un
        // sistema limpio (y no hereden dependencias, etc, de llamadas anteriores)
        DEPENDENCY::$bundles=null;
        return $contents;
    }

} 

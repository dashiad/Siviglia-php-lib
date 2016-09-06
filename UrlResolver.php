<?php
include_once(LIBPATH."/model/BaseException.php");
include_once(LIBPATH."/Request.php");
class  URLResolverException extends \lib\model\BaseException
{

    const ERR_PAGE_NOT_FOUND=1;
    const ERR_REQUIRED_PARAM=2;
    const ERR_CANT_FIND_ROUTE_DIR=3;
    const ERR_CANT_FIND_ROUTE_DEFINITION=4;
}

class URLResolver
{
    var $paths;
    var $request;
    /*
     * $routeSourcePath es la carpeta que contiene la especificacion de ruta=>nombre
     * $definitionSourcePath es la carpeta que contiene la especificacion nombre=>definition
     *
     * En routeSourcePath debe haber ficheros con clases dentro del namespace Routes\Urls,
     * que tienen una variable estatica $definition con definiciones de rutas.
     * Ejemplos de definiciones son:
     *
     * array(
     * "a/"=>"cero",
    "a"=>array(
        "b"=>"uno",
        "c"=>"dos",
        "d"=>array(
            "manufacturer"=>array(
                "list"=>"lista",
                "{id_manufacturer}"=>"verManufacturer"
            ),

            "e"=>"tres",
            "f"=>"cuatro",
            "g"=>array("h"=>"cinco"),
            "{parametro}"=>array("t"=>"seis","{segundoParametro}"=>"siete"),
            "?a={miparam}"=>array(
                "&b={miparam2}"=>"once"
            )
        )
    ),
    "n"=>array(
      "{*partPath}"=>array(
          "uno"=>"doce",
          "?h={param}"=>"trece"
      )
    ),
    "{*subpath}"=>"notfound"
);
    En el constructor, $routeSpec es un array de arrays.Cada uno de estos subArrays contiene las keys:
    'Urls', 'Definitions' y 'namespace', que es el namespace base

     */
    var $project;
    var $routeSpec;
    function __construct(\lib\project\Project $project,$cachePath)
    {
        $this->project=$project;
        if(!is_file($cachePath))
        {
            $this->routeSpec=$project->getUrlPaths();
            $this->regenerateCache($cachePath);
        }
        $def=unserialize(file_get_contents($cachePath));
        $this->regexp=$def["REGEX"];
        $this->paths=$def["PATHS"];
        $this->definitions=$def["DEFINITIONS"];
    }

    function regenerateCache($cachePath)
    {
        $routes=array();
        $regexes=array();
        $definitions=array();

        foreach($this->routeSpec as $key=>$val)
        {
            $routes=array_merge_recursive($routes,$this->loadDefinitions($val["Urls"],$val["namespace"],'urls'));
            $this->counter=0;
            $paths=$this->buildPaths($routes);
            for($k=0;$k<count($paths["REGEX"]);$k++)
                $regexes[]="~^".$paths["REGEX"][$k]."(?:\\?.*){0,1}(?:&.*){0,1}$~";
            $definitions=array_merge($definitions,$this->loadDefinitions($val["Definitions"],$val["namespace"],'definitions'));
            @mkdir(dirname($cachePath),0777,true);
        }
        file_put_contents($cachePath,serialize(array("DEFINITIONS"=>$definitions,"PATHS"=>$routes,"REGEX"=>$regexes)));
    }

    function resolve($path)
    {
        if(is_object($path))
        {
            if(is_a($path,"Request"))
            {
                $this->request=$path;
                $fullPath=$this->request->getOriginalRequest();
            }
        }
        else
            $fullPath=$path;

        $matches=array();
        // Si el path no es "/", quitamos la "/" inicial

        $fullPath=urldecode($fullPath);
        $n=count($this->regexp);
        for($k=0;$k<$n && !($res=preg_match($this->regexp[$k],$fullPath,$matches1,PREG_OFFSET_CAPTURE));$k++)
        ;
        if(!$res)
        {
            throw new URLResolverException(URLResolverException::ERR_PAGE_NOT_FOUND,array("route"=>$fullPath));
        }
        $matches=array();
        $urlParams=array();
        foreach($matches1 as $key=>$value)
        {
            if(($key[0]=="P" && $value[1]==-1) || ($key[0]!='X' && $key[0]!='P'))
                continue;
            $parts=explode("_",$key);
            $prf=substr($parts[0],1);
            unset($parts[0]);
            $cVal=implode("_",$parts);
            if($key[0]=='P')
                $linkName=$cVal;
            else
            {
                if($value[1]!=-1)
                    $urlParams[$cVal]=$value[0];
            }
        }


        if(!isset($this->definitions[$linkName]))
        {
            throw new URLResolverException(URLResolverException::ERR_CANT_FIND_ROUTE_DEFINITION,array("route"=>$fullPath,"name"=>$linkName));
        }
        // Se filtran los matches: Nos quedamos solo con las que empiezen por X, y se recortan sus nombres

        // Ahora, segun el perfil de la pagina, se ejecuta una cosa u otra.

        $this->resolveDefinition($this->definitions[$linkName],$urlParams);
    }

    function loadDefinitions($path,$baseNamespace,$classNamespace)
    {
        $definition=array();
            $dir=opendir($path);
            if(!$dir)
            {
                throw new URLResolverException(URLResolverException::ERR_CANT_FIND_ROUTE_DIR,array("route"=>$path));
            }

            while($file=readdir($dir))
            {
                if($file=="." || $file=="..")
                    continue;
                if(is_dir($path."/".$file))
                {
                    $defs=$this->loadDefinitions(array($path."/".$file),$baseNamespace,$classNamespace."\\".$file);
                    $definition=array_merge_recursive($definition,$defs);
                }
                else
                {
                    include_once($path."/".$file);
                    $className=$baseNamespace.'\\'.$classNamespace.'\\'.basename($file,".php");
                    $definition=array_merge_recursive($definition,$className::$definition);
                }
            }
        return $definition;
    }

    /*
     * Genera una url dando el nombre de la url, y una lista de parametros.
     */
    function generateUrl($name,$params)
    {
        $definitions=$this->definitions;
        if(isset($definitions[$name]))
        {
            $f=function($matches) use ($params)
            {
                if(is_array($params))
                {
                    if(isset($params[$matches[1]]))
                        return $params[$matches[1]];
                }
                else
                {
                    // Se supone un objeto
                    return $params->{$matches[1]};
                }
            };
            return preg_replace_callback("/{([^}]*)}/",$f,$definitions[$name]);
        }
    }
    var $counter=0;
    function buildPaths($paths,$inParam=0)
    {

        $regexes=array();
        $pathArray=array();
        foreach($paths as $key=>$value)
        {
            // If the current path has a LAYOUT defined, it's an entry point,
            // so its current path is stored.
            $checkingParam=false;
            // Se procesa la key, sustituyendo todo lo que hay entre { } por la expresion regular correspondiente.
            // El caracter delimitador depende de si estamos en query string o no
            // Como los parametros nombrados no pueden duplicarse, y, ademas, requieren que el primer caracter
            // no sea numerico, se le pone un prefijo "P"+curIndex.
            // Para ello, vamos a necesitar hacer un closure dentro de preg_replace_callback, que vaya
            // incrementando el curIndex.
            $f=function($matches) {
                $this->counter++;
                // Si comienza por "*" significa que va a hacer match desde ese elemento del path, hasta el final
                // es decir, mientras /a/{param}/b  hace match con /a/q/b , y param==q,
                // la ruta /a/{*param} hace match con /a/q/b , y param==q/b
                $paramName=$matches[1];
                $stopConditions="/&";
                if($matches[1][0]=="*")
                {
                    $paramName=substr($matches[1],1);
                    $stopConditions="?&";
                }
                return "(?P<X".$this->counter."_".$paramName.">[^".$stopConditions."]+)";
            };

            if($inParam==0 && strpos($key,"?")!==false)
                $inParam=1;

            $subRegex=str_replace(array("?","[","]"),array("\\?","\\[","\\]"),$key);

            $subRegex=preg_replace_callback("/{([^}]*)}/",$f,$subRegex);
            if($subRegex[0]!="/" && $inParam==0)
                $subRegex="/".$subRegex;

            // Si bajo esta clave hay un array, es que son subpaginas
            if(is_array($value))
            {
                $results=$this->buildPaths($value,$inParam);
                $childRegex=$results["REGEX"];
                $regexes[]=$subRegex."(?:(?:".implode(")|(?:",$childRegex)."))";
                $subpaths=array();
                $paths2=$results["PATHS"];
                foreach($paths2 as $key2=>$value2)
                {
                    $pathArray[$key2]=($inParam==0?"/":"").$key.$value2;
                }
            }
            else
            {
                // Es una clave final.Debe ser el nombre del link
                // Hay que poner un .* antes del nombre del link, para asegurarnos de que cualquier parametro GET
                // recibido, no acaba siendo considerado el nombre del link.
                // Ademas, si en la subRegex no hay ninguna "?", se la ponemos, para evitar que
                // si el path es /a/b/c, la regex sea al menos /a/b/c?... (con ?... opcional), para que
                // /a/b/chkjlkjl no haga match con /a/b/c

                // Si no es un array el final, se eliminan "/" espureas al final

                if($key!="/")
                    $subRegex=rtrim($subRegex,"/");
                if(strpos($subRegex,"?")===false)
                {
                    $subRegex="(?P<P".$this->counter."_".$value.">".$subRegex.")";
                }
                else
                {
                    $subRegex="(?P<P".$this->counter."_".$value.">".$subRegex.".*)";
                }
                $this->counter++;
                $pathArray[$value]=($inParam==0?"/":"").$key;
                $regexes[]=$subRegex;
            }
        }
        // Se hace implode de las regexes
        //if(count($regexes)>1)
        //{
        //    $r="(?:(?:".implode(")|(?:",$regexes)."))";
        //}
        //else
        //    $r=$regexes[0];

        return array("REGEX"=>$regexes,"PATHS"=>$pathArray);
    }
    /*******************************************************************************************
     *
     *            METODOS DE GESTION DE LOS DISTINTOS TIPOS DE DEFINICIONES
     *
     */
     function resolveDefinition($d,$params)
     {

         // Los parametros son lo que se ha encontrado que ha hecho match en la url.
         // Sobre estos, tienen prioridad aquellos que se fijan en la definicion.
         $definedParams=isset($d["PARAMS"])?$d["PARAMS"]:array();
         foreach($definedParams as $param=>$paramValue)
         {
             $params[$param] = $this->getValueFromRoute($paramValue);
         }
        $value=$d;
         switch($d["TYPE"]){
             default:

             case "REDIRECT":

                 $qsp=array();
                 // Se reconstruye la query string
                 foreach($_GET as $key2=>$value2)
                 {
                     if($key2=="request")
                         continue;
                     $qsp[]=$key2."=".urlencode($value2);
                 }
                 foreach($params as $key2=>$value2)
                     $qsp[]=$key2."=".urlencode($value2);


                 $url=$value["URL"];
                 if(count($params)>0)
                 {
                     if(strpos($url,"?")===false)
                     {
                         $url.="?";
                     }
                     $url.="&".implode("&",$qsp);
                 }
                 header("Location: ".$url);

                 break;
             case "PAGE":
                 $this->resolveFrameworkPage($d,$params);
                 break;
             case "METHOD":

                 $this->$value["METHOD"]($params);
                 break;
             case "DATASOURCE":
                 $definition=$this->urlToObject($_GET["request"]);
                 if($value["OBJECT"])
                     $definition["OBJECT"]=$value["OBJECT"];
                 if($value["NAME"])
                     $definition["NAME"]=$value["NAME"];

                 $ds=new \lib\output\json\JsonDataSource($definition["OBJECT"],$definition["NAME"],null,$definition["ROLE"]);

                 if (isset($value["FILTERING_DATASOURCES"])) {
                     $fdDefinition=array();
                     foreach($value["FILTERING_DATASOURCES"] as $fdd=>$fdv) {
                         $fdDefinition[$fdd]=$fdv;
                     }
                     $ds->setFilteringDatasources($fdDefinition);
                 }

                 // Se deben aniadir los parametros que hayan llegado por $_GET, y que no sean
                 // request, output o rnd:
                 foreach($_GET as $getKey=>$getValue)
                 {
                     if(!in_array($getKey,array("request","output","rnd")))
                         $params[$getKey]=$getValue;
                 }

                 $ds->setParameters($params);
                 echo $ds->execute();

                 break;
             case "ACTION":
                 global $request;
                 foreach($params as $param=>$paramValue)
                     $request->actionData[$param] =$paramValue;
                 $action=\lib\output\json\JsonAction::fromPost();
                 echo $action->execute();
                 break;
         }
     }

    function resolveFrameworkPage($d,$params)
    {
        // La definition de una FrameworkPage tiene que tener como datos, el objeto y el nombre de vista.
        // se hace una primera sustitucion de variables $_GET sobre la url recibida.De esta forma.
        // las variables que deben aparecer in-url, se permite que aparezcan en $_GET.
        global $currentPage;
        $instance=$this->getPageInstance($d["PAGE"],$params,$this->request);
        $currentPage=$instance;
        \Registry::store("currentPage",$instance);
        $instance->render($this->request->getOutputType(),$this->request->getOriginalRequest(),isset($d["OUTPUT_PARAMS"])?$d["OUTPUT_PARAMS"]:array());

    }
    function getPageInstance($page,$params,$request)
    {
        return $this->project->getPage($page,$params);
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 10/07/15
 * Time: 0:18
 */

namespace lib\reflection\html;


class ModelUrlPath extends \lib\reflection\base\BaseDefinition
{
    var $paths;
    var $className;
    var $namespace;
    var $filePath;
    var $routeNamespace;
    function __construct($model,$routeNamespace)
    {
        $objName=$model->objectName;
        $this->routeNamespace=$routeNamespace;
        $namespaced=$objName->getParentNamespace();
        $this->className=$objName->getClassName();
        $this->namespace='web\Routes\\'.$this->routeNamespace.$namespaced;
        $this->filePath=PROJECTPATH."/html/routes/".$routeNamespace."/".str_replace("\\","/",$namespaced)."/".$this->className.".php";
    }
    function load()
    {
        if(!is_file($this->filePath))
        {
            $this->paths=array();
            return;
        }
        include_once($this->filePath);
        $cName="\\".$this->namespace."\\".$this->className;
        $this->paths=$cName::$definition;
    }
    function setDefinition($def)
    {
        $this->paths=$def;
    }

    function save()
    {
        $code="<?php\n  namespace ".$this->namespace.";\n   class ".$this->className."\n  {\n";
        $code.="\t\tstatic \$definition=".$this->dumpArray($this->paths).";\n}\n";
        @mkdir(dirname($this->filePath),0777,true);
        file_put_contents($this->filePath,$code);
    }
    static function pathToNested($path,$uid)
    {
        $pathTree=$path;
        $parts=explode("/",$pathTree);
        if($parts[0]=="")
        {
            array_shift($parts);
            $parts=array_values($parts);
        }
        $nParts=count($parts);
        $n=array($parts[$nParts-1]=>$uid);

        for($k=$nParts-2;$k>=0;$k--)
        {
            $n=array($parts[$k]=>$n);
        }
        return $n;

    }
    static function addUrl($url,$model,$pageName,$pageCode=null)
    {
        if($pageCode==null)
            $pageCode=uniqid();
        // Se crea primero la ruta
        $mInstance=\lib\reflection\ReflectorFactory::getModel($model);


        $route=new ModelUrlPath($mInstance,"Urls");
        $route->load();
        $nested=ModelUrlPath::pathToNested($url,$pageCode);
        $route->paths=array_merge_recursive($route->paths,$nested);
        $route->save();

        $definition=new ModelUrlPath($mInstance,"Definitions");
        $definition->load();
        $definition->paths[$pageCode]=array("OBJECT"=>$model,"PAGE"=>$pageName);
        $definition->save();
        ModelUrlPath::clearRouteCache();
        // Se borra la cache de rutas.
    }
    static function clearRouteCache()
    {
        @unlink(PROJECTPATH."/cache/routes.srl");
    }
} 
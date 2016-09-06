<?php
class BaseMetadata
{
    var $definition;
    function __construct($basePath, $namespaced, $variableName, $isStatic,$fieldResolution, $fieldHiding,$removeKeys,$definition=null)
    {
        $fileName=realpath($basePath);
        if(strstr($basePath,PROJECTPATH)===false || !is_file($basePath))
        {
            echo "{}";exit();
        }
        if($definition==null)
        {
            include_once($basePath);

            if($isStatic)
            {
                $inst = new $namespaced();
                $definition=$inst::$$variableName;
            }
            else
            {
                $inst=new $namespaced();
                $definition=$inst->getDefinition();
            }
        }

                $fDef=$definition["FIELDS"];
                foreach($fDef as $key=>$value)
                {
                    $definition["FIELDS"][$key]=array_merge($value,\lib\model\types\TypeFactory::getTypeMeta($value));
                }


        if($fieldHiding)
        {
            foreach($fieldHiding as $value)
            {
                foreach($definition[$value] as $kk=>$vv)
                {
                    if($vv["PUBLIC_FIELD"]==false)
                        unset($definition[$value][$kk]);
                }
            }
        }

        if($removeKeys)
        {
            foreach($removeKeys as $value)
            {
                $start = & $definition;
                $parts=explode("/",$value);
                for($k=0;$k<count($parts)-1;$k++)
                    $start=& $start[$parts[$k]];
                unset($start[$parts[$k]]);
            }
        }
        $this->definition=$definition;
    }

}

class ModelMetaData extends BaseMetadata {
    function __construct($objName)
    {
        $Obj=new \lib\reflection\model\ObjectDefinition($objName);
        $path=$Obj->getDestinationFile("Definition.php");
        parent::__construct($path, $Obj->getNamespaced().'\Definition', "definition", true ,array(), false,array('STORAGE'));
        $this->definition["layer"]=$Obj->layer;
        $this->definition["parentObject"]=null;
        $this->definition["name"]=$Obj->className;
        if($Obj->isPrivate())
        {
            $this->definition["private"]=1;
            $this->definition["parentObject"]=$Obj->getNamespaceModel();

        }
        if($this->definition["EXTENDS"])
        {
            $parentMeta=new ModelMetaData($this->definition["EXTENDS"]);
            $parentFields=$parentMeta->definition["FIELDS"];
            unset($this->definition["FIELDS"][$this->definition["INDEXFIELDS"][0]]);
            $this->definition["FIELDS"]=array_merge($this->definition["FIELDS"],$parentFields);

            $this->definition["ALIASES"]=array_merge($this->definition["ALIASES"]?$this->definition["ALIASES"]:array(),
                $parentMeta->definition["ALIASES"]?$parentMeta->definition["ALIASES"]:array());
        }
    }
}

class DataSourceMetaData extends BaseMetadata {
    function __construct($objName,$dsName)
    {
        include_once(LIBPATH."/datasource/DataSourceFactory.php");
        $ds=\lib\datasource\DataSourceFactory::getDataSource($objName,$dsName);
        $this->definition=$ds->getOriginalDefinition();
        // Es un datasource "normal", no multiple
        if(isset($this->definition["FIELDS"]))
        {
            foreach($this->definition["FIELDS"] as $key=>$value)
            {
                $info=$value;
                $type=\lib\model\types\TypeFactory::getType(null,$info);
                $this->definition["FIELDS"][$key]=\lib\model\types\TypeFactory::getTypeMeta(array_merge($info,$type->definition));
            }
            unset($this->definition["STORAGE"]);
            unset($this->definition["PERMISSIONS"]);
        }
        if(isset($this->definition["PARAMS"]))
        {
            foreach($this->definition["PARAMS"] as $key=>$value)
            {
                $info=$value;
                $type=\lib\model\types\TypeFactory::getType(null,$info);
                $this->definition["PARAMS"][$key]=\lib\model\types\TypeFactory::getTypeMeta(array_merge($info,$type->definition));
            }
            unset($this->definition["STORAGE"]);
            unset($this->definition["PERMISSIONS"]);
        }
        if(isset($this->definition["DATASOURCES"]))
        {
            // Es un datasource multiple.Obtenemos las definiciones de cada uno
            // de los datasources internos.
            foreach($this->definition["DATASOURCES"] as $key=>$value)
            {
                $newDef=new DataSourceMetaData($value["OBJECT"],$value["DATASOURCE"]);
                $this->definition["DATASOURCES"][$key]=$newDef->definition;
            }
        }
    }
}

class FormMetaData extends BaseMetadata {
    function __construct($objName,$formName)
    {

        $Obj=new \lib\reflection\model\ObjectDefinition($objName);
        $basePath=$Obj->getDestinationFile("/html/forms/$formName.php");
        $fileName=realpath($basePath);
        if(strstr($basePath,PROJECTPATH)===false || !is_file($basePath))
        {
            echo "{}";exit();
        }

        include_once($basePath);
        $className=$Obj->getNamespaced().'\html\forms\\'.$formName;
        $inst=new $className();

        $definition=$inst->getDefinition();
        if(isset($definition["INHERIT"]))
        {
            $actName=$definition["INHERIT"]["ACTION"];
            include_once($Obj->getActionFileName($actName));
            $actName=$Obj->getNamespacedAction($actName);
            $actInstance=new $actName();
            $actDef=$actInstance->getDefinition();
            $definition["FIELDS"]=$actDef["FIELDS"];
        }

        $fDef=$definition["FIELDS"];
        foreach($fDef as $key=>$value)
        {
            $definition["FIELDS"][$key]=array_merge($value,\lib\model\types\TypeFactory::getTypeMeta($value));
        }
        $this->definition=$definition;

    }
}
?>
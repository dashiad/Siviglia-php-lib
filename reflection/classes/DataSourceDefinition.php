<?php
namespace lib\reflection\classes;
class DataSourceDefinition extends ClassFileGenerator
{
    var $serializersDefinition=array();
    var $serializer;
    var $name;
    var $role="list";
    var $haveIndexes;
    var $modelIndexes;
    var $isadmin;

    function __construct($parentModel,$name,$definition)
    {
        $this->parentModel=$parentModel;
        $this->definition=$definition;
        $this->name=$name;
        if($this->definition["ROLE"])
            $this->role=$this->definition["ROLE"];
        $this->label=$definition["LABEL"];
        if(!$this->label)
            $this->label=$this->name;
        $this->isadmin=$definition["IS_ADMIN"];
        ClassFileGenerator::__construct($name,$parentModel->objectName->layer,
        $parentModel->objectName->getNamespace().'\datasources',
        $parentModel->objectName->getPath().'/datasources/'.$this->name.'.php');        
        
        $this->metadata=array();
        if($definition["PARAMS"] && $definition["PARAMS"]["FIELDS"])
        {
            foreach($definition["PARAMS"]["FIELDS"] as $key=>$value)
                $this->fields[$key]=new DataSourceParameterDefinition($key,$value);
        }
        else
            $this->fields=array();
        
      
        

        $this->serializer=\lib\storage\StorageFactory::getSerializerByName($this->parentModel->objectName->layer);
        
        // Se intenta cargar, si existe, la definicion de este datasource, segun su serializador.
        $this->loadSerializerDefinition();
          
        if($definition["METADATA"])
        {
            foreach($definition["METADATA"] as $key=>$value)
                $this->metadata[$key]=\lib\reflection\classes\types\TypeReflectionFactory::getReflectionType($value);
        }        
        $indexFields=$this->parentModel->getIndexFields();
        $keys=array_keys($indexFields);
        $meta=array_keys($this->metadata);
        if(count($keys)==count(array_intersect($keys,$meta)))
        {
                $this->haveIndexes=true;
                $this->modelIndexes=$keys;
        }
        
        
        $this->permissions=new PermissionRequirementsDefinition($definition["PERMISSIONS"]?$definition["PERMISSIONS"]:"_PUBLIC_");

        $this->includes=array();
        if($definition["INCLUDES"])
        {
            foreach($definition["INCLUDES"] as $key=>$value)
            {
                $this->includes[$key]=new DataSourceIncludeDefinition($value);
            }
        }
        
    }
    function getRole()
    {
        $def=$this->getDefinition();
        $role=$def["ROLE"];
        if(!$role)
            return "view";
        return $role;
    }
    function loadSerializerDefinition()
    {
        $serType=$this->serializer->getSerializerType();
        $className='\\'.$this->parentModel->objectName->layer.'\\'.$this->parentModel->objectName->className.'\datasources\\'.$serType.'\\'.$this->name;
        
        if(class_exists($className))
        {                        
            // Existe una definicion del serializer.Se carga.
            $serDsObj=new $className($this->parentModel->objectName->className,
                                     $this->name,
                                     $this->definition,
                                     $this->serializer);
            
            $serDef=$serDsObj->serializerDefinition;
            // La clase que gestiona la definicion de datasources segun serializers, tienen el siguiente formato:
            
            $serDsClass='\lib\reflection\classes\\'.ucfirst(strtolower($serType))."DsDefinition";
            
            $this->serializerDefinition[$serType]=new $serDsClass($this->parentModel,$this->name,$this,$serDef);
            
            // Si no habia metadata, se intenta descubrir a partir de la query MYSQL.
            
                $this->metadata=$this->discoverFields();
                
                $this->definition["METADATA"]=$this->metadata;
                
            
        }
        

    }
    function getListCodePath()
    {
        return "/".$this->parentModel->objectName->layer."/objects/".$this->parentModel->objectName->className."/html/".$this->role."/".$this->name;
    }

    function discoverFields()
    {
        $serType=$this->serializer->getSerializerType();
        if($this->serializerDefinition[$serType])
        {            
            return $this->serializerDefinition[$serType]->discoverFields();
        }

    }
    function getSerializerDefinition($type)
    {
        return $this->serializerDefinition[$type];
    }

    static function createDefaultDataSources($parentModel)
    {
        $fields=$parentModel->fields;

        $fullFields=array();
        $descriptiveFields=array();
        $relationFields=array();
        $labelFields=array();
        $layer=$parentModel->objectName->layer;
        $objName=$parentModel->objectName->className;
        
        foreach($fields as $key=>$value)
        {
            if($value->isRelation())
            {
                $relationFields[$key]=$value;
            }
            else
            {
                $fullFields[$key]=$value;
            }
            if($value->isDescriptive())
            {
                $descriptiveFields[$key]=$value;
            }
            if($value->isLabel())
            {
                $labelFields[$key]=$value;
            }
        }
        if(count($descriptiveFields)==0)
            $descriptiveFields=$fullFields;

        $indexFields=$parentModel->getIndexFields();
        $defaultDs=array(array("FullList","View","AdminFullList","AdminView"),
                         array($descriptiveFields,$fullFields,$descriptiveFields,$fullFields),
                         array(array("_PUBLIC_"),array("_PUBLIC_"),array(array("MODEL"=>$objName,"PERMISSION"=>"adminList")),array(array("MODEL"=>$objName,"PERMISSION"=>"adminView"))),
                         array(array(),$indexFields,array(),$indexFields),
                         array($descriptiveFields,array(),$descriptiveFields,array()),
                         array("list","view","list","view"),
                         array(false,false,true,true)
                         );

        $ownershipField=$parentModel->getOwnershipField();
        if($ownershipField)
        {
            $ownerField=array($ownershipField=>$parentModel->fields[$ownershipField]);

            $defaultDs[0][]="ListOwn";
            $defaultDs[1][]=$descriptiveFields;
            $defaultDs[2][]=array("_OWNER_");
            $defaultDs[3][]=$ownerField;
            $defaultDs[4][]=$descriptiveFields;
            $defaultDs[5][]="list";
            $defaultDs[6][]=false;
            
            $defaultDs[0][]="ViewOwn";
            $defaultDs[1][]=$fullFields;
            $defaultDs[2][]=array("_OWNER_");
            $defaultDs[3][]=array_merge($indexFields,$ownerField);
            $defaultDs[4][]=array();
            $defaultDs[5][]="view";
            $defaultDs[6][]=false;
        }

        for($k=0;$k<count($defaultDs[0]);$k++)
        {
            $name=$defaultDs[0][$k];
            $fields=$defaultDs[1][$k];
            $permissions=$defaultDs[2][$k];
            $curIndexes=$defaultDs[3][$k];
            $filterFields=$defaultDs[4][$k];
            if(!$parentModel->config->mustRebuild("datasource",$name,$parentModel->objectName->getPath().'/datasources/'.$name.'.php'))
            {
                continue;
            }


            $dsDefinition=array(
                                "ROLE"=>$defaultDs[5][$k],
                                "DATAFORMAT"=>"Table",
                                "PARAMS"=>array()
                                );
            if($defaultDs[6][$k])
            {
                
                $dsDefinition["IS_ADMIN"]=true;
            }
            foreach($curIndexes as $key=>$value)      
            {
                $paramType=\lib\reflection\classes\types\ModelReferenceType::create($parentModel->objectName->className,$key);
                $paramField=DataSourceParameterDefinition::create($key,$paramType->getDefinition(),null);
                $dsDefinition["PARAMS"]["FIELDS"][$key]=$paramField->getDefinition();
            }

            foreach($filterFields as $key=>$value)
            {
                // Hay que descartar los campos que ya hayan sido incluidos como keys
                if($dsDefinition["PARAMS"]["FIELDS"][$key])
                    continue;
                $paramType=\lib\reflection\classes\types\ModelReferenceType::create($parentModel->objectName->className,$key);
                $paramField=DataSourceParameterDefinition::create($key,$paramType->getDefinition(),$key);
                $dsDefinition["PARAMS"]["FIELDS"][$key]=$paramField->getDefinition();
            }

            foreach($fields as $key=>$value)
            {
                $paramType=\lib\reflection\classes\types\ModelReferenceType::create($parentModel->objectName->className,$key);
                $dsDefinition["METADATA"][$key]=$paramType->getDefinition();
                $dsDefinition["METADATA"][$key]["LABEL"]=$key;
            }

            $usedNames=array();
            foreach($relationFields as $key=>$value)
            {
                // Por cada relacion, se crea un subDs
                $targetObj=$value->getTargetObject();
                $j=0;
                $subDsName=$targetObj;
                while($usedNames[$subDsName.$j])$j++;
                    $usedNames[$subDsName.$j]=1;
                $includeName=$j?$subDsName.$j:$subDsName;
                $instance=\lib\reflection\classes\DataSourceIncludeDefinition::create($key,$value,"List","LEFT");  
                $dsDefinition["INCLUDES"][$includeName]=$instance->getDefinition();
                
            }
            $perms=\lib\reflection\classes\PermissionRequirementsDefinition::create($permissions);
            $dsDefinition["PERMISSIONS"]=$perms->getDefinition();

            $datasources[$name]=new DataSourceDefinition($parentModel,$name,$dsDefinition);
                        
        }
        return $datasources;
    }
    function isAdmin()
    {
        return $this->isadmin;
    }
    function getLabel()
    {
        return $this->label;
    }
    function getName()
    {
        return $this->name;
    }
    function addSerializerDefinition($defObj,$serName)
    {
        $this->serializersDefinition[$serName]=$defObj;
    }
    function getDefinition()
    {
        
        $def=array(
            "LABEL"=>$this->label,
            "DATAFORMAT"=>($this->definition["DATAFORMAT"]?$this->definition["DATAFORMAT"]:"Table"),
            "ROLE"=>$this->role,
            "IS_ADMIN"=>$this->isadmin?"true":"false"
            
            );
        
        foreach($this->fields as $key=>$value)
            $def["PARAMS"]["FIELDS"][$key]=$value->getDefinition();
        if($this->metadata)
        {
            foreach($this->metadata as $key=>$value)
            {
                if(is_object($value))
                {
                    $def["METADATA"][$key]=$value->getDefinition();
                }
                else
                    $def["METADATA"][$key]=$value;
            }
        }

        $def["PERMISSIONS"]=$this->permissions->getDefinition();

        foreach($this->includes as $key=>$value)
        {
            $def["INCLUDES"][$key]=$value->getDefinition();
        }
        return $def;   
    }

    function save($dsName)
    {
        if($this->parentModel->config->mustRebuild("datasource",$this->name,$this->filePath))
        {
        $this->addProperty(array("NAME"=>"definition",
                                 "ACCESS"=>"static",
                                 "DEFAULT"=>$this->getDefinition()));
        $this->generate();
        }
    }
    function generateCode()
    {
        switch($this->role)
        {
            case "list":
            {
                $this->generateListCode("user");
                $this->generateListCode("admin");
            }break;
            case "view":
            {
                $this->generateViewCode("user");
                $this->generateViewCode("admin");
            }
        }
            
    }
    
    function generateListCode($dstype="user")
    {
        if($dstype=="user")
        $codePath=$this->parentModel->objectName->getPath()."/html/".$this->role."/".$this->name.".wid";
        else
            $codePath=$this->parentModel->objectName->getPath()."/html/admin".$this->role."/".$this->name.".wid";
     
        if(!$this->parentModel->config->mustRebuild("datasourceTemplate",$this->name,
            $codePath
        ))
            return;
            
        $phpCode=<<<'TEMPLATE'
            
            global $SERIALIZERS;
            $params=Registry::$registry["PAGE"];
            $serializer=\lib\storage\StorageFactory::getSerializerByName('{%layer%}');;
            $serializer->useDataSpace($SERIALIZERS["{%layer%}"]["ADDRESS"]["database"]["NAME"]);
TEMPLATE;

        $widgetCode= <<<'WIDGET'
            [*LIST_DS({"currentPage":"$currentPage","object":"{%object%}","dsName":"{%dsName%}","serializer":"$serializer","params":"$params","iterator":"&$iterator"})]               
                [_HEADER]
                    [_TITLE]Titulo de la lista[#]
                    [_DESCRIPTION]Descripcion de la lista[#]
                [#]
                [_LISTING]
                    [_COLUMNHEADERS]
{%headers%}        
                    [#]
                    [_ROWS]
{%columns%}
                    [#]
                    [_LISTINGFOOTER]
                    [#]
                [#]     
           [#]
WIDGET;
        
        // Se buscan todos los objetos que tenemos en metadata.
        $def=$this->getDefinition();
        $widgetHelper=new \lib\reflection\classes\WidgetHelper();
        $metadata=$def["METADATA"];
        if(!$metadata)
            $metadata=$def["PARAMS"]["FIELDS"];
        foreach($metadata as $fName=>$fDef)
        {            
            $type=\lib\model\types\TypeFactory::getType($fDef);
            $typeClass=get_class($type);
            $pos=strrpos($typeClass,"\\");
            $className=substr($typeClass,$pos+1);

            $headerCad.="\t\t\t\t\t\t[_COLUMN][_LABEL]".($typeDef["LABEL"]?$typeDef["LABEL"]:$fName)."[#][#]\n";
            $columnCad.="\t\t\t\t\t\t[_ROW][_VALUE][*:/types/".$className."({\"name\":\"".$fName."\",\"model\":\"\$iterator\"})][#][#][#]\n";

        }
        if($dstype=="admin" && $this->haveIndexes)
        {
            $headerCad.="\t\t\t\t\t\t[_COLUMN][_LABEL][*/icons/delete][#][#][#]\n";
            $columnCad.="\t\t\t\t\t\t[_ROW][_VALUE][*/list/icons/delete({\"model\":\"\$iterator\",\"indexes\":[\"".implode("\",\"",$this->modelIndexes)."\"]})][#][#][#]\n";
        }
        $searchs=array("{%layer%}","{%object%}","{%dsName%}","{%headers%}","{%columns%}");
        
        $replaces=array($this->parentModel->objectName->layer,
                        $this->parentModel->objectName->className,
                        $this->name,
                        $headerCad,
                        $columnCad
                        );
        $code=str_replace($searchs,$replaces,"<?php\n".$phpCode."\n?>\n".$widgetCode."\n");

        @mkdir(dirname($codePath),0777,true);
        file_put_contents($codePath,$code);

    }
    
    function generateViewCode($dstype="user")
    {
        if($dstype=="user")
            $codePath=$this->parentModel->objectName->getPath()."/html/".$this->role."/".$this->name.".wid";
        else
            $codePath=$this->parentModel->objectName->getPath()."/html/admin".$this->role."/".$this->name.".wid";
     
        if(!$this->parentModel->config->mustRebuild("datasourceTemplate",$this->name,
            $codePath
        ))
            return;
        
        $descriptiveFields=$this->parentModel->getDescriptiveFields();
        $dkeys=array_keys($descriptiveFields);
        $mainLabel=$descriptiveFields[$dKeys[0]]->getLabel();
            
        $phpCode=<<<'TEMPLATE'
            
            global $SERIALIZERS;
            $params=Registry::$registry["PAGE"];
            $serializer=\lib\storage\StorageFactory::getSerializerByName('{%layer%}');
            $serializer->useDataSpace($SERIALIZERS["{%layer%}"]["ADDRESS"]["database"]["NAME"]);
TEMPLATE;

        $widgetCode= <<<'WIDGET'
            [*/VIEWS/OBJECT_VIEW({"currentPage":"$currentPage","object":"{%object%}","dsName":"{%dsName%}","serializer":"$serializer","params":"$params","iterator":"&$iterator"})]                
                    [_TITLE]{%title%}[#]
                    [_CONTENTS]
                        [*/LAYOUTS/2Columns]
                           {%contents%} 
                        [#]
                    [#]    
           [#]
WIDGET;
        
        // Se buscan todos los objetos que tenemos en metadata.
        $def=$this->getDefinition();
        $widgetHelper=new \lib\reflection\classes\WidgetHelper();
        $metadata=$def["METADATA"];
        if(!$metadata)
            $metadata=$def["PARAMS"]["FIELDS"];
        foreach($metadata as $fName=>$fDef)
        {            
            $type=\lib\model\types\TypeFactory::getType($fDef);
            $typeClass=get_class($type);
            $pos=strrpos($typeClass,"\\");
            $className=substr($typeClass,$pos+1);

            $contents.="\t\t\t\t\t\t[_LEFT]".($typeDef["LABEL"]?$typeDef["LABEL"]:$fName)."[#]\n"
                        ."\t\t\t\t\t\t[_RIGHT][*:/types/".$className."({\"name\":\"".$fName."\",\"model\":\"\$iterator\"})][#][#]\n";
        }
        
        $searchs=array("{%layer%}","{%object%}","{%dsName%}","{%contents%}","{%title%}");
        $replaces=array($this->parentModel->objectName->layer,
                        $this->parentModel->objectName->className,
                        $this->name,
                        $contents,
                        $mainLabel
                        );
        $code=str_replace($searchs,$replaces,"<?php\n".$phpCode."\n?>\n".$widgetCode."\n");
        
        @mkdir(dirname($codePath),0777,true);
        file_put_contents($codePath,$code);
    }

    

}
?>

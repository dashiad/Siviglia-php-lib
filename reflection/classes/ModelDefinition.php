<?php
namespace lib\reflection\classes;
class ModelDefinition extends ClassFileGenerator
{
        var $objectName;
        var $fields=array();
        var $aliases=array();
        var $actions=array();
        var $datasources=array();
        var $layer;
        var $hasDefinition=false;
        var $baseDir;
        var $extendedModel="";
        var $config;
        function __construct($objectName,$layer=null)
        {

                $this->objectName=new ObjectDefinition($objectName);
                $this->layer=$this->objectName->layer;
                
                try{
                        $this->baseDir=$this->objectName->getDestinationFile();
                        $this->definition=\lib\model\types\TypeFactory::getObjectDefinition($objectName);
                        $this->indexFields=$this->definition["INDEXFIELD"];
                        $this->loadFields();
                        $this->loadAliases();
                        
                                              
                        $this->loadModelPermissions();
                        $this->hasDefinition=true;
                        if($this->definition["EXTENDS"])
                            $this->extendedModel=$this->definition["EXTENDS"];
                        
                        
                        $this->acls=$this->definition["DEFAULT_PERMISSIONS"];
                                                                        
                        if($this->definition["SERIALIZER"])
                            $this->serializer=\lib\reflection\classes\serializers\SerializerReflectionFactory::getReflectionSerializer($this->definition["SERIALIZER"]);
                        else
                            $this->serializer=null;
                        
                        ClassFileGenerator::__construct("Definition",
                                               $this->layer,
                                               $this->layer.'\\'.$this->objectName->className,
                                               PROJECTPATH."/".$this->layer."/objects/".$this->objectName->className."/Definition.php");
                   }catch(\lib\model\types\BaseTypeException $e)
                   {                       
                       $this->hasDefinition=false;
                   }
                   $this->config=new \lib\reflection\classes\ModelConfiguration($this);
                   
        }
        function hasDefinition()
        {
            return $this->hasDefinition;
        }
        function hasCustomSerializer()
        {
            return $this->serializer!=null;
        }
        function getCustomSerializer()
        {
             return $this->serializer;
        }
        function getLabel()
        {
            $def=$this->getDefinition();
            return $def["LABEL"];
        }
        function getShortLabel()
        {
            $def=$this->getDefinition();
            return $def["SHORTLABEL"];
        }
        function getExtendedModel()
        {
            return $this->extendedModel;
        }
        function getRelations()
        {
            $rels=array();
            foreach($this->fields as $key=>$value)
            {
                if($value->isRelation())
                    $rels[$key]=$value;
            }
            foreach($this->aliases as $key=>$value)
            {
                if($value->getType()->isRelation())
                    $rels[$key]=$value->getType();

            }
            return $rels;
        }
		function getTableName()
		{
			if($this->definition["TABLE"])
				return $this->definition["TABLE"];
			return $this->objectName->className;
		}
        
        function getOwnershipField()
        {           
            return $this->definition["OWNERSHIP"];
        }
        function loadFields()
        {
            $this->fields=array();
            foreach($this->definition["FIELDS"] as $key=>$value)
            {
                if(\lib\reflection\classes\FieldDefinition::isRelation($value))
                    $this->fields[$key]=new \lib\reflection\classes\RelationshipDefinition($key,$this,$value);
                else
                    $this->fields[$key]=new \lib\reflection\classes\FieldDefinition($key,$this,$value);           
            }
        }
        function loadAliases()
        {
            $this->aliases=array();
            if(!$this->definition["ALIASES"])return;
            foreach($this->definition["ALIASES"] as $key=>$value)
            {
                
                $this->aliases[$key]=new \lib\reflection\classes\AliasDefinition($this,$value);
                
            }
        }
        function getAliases()
        {
            return $this->aliases;
        }

        function addAlias($name,$alias)
        {            
            $this->aliases[$name]=$alias;

        }

         function createFromQuick($quickDef)
        {
            $objectName=$this->objectName;
            $layer=$this->layer;
            $this->table=$objectName;
            


            // Se crea automaticamente un campo id
            $fieldIndex="id_".strtolower($objectName);

            $this->indexFields=array($fieldIndex);
            $this->fields[$fieldIndex]=\lib\reflection\classes\FieldDefinition::createField($fieldIndex,$this,array("TYPE"=>"UUID"));
            
            for($k=0;$k<count($quickDef);$k++)
            {
                $isDigit=0;
                $fName=$quickDef[$k];
                if(strpos($fName,"@"))
                {
                    $parts=explode("@",$fName);
                    $relFields=explode(",",trim($parts[1]));
                    $targetObj=substr($parts[0],1);
                    $this->fields[$targetObj]=\lib\reflection\classes\RelationshipDefinition::createRelation($targetObj,$this,$targetObj,$relFields);
                    continue;
                }
                if($quickDef[$k][0]=='#')
                    $this->fields[substr($fName,1)]=\lib\reflection\classes\FieldDefinition::createField(substr($fName,1),$this,array("TYPE"=>"Integer"));
                else
                    $this->fields[$fName]=\lib\reflection\classes\FieldDefinition::createField($fName,$this,array("TYPE"=>"String","MAXLENGTH"=>45));
            }
            $this->baseDir=PROJECTPATH."/".$layer."/objects/".$objectName;
                        
            $this->acls=ModelPermissionsDefinition::getDefaultAcls($objectName,$layer);
            
            // Setup de permisos, con un array vacio.
            $this->modelPermissions=new \lib\reflection\classes\ModelPermissionsDefinition($this,array());
             ClassFileGenerator::__construct("Definition",
                                               $this->layer,
                                               $this->layer.'\objects\\'.$this->objectName->className,
                                               PROJECTPATH."/".$this->layer."/objects/".$this->objectName->className."/Definition.php");
        }
        /**
         *  Function : getDefinition()
         */

        function getDefinition()
        {
            $def=array();
            if($this->extendedModel)
                $def["EXTENDS"]=$this->extendedModel;
            if($this->indexFields)
                $def["INDEXFIELD"]=$this->indexFields;

             $firstStringField="";
             foreach($this->fields as $key=>$value)
             {
                $def["FIELDS"][$key]=$value->getDefinition();
                if($firstStringField=="" && $def["FIELDS"][$key]["TYPE"]=='String')
                    $firstStringField=$key;
             }
             foreach($this->aliases as $key=>$value)
             {
                 $def["ALIASES"][$key]=$value->getDefinition();
             }
             if($this->serializer)
                 $def["SERIALIZER"]=$this->serializer->getDefinition();

             
             if($this->modelPermissions)
                 $def["PERMISSIONS"]=$this->modelPermissions->getDefinition();

             $owner=$this->getOwnershipField();

             if($owner)
             {                 
                     $def["OWNERSHIP"]=$owner;
             }

             if($this->acls)
             {
                 $def["DEFAULT_PERMISSIONS"]=$this->acls;
             }
             if($this->definition["STATES"])
                 $def["STATES"]=$this->definition["STATES"];
             if($this->definition["TABLE"])
                 $def["TABLE"]=$this->definition["TABLE"];
             else
                 $def["TABLE"]=$this->objectName->className;

             if($this->definition["LABEL"])
                 $def["LABEL"]=$this->definition["LABEL"];
             else
             {
                 
                 $def["LABEL"]=$this->objectName->className;
             }
             if($this->definition["SHORTLABEL"])
                 $def["SHORTLABEL"]=$this->definition["SHORTLABEL"];
             else                              
                 $def["SHORTLABEL"]=$this->objectName->className;
             
             return $def;
        }
        
        function addAction($name,$actionObj)
        {
            $this->actions[$name]=$actionObj;
        }
        
        function save($all=true)
        {
            $this->saveDefinition();
            
            if($all)
            {
                foreach($this->actions as $key=>$value)
                     $value->save($key);
                foreach($this->datasources as $key=>$value)
                    $value->save($key);
            }
        }

        function saveDefinition()
        {
            $this->addProperty(array("NAME"=>"definition",
                                     "ACCESS"=>"static",
                                     "DEFAULT"=>$this->getDefinition()));
            $this->generate();
        }


        function addDataSource($name,$dataSource)
        {
            $this->datasources[$name]=$dataSource;
        }

        function getSerializer()
        {
            if($this->hasCustomSerializer())
                {
                    $serDef=$this->getCustomSerializer();
                    $ser=\lib\storage\StorageFactory::getSerializer($serDef);
                    return $ser;
                    // TODO : Hacer que utilice su custom namespace
                    $curSerializer->useDataSpace($serDef["database"]);
                    
                }
                else
                {
                    $ser=\lib\storage\StorageFactory::getSerializerByName($this->objectName->layer);
                    return $ser;
                }
        }


        function getDataSources()
        {
            return $this->datasources;
        }


        function loadDataSources()
        {
            
            $dsDir=$this->baseDir."/datasources/";
            if(!is_dir($dsDir))
                return;

            $fileIt=opendir($dsDir);

            while($fileName=readdir($fileIt))
            {
                
                if($fileName=="." || $fileName==".." || is_dir($dsDir.$fileName))
                    continue;
                
                $viewName=basename($fileName,".php");
                
                include_once($dsDir.$fileName);
                
                $className=$this->layer.'\\'.$this->objectName.'\datasources\\'.$viewName;
                $inst=new $className();
                
                $this->datasources[$viewName]=new \lib\reflection\classes\DataSourceDefinition($this,$viewName,$inst::$definition);               
                

            }
            
        }

        function loadActions()
        {
            $actionDir=$this->baseDir."/actions/";            
            if(!is_dir($actionDir))
                return;
            $fileIt=opendir($actionDir);
            while($fileName=readdir($fileIt))
            {
                if($fileName=="." || $fileName==".." || is_dir($actionDir.$fileName))
                    continue;
                $viewName=basename($fileName,".php");
                include_once($actionDir.$fileName);
                $className=$this->layer.'\\'.$this->objectName.'\actions\\'.$viewName;
                $inst=new $className();
                $this->actions[$viewName]=new \lib\reflection\classes\ActionDefinition($viewName,$this,$inst::$definition);

            }
        }
        function loadModelPermissions()
        {
                $this->modelPermissions=new \lib\reflection\classes\ModelPermissionsDefinition($this,$this->definition["PERMISSIONS"]?$this->definition["PERMISSIONS"]:array());
        }
        function getPermissionsDefinition()
        {
            return $this->modelPermissions;
        }
        function getActions()
        {
            return $this->actions;
        }

        function getIndexFields()
        {
            if(!is_array($this->indexFields))
                return array($this->fields[$this->indexFields]);
            $results=array();
            foreach($this->indexFields as $key=>$value)
                $results[$value]=$this->fields[$value];
            return $results;
        }
        function __getRequiredFields($required)
        {
            $notRequiredFlags=\lib\model\types\BaseType::TYPE_SET_ON_SAVE |
                              \lib\model\types\BaseType::TYPE_REQUIRES_SAVE;

            foreach($this->fields as $key=>$value)
            {
                // Las keys no se consideran campos "requeridos" u "opcionales"
                if(in_array($key,$this->indexFields))
                {
                    continue;
                }
                if(!$value->isRelation())
                {
                    $type=$value->getType();

                    $instance=$type[$key];

                    if($instance->flags & $notRequiredFlags && $instance->isEditable())
                        continue;
                }

                if($value->isRequired()==$required)
                    $results[$key]=$value;
            }
            return $results;
        }
        function getRequiredFields()
        {
            $res=$this->__getRequiredFields(true);
            return $res;
        }
        function getOptionalFields()
        {
            $res=$this->__getRequiredFields(false);
            return $res;
        }
        function getInvRelationships()
        {
            $results=array();
            foreach($this->aliases as $key=>$value)
            {
                $type=$value->getType();
                if(is_a($type,'\lib\reflection\classes\aliases\InverseRelation'))
                    $results[$key]=$type;
            }
            return $results;
        }
        function getLabelFields()
        {
            $results=array();
            foreach($this->fields as $key=>$value)
            {
                if($value->isLabel())
                    $results[$key]=$value;
            }
            return $results;
        }
        function getDescriptiveFields()
        {
            $results=array();
            foreach($this->fields as $key=>$value)
            {
                if($value->isDescriptive())
                    $results[$key]=$value;
            }
            return $results;
        }
        function getRelationFields()
        {
           return $this->getRelations();
        }
        function getStateField()
        {
            foreach($this->fields as $key=>$value)
            {
                if($value->isState())
                    return array($key=>$value);
            }
            return null;
        }
        function getDefaultState()
        {
            $stateField=$this->getStateField();
            if(!$stateField)
                return null;
            $vals=array_values($stateField);
            $types=array_values($vals[0]->getType());
            return $types[0]->getDefaultState();
            
        }

        function generateModelClass($empty=false)
        {
            $cName=$this->objectName->className;
            $layer=$this->objectName->layer;
            $classFile=PROJECTPATH."/$layer/objects/$cName/$cName".".php";
            if($this->config->mustRebuild("MODEL","Class",$classFile))
            {
                $layer=$this->objectName->layer;
                $cName=$this->objectName->className;        
                $classFileGenerator=new ClassFileGenerator($cName,$layer,                                                       
                                                       "$layer",
                                                       $classFile,
                                                       $this->extendedModel?"\\lib\\model\\ExtendedModel":"\\lib\\model\\BaseModel", 
                                                       true);
                $classFileGenerator->generate();
            }
        }
}


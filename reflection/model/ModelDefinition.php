<?php
/**
 * ADVERTENCIA : UN OBJETO MODELO NECESITA: 
 * 1) CREARSE 
 * 2) LLAMAR A INITIALIZE() 
 * 3) LLAMAR A o()
 * Para evitar relaciones circulares. 
 * 
 * @author Jose (13/10/2012)
 */
namespace lib\reflection\model;
class ModelDefinition extends \lib\reflection\base\SimpleModelDefinition
{
    var $aliases=array();
    var $actions=array();
    var $datasources=array();
    var $layer;
    var $actionsLoaded=false;
    var $datasourcesLoaded=false;
    var $extendedModel="";
    var $typeField=null;
    var $subTypes;
    var $storageConfigs;
    var $pages;
    var $aliasesLoaded=false;
    function __construct($objectName,$layer=null)
    {

       if($objectName=="") // TODO : Cambiar esto.
       {
           throw new \Exception("SIN OBJETO");
       }
       $this->objectName=new ObjectDefinition($objectName,$layer);
       try{
           $this->baseDir=$this->objectName->getDestinationFile();
           $this->definition=\lib\model\types\TypeFactory::getObjectDefinition($objectName,$layer);
           $this->layer=$this->objectName->layer;                     
           parent::__construct(
                          "Definition",
                           $this->layer,
                           $this->objectName->getNamespaced(),
                           $this->objectName->getPath("Definition.php")
                          );
      }
      catch(\lib\model\types\BaseTypeException $e)
      {                       
          $this->hasDefinition=false;
      }
      $this->config=new \lib\reflection\base\ModelConfiguration($this);
    }

    function initialize($definition=null)
    {
        parent::initialize(null);
        $this->loadModelPermissions();
        if($this->definition["EXTENDS"])
        {
            $this->extendedModel=$this->definition["EXTENDS"];
            
        }
        $this->acls=$this->definition["DEFAULT_PERMISSIONS"];

        if($this->definition["SERIALIZER"])
            $this->serializer=\lib\reflection\serializers\SerializerReflectionFactory::getReflectionSerializer($this->definition["SERIALIZER"]);
        else
            $this->serializer=null;

        if($this->definition["DEFAULT_SERIALIZER"])
        {
            // TODO! WARNING! Siempre devuelve el serializador de lectura!!
            global $SERIALIZERS;
            $this->serializer=$SERIALIZERS[$this->definition["DEFAULT_SERIALIZER"]];


        }
        if(isset($this->definition["STATES"]))
        {            

            $stateField=$this->getStateField();                       
            if($stateField)
            {
                $keys=array_keys($stateField);
                $sfield=$stateField[$keys[0]];
                $states=$this->definition["STATES"]["STATES"];
                $sfield->setStates($states);
            }                
        }
        if(isset($this->definition["TYPEFIELD"]))
        {
            $this->typeField=$this->definition["TYPEFIELD"];
            $this->subTypes=$this->definition["FIELDS"][$this->typeField]["VALUES"];
        }
        // Se aniade una cardinalidad, en caso de que no exista.
        if(!isset($this->definition["CARDINALITY"]))
            $this->definition["CARDINALITY"]=100;

        if(isset($this->definition["STORAGE"]))
        {
            foreach($this->definition["STORAGE"] as $key=>$value)
               $this->addStorageConfiguration($key,$value);
        }
        
    }
    /*
     * 'DEFAULT_SERIALIZER'=>"prestashopSlave",
               'DEFAULT_WRITE_SERIALIZER'=>"prestashopMaster",
     */
    function initializeAliases()
    {
        $this->aliasesLoaded=true;
        $this->loadAliases();
    }
    function getConfiguration()
    {
        return $this->config;
    }
    static function getObjects($layer)
    {
        return \lib\reflection\base\BaseDefinition::loadFilesFrom(PROJECTPATH."/".$layer."/objects/",null,false,true);
    }

    function getField($fieldName)
    {
        $inst=parent::getField($fieldName);
        if($inst)
            return $inst;
        
        if($this->extendedModel)
        {
            $ext=\lib\reflection\ReflectorFactory::getModel($this->extendedModel);
            return $ext->getField($fieldName);
        }
    }
    function getExtendedModelName()
    {
        return $this->extendedModel;

    }
    function getClassName()
    {
        return $this->objectName->getNormalizedName();
    }
    // Funcion que , en caso de que se pase un fieldName con un path ("a/b/c"), en vez de devolver a, devuelve c.
    function resolveField($fieldName)
    {
        $parts=explode("/",$fieldName);
        if($parts[0]=="")
            array_shift($parts);
        $nParts=count($parts);
        $first=$this->getFieldOrAlias($fieldName);
        if($nParts==1)
        {
            return array("model"=>$this,"field"=>$first);
        }
        array_shift($parts);
        $newPath=implode("/",$parts);
        if($first->isRelation() || $first->isAlias())
        {
            $m=$first->getRemoteModel();
            $rdef=$first->getDefinition();
            $rFields=$rdef["FIELDS"];
            return $m->resolveField($rFields[$newPath]);
        }
        return null;

    }
    function getAlias($fieldName)
    {
        // Hay que tratar el caso de que existe un path dentro del nombre del campo.
        $parts=explode("/",$fieldName);
        $extraName=null;
        $origField=$fieldName;
        if(count($parts)>1)
        {

            $fieldName=$parts[0];
            array_splice($parts,0,1);
            $extraName=implode("/",$parts);

        }



        if(!$this->aliasesLoaded)
            $this->loadAliases();
        $inst=$this->aliases[$fieldName];
        if($inst)
        {
            if($extraName==null)
                return $inst;
            $def=$this->aliases[$fieldName]->getDefinition();
            $ext=\lib\reflection\ReflectorFactory::getModel($def["OBJECT"]);
            return $ext->getFieldOrAlias($extraName);

        }
        if($this->extendedModel)
        {
            $ext=\lib\reflection\ReflectorFactory::getModel($this->extendedModel);
            return $ext->getFieldOrAlias($origField);
        }
        return null;
    }
    function hasCustomSerializer()
    {
        return $this->serializer!=null;
    }

    function getCustomSerializer()
    {
        return $this->serializer;
    }

    function isConcrete()
    {
        return true;
    }
    function getCardinality()
    {
        return $this->definition["CARDINALITY"];
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
            if($value->isRelation())
                $rels[$key]=$value;
        }
        return $rels;
    }
    function getSimpleRelations()
    {
        $rels=array();
        foreach($this->fields as $key=>$value)
        {
            if($value->isRelation())
                $rels[$key]=$value;
        }
        return $rels;
    }

    function getTableName()
    {
        if($this->definition["TABLE"])
			return $this->definition["TABLE"];
        if($this->objectName->isPrivate())
        {
            $parentObj=$this->objectName->getNamespaceModel();
            $model=\lib\reflection\ReflectorFactory::getModel($parentObj);
            $modelTable=$model->getTableName();
            $this->definition["TABLE"]=$modelTable."_".$this->objectName->className;
            return $this->definition["TABLE"];
        }
		return str_replace('\\','_',$this->objectName);
    }
    function getIndexFields()
    {
        //if(!$this->definition["EXTENDS"])
            return parent::getIndexFields();
        //$extModelInstance=\lib\reflection\ReflectorFactory::getModel($this->extendedModel);
        //return $extModelInstance->getIndexFields();
    }
        
    function getOwnershipField()
    {           
       return $this->definition["OWNERSHIP"];
    }    
       
    function loadAliases()
    {        
       $this->aliases=array();
       if(!$this->definition["ALIASES"])return;
       // Codigo para eliminar aliases duplicados.
       $existingAliases=array();
       foreach($this->definition["ALIASES"] as $key=>$value)
       {           
          /* if($value["FIELDS"])
           {
               if(is_array($value["FIELDS"]) && $value["OBJECT"])
                   $hash=implode(",",array_values($value["FIELDS"]));
               else
                   $hash=$value["FIELDS"];
               $objDef=new \lib\reflection\model\ObjectDefinition($value["MODEL"]);
               $hash=$objDef->className.$hash;
               if($existingAliases[$hash]) // Ya existe un alias para ese modelo/campos.
                   continue;
               $existingAliases[$hash]=1;
           }*/

           $this->aliases[$key]=AliasFactory::getAlias($this,$key,$value);
       }
            
    }
    function getAliases()
    {
        return $this->aliases;
    }

    function addAlias($name,$alias)
    {        
        echo "Aniadiendo el alias $name al objeto ".$this->getNamespaced()."<br>";    
        $this->aliases[$name]=$alias;
    }
        
    function getFieldOrAlias($name)
    {
        $field=$this->getField($name);
        if(isset($field))
            return $field;
        return $this->getAlias($name);
    }
    function getRole()
    {
        $def=$this->getDefinition();
        return $this->definition["ROLE"];
    }
    function addStorageConfiguration($storageEngine,$configuration)
    {        
        $this->storageConfigs[$storageEngine]=$configuration;
    }
    function getStorageConfiguration($storageEngine)
    {
        if(!isset($this->storageConfigs[$storageEngine]))
            return NULL;
        return $this->storageConfigs[$storageEngine];
    }

    /**
     *    Function : getDefinition()
       */

        function getDefinition()
        {
            $def=array();
            if($this->extendedModel)
                $def["EXTENDS"]=$this->extendedModel;
            // LOS ROLES POSIBLES VAN A SER ENTITY / MULTIPLE_RELATION / PROPERTY
            if(!isset($this->definition["ROLE"]))
                $def["ROLE"]="ENTITY";
            else
                $def["ROLE"]=$this->definition["ROLE"];

            if($this->definition['DEFAULT_SERIALIZER'])
                $def['DEFAULT_SERIALIZER']=$this->definition['DEFAULT_SERIALIZER'];
            if($this->definition['DEFAULT_WRITE_SERIALIZER'])
                $def['DEFAULT_WRITE_SERIALIZER']=$this->definition['DEFAULT_WRITE_SERIALIZER'];
            // MULTIPLE_RELATION debe tener los campos "MODELS" y "OWNER"
            // MODELS es la lista de modelos que estan relacionados.
            // OWNER indica la entidad duenia de la relacion.

            if($def["ROLE"]=="MULTIPLE_RELATION")
            {
                $def["MULTIPLE_RELATION"]=$this->definition["MULTIPLE_RELATION"];
            }            

            // CAMPOS INDICES USADOS POR ESTE OBJETO.Es un array de campos.
            if($this->indexFields)
                $def["INDEXFIELDS"]=$this->indexFields;
            if($this->typeField)
                $def["TYPEFIELD"]=$this->typeField;

            // Sobreescribe el nombre de la tabla a generar para este objeto.
            if($this->definition["TABLE"])
                $def["TABLE"]=$this->definition["TABLE"];
            else
                $def["TABLE"]=str_replace('\\','_',$this->objectName->getNormalizedName());

            // Etiqueta general para el objeto
            if($this->definition["LABEL"])
                $def["LABEL"]=$this->definition["LABEL"];
            else
            {
                $def["LABEL"]=$this->objectName->getNormalizedName();
            }
            if($this->definition["SHORTLABEL"])
                $def["SHORTLABEL"]=$this->definition["SHORTLABEL"];
            else                              
                $def["SHORTLABEL"]=$this->objectName->getNormalizedName();

            // CARDINALITY es una estimacion del numero de filas de este objeto.Obviamente, no es exacta.Sirve para
            // "tener una idea" de si esta tabla va a ser muy grande, o no.
            if(!$this->definition["CARDINALITY"])
                $def["CARDINALITY"]=100;
            else
                $def["CARDINALITY"]=$this->definition["CARDINALITY"];
            // CARDINALITY_TYPE indica si el numero de filas de este objeto va a variar mucho o no.De esta forma, sabemos si
            // una tabla que indica 20 en su CARDINALITY,y su CARDINALITY_TYPE es FIXED, es mejor no cargarla LAZY, define el tipo de input a generar por defecto, etc.
            if(!$this->definition["CARDINALITY_TYPE"])
                $def["CARDINALITY_TYPE"]="VARIABLE"; // o FIXED
            else
                $def["CARDINALITY_TYPE"]=$this->definition["CARDINALITY_TYPE"];

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
            // if($this->serializer)
            //     $def["SERIALIZER"]=$this->serializer->getDefinition();

             
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
             
             if(isset($this->storageConfigs))
             {
                 foreach($this->storageConfigs as $key=>$value)
                 {
                     $def["STORAGE"][$key]=$value;
                 }
             }
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
                {
                     $value->save($key);
                }
                foreach($this->datasources as $key=>$value)
                    $value->save($key);
            }
        }

        function saveDefinition()
        {
            $def=$this->getDefinition();
            $this->addProperty(array("NAME"=>"definition",
                                     "ACCESS"=>"static",
                                     "DEFAULT"=>$def));
            $this->generate();
            
        }
        
        function addDataSource($dataSource)
        {
            $this->datasources[$dataSource->getName()]=$dataSource;
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
                    $layer=\lib\reflection\ReflectorFactory::getLayer($this->objectName->layer);
                    return $layer->getSerializer();
                }
        }
        

        function getDataSources()
        {
            if(!$this->datasourcesLoaded)
                $this->loadDataSources();
            return $this->datasources;
        }
        function getDataSource($name)
        {
            if(!$this->datasourcesLoaded)
                $this->loadDataSources();
            return $this->datasources[$name];
        }
        

        function loadDataSources()
        {
            if($this->datasourcesLoaded)
            {
                return $this->datasources;
            }
            $dsnames=$this->loadFilesFrom($this->objectName->getPath("/datasources/"),"/.*\.php/",true,false,true);
            foreach($dsnames as $curDs)
            {
                include_once($this->objectName->getPath('/datasources/'.$curDs.".php"));
                $dsclass=$this->objectName->getNamespaced().'\\datasources\\'.$curDs;
                $instance=new $dsclass();
                if(is_a($instance,'\lib\datasource\MultipleDataSource'))
                    continue;
                $this->datasources[$curDs]=new \lib\reflection\datasources\DataSourceDefinition($curDs,$this);
                $this->datasources[$curDs]->initialize();
            }
            $this->datasourcesLoaded=true;            
            return $this->datasources;
        }        

        function loadModelPermissions()
        {
                $this->modelPermissions=new \lib\reflection\permissions\ModelPermissionsDefinition($this,$this->definition["PERMISSIONS"]?$this->definition["PERMISSIONS"]:array());
        }
        function getPermissionsDefinition()
        {
            return $this->modelPermissions;
        }

        function getActions()
        {
            if(!$this->actions) {
                $this->loadActions();
            }
            return $this->actions;
        }
        function getAction($name)
        {
            if(!$this->actions)
                $this->loadActions();
            return $this->actions[$name];
        }
        function loadActions()
        {
            $this->actions=array();
            $actions=$this->loadFilesFrom($this->objectName->getPath('')."/actions/","/.*\.php/",true,false,true);
            foreach($actions as $curAct)
            {                
                $this->actions[$curAct]=new \lib\reflection\actions\ActionDefinition($curAct,$this);
                $this->actions[$curAct]->initialize();
            }
        }

        function getInvRelationships()
        {
            $results=array();
            foreach($this->aliases as $key=>$value)
            {
                if(is_a($value,'\lib\reflection\aliases\InverseRelation'))
                    $results[$key]=$value;
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
    function getSearchableFields()
    {
        $results=array();
        foreach($this->fields as $key=>$value)
        {
            if($value->isSearchable())
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

        function saveInitialData()
        {

        }

        function runSetup()
        {
            $layer=$this->objectName->layer;
            $objName=$this->objectName->getNormalizedName();
            $sPath=$this->objectName->getPath("Setup.php");
              if(is_file($sPath))
              {
                  include_once($sPath);
                  $className=$this->objectName->getNamespaced().'\\Setup';
                  if(class_exists($className))
                  {
                      $setupInstance= new $className();
                      if(method_exists($setupInstance,"install"))                            
                           $setupInstance->install();                            
                   }
               }
        }

        function createDerivedRelations()
        {
            $relations=$this->getRelations();
            echo "<h3>Generando relaciones derivadas para ".$this->objectName->getNamespaced()."</h3>";
            foreach($relations as $relName=>$relObject)
            {
                echo "Analizando relacion $relName<br>";                
                $relObject->createDerivedRelation();
            }
        }

        function hasRelationWith($model,$fields)
        {
            $relations=$this->getRelations();
            foreach($relations as $key=>$value)
            {
                if($value->pointsTo($model,$fields))
                    return true;
            }
            return false;
        }
        function areIndexesContained($fieldList)
        {
            $indexf=$this->getIndexFields();
            $keys=array_keys($indexf);

            if(count($keys)==count(array_intersect($keys,$fieldList)))
                return $keys;
            return null;
        }
        function getSubTypes()
        {
            return $this->subTypes;
        }
        function getSubTypeField()
        {
            return $this->typeField;
        }
        function addDatasourcePage($page,$ds)
        {
            $this->pages["DATASOURCES"][]=array("page"=>$page,"datasource"=>$ds);
        }
        function getDatasourcePages()
        {
            return $this->pages["DATASOURCES"];
        }
        function addActionPage($page,$action)
        {
            $this->pages["ACTIONS"][]=array("page"=>$page,"action"=>$action);
        }
        function getActionPages()
        {
            return $this->pages["ACTIONS"];
        }
        
}


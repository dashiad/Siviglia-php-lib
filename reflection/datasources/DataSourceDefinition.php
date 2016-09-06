<?php
namespace lib\reflection\datasources;
/*
    Los datasources creados son (tanto Admin cono no-Admin)
 
 
 
 
*/
class DataSourceDefinition extends \lib\reflection\base\ConfiguredObject
{       
    var $serializersDefinition=array();
    var $serializer;
    var $role="list";
    var $haveIndexes=false;
    var $modelIndexes;
    var $isadmin;
    var $outputWidgets;
    var $storageDefinitions;

    function __construct($name,$parentModel)
    {
        $this->parentModel=$parentModel;
        parent::__construct($name, $parentModel,'\datasources','datasources','datasources','',null);
    }


    function initialize($definition=null)
    {        
        parent::initialize($definition);

        if($this->definition["ROLE"])
            $this->role=$this->definition["ROLE"];

        $this->isadmin=$definition["IS_ADMIN"];        
      
        $this->metadata=array();
                
        $this->serializer=$this->parentModel->getSerializer();
               
        $indexFields=$this->parentModel->getIndexFields();

        $containsIndexes=$this->parentModel->areIndexesContained(array_keys($this->metadata));
        if($containsIndexes!=null)
        {
                $this->haveIndexes=true;
                $this->modelIndexes=$containsIndexes;
        }               
        $this->permissions=new \lib\reflection\permissions\PermissionRequirementsDefinition($definition["PERMISSIONS"]?$definition["PERMISSIONS"]:"_PUBLIC_");
        $this->includes=array();
        if($this->definition["INCLUDE"])
        {
            foreach($this->definition["INCLUDE"] as $key=>$value)
            {
                $this->includes[$key]=new DataSourceIncludeDefinition($value);
            }            
        }                
        
        
    }

    function loadParams()
    {
        if(!isset($this->definition["PARAMS"]))
        {
            $this->params=array();
            return;
        }
        
        foreach($this->definition["PARAMS"] as $key=>$value)
            $this->params[$key]=new DataSourceParameterDefinition($key,$value);
    }
    
    function getRole()
    {
        $def=$this->getDefinition();
        $role=$def["ROLE"];
        if(!$role)
            return "view";
        return $role;
    }

    function haveIndexes()
    {
        return $this->haveIndexes;
    }
    function getIncludes()
    {
        if(isset($this->includes))
            return $this->includes;
        return array();
    }
    function create($fields,$permissions,$curIndexes,$filterFields,$role,$isadmin=false)
    {    
        
        $name=$this->getName();    
        $dsDefinition=array(
            "ROLE"=>$role,
            "DATAFORMAT"=>"Table",
            "PARAMS"=>array(),
            "IS_ADMIN"=>$isadmin?1:0
            );
        foreach($curIndexes as $key=>$value)      
        {
            $paramType=\lib\reflection\model\types\ModelReferenceType::create($this->parentModel->objectName->getNormalizedName(),$key);
            $paramField=DataSourceParameterDefinition::create($key,$paramType->getDefinition(),null);
            $dsDefinition["INDEXFIELDS"][$key]=$paramField->getDefinition();
        }
        $relationFields=array();

        foreach($fields as $key=>$value)
        {
            if(is_object($value))
            {
                if($value->isRelation())
                    $relationFields[$key]=$value;
            }
        }
        if($filterFields)
        {
        foreach($filterFields as $key=>$value)
        {
            // Hay que descartar los campos que ya hayan sido incluidos como keys
            if($dsDefinition["PARAMS"][$key])
                continue;

            // Tanto en los campos como en los filtros, nos pueden pasar un objeto, o un array.
            // Si es un objeto, se supone que es un ModelField, por lo que hay que calcularle el tipo.
            // Si es un array, se supone que , directamente, es una definicion normal de campo (con TYPE,etc), por
            // lo que simplemente hay que copiarla.
            if(is_object($value))
            {
                $paramType=\lib\reflection\model\types\ModelReferenceType::create($this->parentModel->objectName->getNamespaced(),$key);
                $paramField=DataSourceParameterDefinition::create($key,$paramType->getDefinition(),$key);
                $dsDefinition["PARAMS"][$key]=$paramField->getDefinition();
                // Si es ajax, permitimos errores (cadenas incompletas, etc)
            
                $type=$value->getRawType();
                $keys=array_keys($type);
                if(is_a($type[$keys[0]],'\lib\model\types\String'))
                {
                    $dsDefinition["PARAMS"]["dyn".$key]=$paramType->getDefinition();
                    $dsDefinition["PARAMS"]["dyn".$key]["PARAMTYPE"]="DYNAMIC";
                }
            }
            else{
                $dsDefinition["PARAMS"][$key]=$value;
            }
          }
        }
        /*if($role=="list" || $role=="MxNlist")
        {
            // Se aniaden variables para la paginacion.
            $dsDefinition["PARAMS"]["__start"]=array("TYPE"=>"Integer","DEFAULT"=>"0");
            $dsDefinition["PARAMS"]["__count"]=array("TYPE"=>"Integer","DEFAULT"=>"35");
            $dsDefinition["PARAMS"]["__sort"]=array("TYPE"=>"String","MAXLENGTH"=>20);
            $dsDefinition["PARAMS"]["__sortDir"]=array("TYPE"=>"Enum","VALUES"=>array("ASC","DESC"),"DEFAULT"=>"ASC");
            $dsDefinition["PARAMS"]["__sort1"]=array("TYPE"=>"String","MAXLENGTH"=>20);
            $dsDefinition["PARAMS"]["__sortDir1"]=array("TYPE"=>"Enum","VALUES"=>array("ASC","DESC"),"DEFAULT"=>"ASC");
            //$dsDefinition["PARAMS"]["curPage"]=array("TYPE"=>"Integer","DEFAULT"=>"0","PARAMTYPE"=>"PAGER");
        }
        else
        {
            $dsDefinition["PARAMS"]["__start"]=array("TYPE"=>"Integer","DEFAULT"=>"0");
            $dsDefinition["PARAMS"]["__count"]=array("TYPE"=>"Integer","DEFAULT"=>"10");
        }
        */

        foreach($fields as $key=>$value)
        {
            // De nuevo, se distingue si en value tenemos un objeto o un array.Si es un objeto, se supone un ModelField.
            // Si es un array, es simplemente una definicion de campo "normal" (con "TYPE")
            if(is_object($value))
            {
                $paramType=\lib\reflection\model\types\ModelReferenceType::create($this->parentModel->objectName->getNamespaced(),$key);
                $dsDefinition["FIELDS"][$key]=$paramType->getDefinition();
                $dsDefinition["FIELDS"][$key]["LABEL"]=$key;
            }
            else
                $dsDefinition["FIELDS"][$key]=$value;
        }

        $usedNames=array();
        
        foreach($relationFields as $key=>$value)
        {
           // Por cada relacion, se crea un subDs         
           $j=0;
           $remObjectName=$value->getRemoteObjectName();
           if(is_a($value,'\lib\reflection\model\aliases\Relationship'))
               $subDsName=$remObjectName;
           else
               $subDsName=$remObjectName."_".$key;

           while($usedNames[$subDsName.$j])$j++;
               $usedNames[$subDsName.$j]=1;
           $includeName=$j?$subDsName.$j:$subDsName;
           $instance=\lib\reflection\datasources\DataSourceIncludeDefinition::create($key,$value,($dsDefinition["IS_ADMIN"]?"AdminFullList":"FullList"),"LEFT");
           $dsDefinition["INCLUDE"][$includeName]=$instance->getDefinition();                
         }

        if(strtolower($role)=="view")
        {
            // Se incluyen los datasources asociados a los aliases
            $aliases=$this->parentModel->getAliases();
            foreach($aliases as $key=>$value)
            {
                if($value->isRelation())
                {
                        // En el caso de una inverseRelation
                        $subDsName=$key;
                        $j=0;
                        while($usedNames[$subDsName.$j])$j++;
                            $usedNames[$subDsName.$j]=1;
                       $includeName=$j?$subDsName.$j:$subDsName;
                      
                       $instance=\lib\reflection\datasources\DataSourceIncludeDefinition::createFromAlias($key,$value,$key,"INNER"); 
                      
                       $dsDefinition["INCLUDE"][$includeName]=$instance->getDefinition();                
                    
                }
            }

        }
       $perms=\lib\reflection\permissions\PermissionRequirementsDefinition::create($permissions);
       $dsDefinition["PERMISSIONS"]=$perms->getDefinition();
       $this->initialize($dsDefinition);
       return $this;
    }       

    function getParentModelKeysParams()
    {
        $curIndexes=$this->parentModel->getIndexFields();
        foreach($curIndexes as $key=>$value)      
        {
            $paramType=\lib\reflection\model\types\ModelReferenceType::create($this->parentModel->objectName->getNormalizedName(),$key);
            $paramField=DataSourceParameterDefinition::create($key,$paramType->getDefinition(),null);
            $params[$key]=$paramField->getDefinition();
        }
        return $params;
    }
    // El parametro including significa si este datasource muestra los relacionados ($including="INNER")
    // ,los no relacionados ($including="OUTER"), o todos ($including="LEFT".    

    function createFromInverseRelation($inverseRelationField,$role,$including,$isadmin,$permissions)
    {
        echo "CREANDO DS con nombre ".$this->getName()." para ".$this->parentModel->objectName." a partir de relacion inversa<br>";

        $perms=\lib\reflection\permissions\PermissionRequirementsDefinition::create($permissions);
        // En una relacion inversa, se generan 2 datasources.El primero, incluyente, es aquel que
        // incluye todos los objetos remotos que apuntan a uno actual.
        $def=array(
                'LABEL'=>$this->getName(),
                'DATAFORMAT'=>'Table',
                'ROLE'=>$role,
                'IS_ADMIN'=>$isadmin?1:0,
                'INDEXFIELDS'=>$this->getParentModelKeysParams(),
                'RELATION'=>$inverseRelationField->getName());
        $fName=$inverseRelationField->name;

        $pointedFields=$inverseRelationField->getRemoteFieldInstances();

        $keys=array_keys($pointedFields);
        $firstField=$pointedFields[$keys[0]];
        $relation=array();
        // Se obtiene la definicion remota de todas las relaciones de este alias.
        foreach($pointedFields as $key=>$value)
        {
            // Los campos locales, son aquellos apuntados por la relacion remota.
            $def["PARAMS"][$key]=array("MODEL"=>$value->parentModel->objectName->getNamespaced(),"FIELD"=>$key,"TRIGGER_VAR"=>$key);
            $relation=array_merge($relation,$value->getRelationFields());
        }

/*        $descriptiveFields=$this->parentModel->getDescriptiveFields();
        if(!$descriptiveFields)
            $descriptiveFields=$this->parentModel->getFields();
        $namespaced=$this->parentModel->objectName->getNamespaced();
        foreach($descriptiveFields as $key=>$value)
            $def["FIELDS"][$key]=array("MODEL"=>$namespaced,"FIELD"=>$key);
*/
        $def["INCLUDE"][$firstField->parentModel->objectName->getNormalizedName()]=array(
                          "OBJECT"=>$firstField->parentModel->objectName->getNormalizedName(),
                          "DATASOURCE"=>"View",
                          "JOINTYPE"=>$including,
                          "JOIN"=>array_flip($relation));

        $def["PERMISSIONS"]=$perms->getDefinition();
        
        $this->initialize($def);
        return $this;        
    }

    function createFromMxNRelation($inverseRelationField,$role,$including,$isadmin,$permissions)
    {
        // Si estamos en el objeto A , que se relaciona con B a traves de A-B, este datasource da los A que estan relacionados con
        // un cierto B.Por tanto, aun estando en A, el parametro es un id_B.
        $perms=\lib\reflection\permissions\PermissionRequirementsDefinition::create($permissions);
        
        $def=array(
                'LABEL'=>$this->getName(),
                'DATAFORMAT'=>'Table',
                'ROLE'=>$role,
                'IS_ADMIN'=>$isadmin?1:0,
                'RELATIONTYPE'=>$including,
                'RELATION'=>array("MODEL"=>$inverseRelationField->parentModel->objectName->getNormalizedName(),
                                  "FIELD"=>$inverseRelationField->getName())
                );

        
        $localFields = $inverseRelationField->getRelationTableLocalFields();
        $remoteFields= $inverseRelationField->getRelationTableRemoteFields();        
        $remClass    = $inverseRelationField->getRemoteObjectName();
       
        $joins = array();
                    
        // Aunque estamos en el objeto A, este datasource recibe como parametro un ID del objeto B.
        // Esto ha costado mucho decidirlo.
        // La causa de que ocurra esto, es que es la forma mas simple de cumplir las siguientes restricciones:
        // 1) Procesar una MxN debe hacerse en 1 sola query, un left join de la tabla intermedia con la tabla que nos interese.
        // 2) Conseguir esa query a partir de 2 datasources distintos, requeriria construir ese left join a partir de las 2
        //    queries de ambos datasources.Y eso puede volverse muy complejo.
        //    Nota: una forma de hacerlo,seria hacer que los parametros (indices) de un datasource, en vez de asignarsele valores,
        //    se le pudiera asignar otro datasource.
        //    Por ejemplo, si el ds de A tiene un parametro id_a , y a ese parametro le podemos asignar otro datasource, que devuelve
        //    una columna id_a, la query resultante, en vez de ser del tipo id_a IN (1,2...) deberia ser del tipo id_a IN (SELECT ...),
        //    es decir, en vez de ser simples valores, seria la query del datasource asignado al parametro.
        
        /*$fieldSrc=($including=="LEFT"?$localFields:$remoteFields);
        $srcClass=($including=="LEFT"?$this->parentModel->objectName->getNormalizedName():$remClass);
        */
        $fieldSrc=($including=="LEFT"?$localFields:$remoteFields);
        $srcClass=($including=="LEFT"?$inverseRelationField->parentModel->objectName->getNormalizedName():$remClass);
        

        foreach($fieldSrc as $keyF=>$valueF)
        {
            $paramType=\lib\reflection\model\types\ModelReferenceType::create($srcClass,$keyF);
            $paramField=DataSourceParameterDefinition::create($keyF,$paramType->getDefinition(),null);
            $def["INDEXFIELDS"][$keyF]=$paramField->getDefinition();
            $def["INDEXFIELDS"][$keyF]["MAPS_TO"]=$valueF;
            $joins[$valueF]=$keyF;
         }        

         foreach($localFields as $keyF=>$valueF)
         {
             $def["FIELDS"][$valueF]=array(
                 "REFERENCES"=>array(
                     "MODEL"=>$this->parentModel->objectName->getNormalizedName(),
                     "FIELD"=>$keyF)
                 );
            
         }
         // Se aniaden a los campos locales, todos los campos de la vista View de este objeto.
         $viewDs=$this->parentModel->getDataSource("ViewDs");
         $viewDef=$viewDs->getDefinition();
         $def["FIELDS"]=array_merge($def["FIELDS"],$viewDef["FIELDS"]);

         // Se repasan los campos, para ver cuales son cadenas, y a�adir los parametros "normales",y
         // "dinamicos".
         foreach($def["FIELDS"] as $key=>$value)
         {
             $def["PARAMS"][$key]=$value;
             $def["PARAMS"][$key]["TRIGGER_VAR"]=$key;
             //unset($def["PARAMS"][$key]["LABEL"]);

             if($value["REFERENCES"])
             {
                 $model=$value["REFERENCES"]["MODEL"];
                 $fieldName=$value["REFERENCES"]["FIELD"];
             }
             else
             {
                 $model=$value["MODEL"];
                 $fieldName=$value["FIELD"];
             }

             $target=\lib\reflection\ReflectorFactory::getModel($model);
             $field=$target->getField($fieldName);
             $type=$field->getRawType();
             $keys2=array_keys($type);
            if(is_a($type[$keys2[0]],'\lib\model\types\String'))
            {
                $def["PARAMS"]["dyn".$key]=$value;
                $def["PARAMS"]["dyn".$key]["PARAMTYPE"]="DYNAMIC";
            } 
             
             
         }
        
        $def["PERMISSIONS"]=$perms->getDefinition();
        $this->initialize($def);
        
        return $this;        
    }
       
    function isAdmin()
    {
        return $this->isadmin;
    }
    
    function addSerializerDefinition($serName,$defObj)
    {
        $this->serializersDefinition[$serName]=$defObj;
    }
    function getDefinition()
    {                
        return $this->definition;
    }

    function save()
    {        
        $this->addProperty(array("NAME"=>"definition",
                                 "ACCESS"=>"static",
                                 "DEFAULT"=>$this->getDefinition()));
        $this->generate();      
    }
    
    function setWidget($outputType,$object)
    {
        $this->outputWidgets[$outputType]=$object;
    }
    function getWidget($outputType)
    {
        if(!isset($this->outputWidgets[$outputType]))
        {
            if(strtoupper($this->role)=="VIEW")
                $handler="view";
            else
                $handler="list";
            echo "LOADING FOR ROLE ".$this->role." de ".$this->className." [".$this->parentModel->objectName->getNormalizedName()."]<br>";
            $className='\lib\reflection\\'.$outputType.'\\views\\'.ucfirst(strtolower($handler)).'Widget';
            $generator=new $className($this->name,$this->parentModel,$this);
            $generator->initialize();
            $this->outputWidgets[$outputType]=$generator;
        }
        return $this->outputWidgets[$outputType];
    }
    
    function setFields($fields)
    {        
        $this->definition["FIELDS"]=$fields;
        $this->loadFields();
    }
    function getSourceRelation()
    {
        if(!isset($this->definition["RELATION"]))
            return null;
        return $this->definition["RELATION"];
    }    
    function getStorageDataSource($type)
    {
        if(!$this->storageDataSource)
        {            
            $className='\lib\reflection\storage\\'.ucfirst(strtolower($type)).'DsDefinition';
            $this->storageDataSource=new $className($this->parentModel,$this->getName(),$this);
        }
        return $this->storageDataSource;
    }
    
    function addStorageDefinition($storageType,$def)
    {
        $this->definition["STORAGE"][$storageType]=$def;
    }

    function getStorageDefinition($storageType)
    {
        if(!isset($this->definition["STORAGE"][$storageType]))
            return null;
        return $this->definition["STORAGE"][$storageType];
    }
}
?>

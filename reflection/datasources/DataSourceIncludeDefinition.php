<?php
namespace lib\reflection\datasources;
class DataSourceIncludeDefinition
{
    function __construct($definition)
    {
        $this->definition=$definition;
    }
    static function create($relationshipName,$relationshipField,$remoteDs,$joinType="LEFT")
    {
        $relations=$relationshipField->getRelationFields();
        if(is_array($relations))
            $relation=array_flip($relations);
        else
            $relation=array($relations=>$relationshipName);
        return new DataSourceIncludeDefinition(array(
            "OBJECT"=>$relationshipField->getRemoteModelName(),
            "DATASOURCE"=>$remoteDs,
            "JOINTYPE"=>$joinType,
            "JOIN"=>$relation
            ));
    }
    static function createFromAlias($aliasName,$aliasField,$remoteDsName,$joinType)
    {

        $className=get_class($aliasField);
        switch($className)
        {        
            case 'lib\reflection\model\aliases\Relationship':
               {
                      return DataSourceIncludeDefinition::createFromMxNRelation($aliasName,$aliasField,$remoteDsName,$joinType);
               }break;
            case 'lib\reflection\model\aliases\InverseRelation':
               {
                     return DataSourceIncludeDefinition::createFromInverseRelation($aliasName,$aliasField,$remoteDsName,$joinType);
               }break;
        }
    }
    static function createFromInverseRelation($relationshipName,$relationshipField,$remoteDs,$joinType="LEFT")
    {
        // Tengamos los objetos A y B
        // B tiene una relacion con A,  por lo que A tiene una relaciona inversa con B.
        // Por lo tanto, el datasource desde A (la relacion inversa), es el fullList de B, pero filtrado
        // por los campos de la relacion.

        $relations=$relationshipField->getRemoteFieldInstances();
        foreach($relations as $key=>$value)
        {
            if(!is_object($value))
            {
                echo "STOPPING";
                $p=1;
            }
            $fieldNames=$value->getRemoteFieldNames();
            foreach($fieldNames as $key2=>$val2)
            {
                $joins[$key]=$val2;
            }
        }
        /*return new DataSourceIncludeDefinition(array(
            "OBJECT"=>$relationshipField->getRemoteModelName(),
            "DATASOURCE"=>'FullList',
            "JOINTYPE"=>$joinType,
            "JOIN"=>$joins
            ));*/
        $def=array(
            "OBJECT"=>$relationshipField->getRemoteModelName(),
            "DATASOURCE"=>'FullList',
            "JOINTYPE"=>$joinType,
            "JOIN"=>$joins
        );
        $extraConds=$relationshipField->getExtraConditions();
        if($extraConds)
        {
            $def["CONDITIONS"]=$extraConds;
        }
        return new DataSourceIncludeDefinition($def);
    }

        
    // Los datasources que relacionan los objetos A y B, que tienen una multiple relation usando el objeto C, los va a definir
    // C.Los nombres de los datasources van a ser los nombres de los modelos relacionados.
    // Si estoy en A, tengo que obtener el datasource B, y al reves.    
    static function createFromMxNRelation($relationshipName,$relationshipField,$remoteDs,$joinType="LEFT")
    {    
        $relationshipObjName=$relationshipField->getRelationModelName();
        $relationshipObj=\lib\reflection\ReflectorFactory::getModel($relationshipObjName);        
        $relDef=$relationshipObj->getDefinition();

        $localFields=$relationshipField->getLocalMapping();
        $localMap=array_values($localFields);

        $relFields=$relDef["MULTIPLE_RELATION"]["FIELDS"];
        
        $diff=array_values(array_diff($relFields,$localMap));

        /*return new DataSourceIncludeDefinition(array(
            "OBJECT"=>$relationshipObjName,
            "DATASOURCE"=>$diff[0],
            "JOINTYPE"=>"INNER", // Siempre en INNER.
            "JOIN"=>array_flip($localFields)
            ));*/

        $def=array(
            "OBJECT"=>$relationshipObjName,
            "DATASOURCE"=>"FullList",
            "JOINTYPE"=>"INNER", // Siempre en INNER.
            "JOIN"=>array_flip($localFields)
        );
        $extraConds=$relationshipField->getExtraConditions();
        if($extraConds)
        {
            $def["CONDITIONS"]=$extraConds;
        }
        return new DataSourceIncludeDefinition($def);
    }        

    function getDefinition()
    {
        return $this->definition;
    }
    function getDatasource()
    {
        $remoteObject=\lib\reflection\ReflectorFactory::getModel($this->definition["OBJECT"]);
        $datasources=$remoteObject->loadDatasources();        
        if(!array_key_exists($this->definition["DATASOURCE"],$datasources))
        {
            // TODO: Throw exception
            return null;
        }
        return $datasources[$this->definition["DATASOURCE"]];
    }
}

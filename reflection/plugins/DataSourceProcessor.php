<?php
namespace lib\reflection\plugins;

class DataSourceProcessor extends \lib\reflection\SystemPlugin {

    function REBUILD_DATASOURCES($level)
    {
        if($level!=0)return;
        printPhase("Generando datasources");
        $this->iterateOnModels('createDataSources');
        $this->iterateOnModels('createAliasDataSources');
    }
    function createDataSources($layer,$objName,$model)
    {              
        $model->loadDatasources();

        $fields=$model->getFields();
        $relationFields=$model->getRelations();
        $fullFields=$model->getFields();
        $descriptiveFields=$model->getDescriptiveFields();
        $label=$model->getLabelFields();
        $indexFields=$model->getIndexFields();

        /* Se obtienen los campos derivados de string, para determinar que campos existen en los Dyn.*/
        $nStringFields=0;
        $urlPathField="";
        foreach($fullFields as $key=>$value)
        {
            if($indexFields[$key]) // Es un campo $key.Se incluye
            {
                $stringFields[$key]=$value;
                continue;
            }

            $type=$value->getRawType();
            $keys=array_keys($type);
            if(is_a($type[$keys[0]],'\lib\model\types\String'))
            {
                $stringFields[$key]=$value;
                $nStringFields++;
            }
/*            if(is_a($type[$keys[0]],'\lib\model\types\UrlPathString'))
            {
                $urlPathField=$keys[0];
            }*/
            if($value->isUnique() && $urlPathField=="" && in_array($keys[0],array("name","nombre","title","titulo")))
            {
                $urlPathField=$key;
                $urlPathFields[$key]=$value;
            }
        }

        /*$extended=$model->getExtendedModelName();
        if($extended)
        {
            $parentModel=\lib\reflection\ReflectorFactory::getModel($extended);

            $fields=array_merge($fields,$parentModel->getFields());
            $relationFields=array_merge($relationFields,$model->getRelations());
            $fullFields=$fields;
            $descriptive=array_merge($descriptive,$model->getDescriptiveFields());
            $label=array_merge($label,$model->getLabelFields());
        }*/

        if(count($descriptiveFields)==0)
            $descriptiveFields=$fullFields;

        $viewDsName="View";
        if($urlPathField!="")
             $viewDsName="ViewByIdDs";

        $defaultDs=array(array("FullList",$viewDsName,"AdminFullList","AdminView"),
                         array($descriptiveFields,$fullFields,$descriptiveFields,$fullFields),
                         array(array("_PUBLIC_"),array("_PUBLIC_"),array(array("MODEL"=>$objName,"PERMISSION"=>"adminList")),array(array("MODEL"=>$objName,"PERMISSION"=>"adminView"))),
                         array(array(),$indexFields,array(),$indexFields),
                         array($descriptiveFields,$indexFields,$descriptiveFields,$indexFields),
                         array("list","view","list","view"),
                         array(false,false,true,true)
                         );
        if($urlPathField!="")
        {
            $defaultDs[0][]="View";
            $defaultDs[1][]=$fullFields;
            $defaultDs[2][]=array("_PUBLIC_");
            $defaultDs[3][]=$urlPathFields;
            $defaultDs[4][]=array();
            $defaultDs[5][]="view";
            $defaultDs[6][]=false;
        }

        $ownershipField=$model->getOwnershipField();
                
        if($ownershipField)
        {
            $ownerField=array($ownershipField=>$fields[$ownershipField]);
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
       
        $nDs=count($defaultDs[0]);
        for($k=0;$k<$nDs;$k++)
        {
            $curDs=new \lib\reflection\datasources\DataSourceDefinition($defaultDs[0][$k],$model);
            if($curDs->mustRebuild())
            {
                //($fields,$permissions,$curIndexes,$filterFields,$role,$isadmin=false)
                $curDs->create($defaultDs[1][$k],$defaultDs[2][$k],$defaultDs[3][$k],$defaultDs[4][$k],$defaultDs[5][$k],$defaultDs[6][$k]);
                $model->addDatasource($curDs);
            }
            else
                $curDs->initialize();
        }

    }
    function createAliasDataSources($layer,$objName,$model)
    {                       
        // Generacion de datasources asociados a campos alias.
        $aliases=$model->getAliases();
        if(!$aliases || count($aliases)==0)
            return;
        foreach($aliases as $key=>$value)
        {            
            
            // los datasources derivados de una relacion MxN, se aniaden a la tabla intermedia.
            if(is_a($value,'\lib\reflection\model\aliases\Relationship'))
            {
                 $targetModelName=$value->getRelationModelName();
                 $targetModel=\lib\reflection\ReflectorFactory::getModel($targetModelName,$key);
            }
            else
                $targetModel=$model;

            $datasources=$value->getDatasources();
            foreach($datasources as $cVal)
            {
                $targetModel->addDatasource($cVal);
            }                       
        }
        /*
        // Si el modelo actual es una multiple relationship, creamos los datasources directos sobre los objetos que relaciona.
        $role=$model->getRole();
        if($role=="MULTIPLE_RELATION")
        {
            $def=$model->getDefinition();
            $mult=$def["MULTIPLE_RELATION"];
            // Hay que crear datasources para cada uno de los objetos relacionados.
            foreach($mult["FIELDS"] as $value)
            {
                $relatedModel[]=''.$model->getField($value)->getRemoteModel()->objectName;
            }
            sort($relatedModel);
            $name=implode("_",$relatedModel);
            foreach($mult["FIELDS"] as $value)
            {
                $fake=new \lib\reflection\model\aliases\FakeRelationship($name,$model);
                $fake->setSideFromField($value);
                $datasources=$fake->getDatasources();
                $target=$model->getField($value)->getRemoteModel();
                foreach($datasources as $value)
                    $this->addDatasource($value);
            }
        }*/
    }
}




?>


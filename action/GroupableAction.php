<?php
namespace lib\action;

class GroupableActionException extends \lib\model\BaseException
{
    const ERR_NOT_DEFINED_SCOPE = 1;
    const ERR_NOT_MODEL_AND_DATASOURCE = 2;
}

class GroupableAction extends \lib\action\Action
{
    const SCOPE_DATA_GROUP_MEMBER = 1;
    const SCOPE_DATA_GROUP = 2 ;
    const SCOPE_SELECTION = 3 ;

    var $scope;
    var $idGroup;
    var $idGroupMember;
    var $modelName;
    var $datasource;
    var $dsParams;
    function validate($params, $actionResult, $user)
    {
        if (!$params->id_group && !$params->id_group_member && !$params->datasource) {
            $e = new \lib\action\GroupableActionException(\lib\action\GroupableActionException::ERR_NOT_DEFINED_SCOPE);
            $actionResult->addGlobalError($e);
        }

        //Set the scope
        if ($params->id_group) {
            $this->scope = self::SCOPE_DATA_GROUP;
            $this->idGroup = $params->id_group;
        }
        else {
            if ($params->id_group_member) {
                $this->scope = self::SCOPE_DATA_GROUP_MEMBER;
                $this->idGroupMember = $params->id_group_member;
            }
            else {
                //Check that we have at least model and datasource
                if (!$params->model_name && !$params->datasource) {
                    $e = new \lib\action\GroupableActionException(\lib\action\GroupableActionException::ERR_NOT_MODEL_AND_DATASOURCE);
                    $actionResult->addGlobalError($e);
                }

                $this->scope = self::SCOPE_SELECTION;
                $this->modelName = $params->model_name;
                $this->datasource = $params->datasource;
                $this->dsParams = $params->ds_params;
            }
        }


        return $actionResult->isOk();
    }

    function onSuccess($model, $user)
    {
        $def = $this->definition;
        if (! isset($def['GROUPABLE_OPTIONS'])) {
            return;
        }

        $gOptions = $def['GROUPABLE_OPTIONS'];
        if ($this->scope == self::SCOPE_DATA_GROUP_MEMBER || $this->scope == self::SCOPE_SELECTION) {
            $ds = $this->getDataSource($this->scope, $gOptions, $this->idGroupMember, $this->datasource, $this->dsParams);
            $this->executeStuff($ds, $gOptions);
        }
        else {
            //Iterar sobre todos los DS
            $dsDg = \getDataSource('data_group\data_group_member', 'FullList');
            $dsDg->id_group = $this->idGroup;
            $it = $dsDg->fetchAll();
            $data = $it->getFullData();
            foreach ($data as $dt) {
                $params = $dt['params'];
                $paramsDef = array(
                    'MODEL'=>'data_group\data_group_member',
                    'FIELD'=>'params'
                );
                $paramsType = \lib\model\types\TypeFactory::getType(null, $paramsDef);
                $paramsType->setValue($params);
                $params = $paramsType->getUnserializedValue();
                $ds = $this->getDataSource($this->scope, $gOptions, $dt['id_group_member'], $dt['datasource'], $params);
                $this->executeStuff($ds, $gOptions);
            }
        }
    }

    function executeStuff($ds, $options)
    {
        if (isset($options['EXECUTE_METHOD'])) {
            $this->executeMethod($ds, $options);
        }
        if (isset($options['EXECUTE_QUERY'])) {
            $this->executeQuery($ds, $options);
        }
    }

    function getDataSource($scope, $options, $idGroupMember, $datasource, $dsParams)
    {
        if ($scope == self::SCOPE_SELECTION) {
            $ds = \getDataSource($this->modelName, $this->datasource);
            foreach($this->dsParams as $key=>$value) {
                $ds->{$key} = $value;
            }
        }
        else {
            $dsDgm = \getDataSource('data_group/data_group_member', 'FullView');
            $dsDgm->id_group_member = $idGroupMember;
            $it=$dsDgm->fetchAll();
            $dgm = $it[0];
            $dgmDef = $dgm->getDefinition();
            $objectType = \lib\model\types\TypeFactory::getType(null, $dgmDef['FIELDS']['object']);
            $objectType->setValue($dgm->object);
            $object = $objectType->getLabel();
            $dsParams = $dgm->params;

            $ds = \getDataSource($object, $dgm->datasource);
            foreach($dsParams as $key=>$value) {
                $ds->{$key} = $value;
            }
        }

        //TODO: Evitar que casque si el DS no soporta el parámetro indicado
        if (isset($options['DS_EXTRA_PARAMS'])) {
            foreach($options['DS_EXTRA_PARAMS'] as $key=>$value) {
                $ds->{$key} = $value;
            }
        }

        return $ds;
    }

    function executeMethod($ds, $options)
    {
        $model = \getModel($options['EXECUTE_METHOD']['MODEL']);
        $method = $options['EXECUTE_METHOD']['METHOD'];
        $params = $options['EXECUTE_METHOD']['PARAMS'];
        $model::$method($ds, $params);
    }

    function executeQuery($ds, $options)
    {

        $dsQuery = $ds->getBuiltQuery();
        $dsQuery = str_replace('SQL_CALC_FOUND_ROWS', '', $dsQuery);

        $query = $options['EXECUTE_QUERY']['BASE'];

        //Change fields
        if ($options['EXECUTE_QUERY']['FIELDS']) {
            $i = 0;
            foreach($options['EXECUTE_QUERY']['FIELDS'] as $field) {
                if (isset($this->{$field})) {
                    $query = str_replace('[#'.$i.'#]', "'".$this->{$field}."'", $query);
                }
                $i++;
            }
        }

        $query = str_replace('[%DS_QUERY%]', $dsQuery, $query);

        $ins=getModel($options['EXECUTE_QUERY']['MODEL']);
        $ser=$ins->__getSerializer($options['EXECUTE_QUERY']['SERIALIZER']);
        $ser->getConnection()->doQ($query);
    }


}

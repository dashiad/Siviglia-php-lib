<?php
include_once(LIBPATH."/model/permissions/AclManager.php");
class PermissionsManager {
    static $aclManager;
    var $currentUserProfiles;
    static $permCache=array();
    var $effectiveProfiles;
    function __construct($serializer) {
        if(!PermissionsManager::$aclManager)
            PermissionsManager::$aclManager=new AclManager($serializer);
        }

    function getPermissionsOverModel($userId,$objName,$model)
    {
        if($model)
            $loaded=$model->isLoaded();
        else
            $loaded=false;

        if($loaded)
            $keyPart=implode("_",$model->__getKeys()->get());
        else
            $keyPart="NOKEYS";
            
            if(PermissionsManager::$permCache[$userId][$objName][$keyPart])
            {             
                
                $cached=PermissionsManager::$permCache[$userId][$objName][$keyPart];                    
                    if(isset($cached))
                        return $cached;
           }        
        $permissions=PermissionsManager::$aclManager->getUserPermissions($model->__getObjectName(),
                                                                            ($loaded?PermissionsManager::$aclManager->getModelId($model):null),
                                                                            $userId);
        
                
        PermissionsManager::$permCache[$userId][$objName][$keyPart]=$permissions;
        return $permissions;
    }

    
    // The item parameter can be :
    //      a BaseModel instance
    //      an array("ITEM"=>x,"GROUP"=>y)
    //      a string (equivalent to array("ITEM"=>x))
    function canAccess($reqPermission, $item,$userId=null)
    {
        global $oCurrentUser;
        if (is_object($item))
            return $this->canAccessModel($item,$reqPermission);
        else
        {
            if (is_array($item))
            {
                $axo = $item;
            }
            else
            {
                if($item)
                    $axo = array("ITEM" => $item);
                else
                    $axo=null;
            }
        }
        $aro=array("GROUP"=>"Users");
        
        if(!$userId)
        {
            global $oCurrentUser;
            if($oCurrentUser->isLogged())
                $aro["ITEM"]=$oCurrentUser->getId();
            else
                $aro["GROUP"]="Anonymous";
            
        }
        else
        {
            $aro["ITEM"]=$userId;
        }
        return PermissionsManager::$aclManager->acl_check(array("ITEM" => $reqPermission), $aro, $axo);
    }
    
    function canAccessModel(& $model,$requiredPermission,$userId=null) {

        $objName=$model->__getObjectName();
        if(!is_array($requiredPermission))
            $requiredPermission=array($requiredPermission);
        if(!$userId)
        {
            global $oCurrentUser;
            $userId=$oCurrentUser->getId();
        }
        

        foreach($requiredPermission as $req)
        {
            if(!$req)
            {
                continue;
            }
            if($req=="_PUBLIC_")
                return true;
            
            if(!$userId)
            {
                $userId=0;
            }
                //continue;
            
            if($req=="_LOGGED_" && $userId)
                return true;
                
            if($req=="_OWNER_")
            {
                 if($model->isLoaded())
                 {
                     $owner=$model->getOwner();
                     if($owner && $owner==$userId)
                        return true;
                 }
            }
            
            if(!is_array($req))
            {
                
                $perms=$this->getPermissionsOverModel($userId,$objName,$model);

                if(in_array($req,$perms))
                    return true;
            }
            // If $req is an array, it has the following keys:MODEL,FIELD,PERMISSION
            // Meaning this method will check for PERMISSIONS over MODEL, thru relation FIELD
            //. This is, if we have 2 objects, "a" and "b", and "a" has a relation with "b" thru the field "a1",
            //  a possible spec would be array(MODEL=>b, FIELD=>a1, PERMISSION=CREATE);In this case, this method
            // was called with an "a" instance.
            if(is_array($req))
            {
                if($req["MODEL"])
                {
                    $curName=$req["MODEL"];
                    $curModel=\lib\model\BaseModel::getModelInstance($curName);
                }
                else
                {
                    if($req["FIELD"])
                    {
                        if($model->isLoaded())
                            {
                                // TODO: Excepcion si field no existe.
                                $field=$model->__getField($req["FIELD"]);
                                if(!$field)
                                    continue;
                                if(!is_a($field,'\lib\model\ModelBaseRelation'))
                                    continue;
                                $curModel=$model->{$req["FIELD"]};
                                $curName=$curModel->__getObjectName();
                                
                            }
                        
                    }
                }

                if($this->canAccessModel($curModel,is_array($req["PERMISSION"])?$req["PERMISSION"]:array($req["PERMISSION"]),$userId))                
                        return true;
                
            }

        }  // Fin del foreach
        // Si no hay ninguna definicion de permisos, se retorna true
        return false;
    }

    function getFilteringCondition($model,$user,$requiredPerm="READ",$prefix="") {

        global $oCurrentUser;
        if($oCurrentUser->hasFullPermissions())
            return '';
        if(!is_object($model))
        {
            include_once(PROJECTPATH."/objects/".$model."/".$model.".php");
            $model=new $model();
        }
        global $website;
        // Se obtiene una lista completa de los perfiles.
        $effectiveProfiles=array("ANONYMOUS","LOGGED","OWNER");
        // Se une el tipo de usuario actual
        if($website["USER_TYPES"]) {
            $effectiveProfiles[]=$website["USER_TYPES"][$oCurrentUser->getUserType()];
        }

        $modelDef=$model->getDefinition();

        if($modelDef["OWNERSHIP"] && $oCurrentUser->isLogged() && $website["USER_PROFILES"]) {
            $ownerProfiles=$website["USER_PROFILES"];
            $effectiveProfiles=array_merge($effectiveProfiles,array_keys($ownerProfiles));
        }
        // El permiso implicito para que un elemento salga en una lista, es que el usuario
        // tenga permisos $requiredPerm sobre los items.
        // Por lo tanto, hay que obtener todos los perfiles posibles del usuario, ver cuales de
        // ellos le dan permiso $requiredPermission, y crear una expresion SQL con ellos.
        $permsDefinition=$model->getDefaultPermissions();
        $states=$model->getStates();
        $usedStates=null;
        global $website;
        $userPermissions=array();
        $this->loadRolePermissions($website["DEFAULT_USER_PROFILES"],$effectiveProfiles,$userPermissions);
        if($website["USER_PROFILES"])
            $this->loadRolePermissions($website["USER_PROFILES"],$effectiveProfiles,$userPermissions);

        $modelDefaultPermissions=$model->getDefaultPermissions();
        if($modelDefaultPermissions)
            $this->loadRolePermissions($modelDefaultPermissions,$effectiveProfiles,$userPermissions);
            
        $defaultExpr=$this->getSQLSubExpression($userPermissions,$requiredPerm,$ownerProfiles,$modelDef,$prefix);
        
        // Hasta aqui, tenemos un array constante de permisos.
        // En caso de que haya estados, el array anterior hay que seguir procesandolo con cada uno de los estados.
        if(!$states)
            return $defaultExpr;

        $usedStates=array();
        $stateField=$states["FIELD"];
        $permValue=0;
        foreach($states["STATES"] as $name=>$definition) {
            if($definition["PERMISSIONS"]) {
                $statePermissions=$userPermissions;
                $this->loadRolePermissions($definition["PERMISSIONS"],$effectiveProfiles,$statePermissions);

                $expr=$this->getSQLSubExpression($statePermissions,$requiredPerm,$ownerProfiles,$modelDef,$prefix);
                if($expr!=="") {
                    if($expr==null) {
                        // Este estado no se puede ver en absoluto.
                        // Por ello, se indica en $usedStates que este estado ha sido utilizado,
                        // pero no se incluye ninguna expresion que permita seleccionarlo.
                    }
                    else
                        $queryParts[]="($stateField=".$permValue." AND (".$expr."))";

                    $usedStates[]=$permValue;
                }
            }
            $permValue++;
        }
        if(count($usedStates)==0)
            $expr=$defaultExpr;
        else {
            $expr="($defaultExpr AND $stateField NOT IN (".implode(",",$usedStates)."))";
            if(is_array($queryParts))
                $expr.=" OR ".implode(" OR ",$queryParts);
        }
        return $expr;

    }
    private function getSQLSubExpression(& $perms,$requiredPerm,& $ownerProfileType,& $modelDef,$prefix="") {

        global $oCurrentUser;
        $subParts=array();
        $possibleOwners=array();
        $noOwners="";

        foreach($perms as $key=>$value) {            
            if(!in_array($requiredPerm,$value["ALLOW"]))
                continue;
            switch($key) {
                case "OWNER": {                        
                        if($oCurrentUser->isLogged()) {                            
                            $possibleOwners[]=$oCurrentUser->getId();
                        }
                        else
                            $noOwners="1 = -1";

                    }break;
                case "LOGGED": {
                        return "";
                    }break;
                case "ANONYMOUS": {
                        return "";
                    }break;
                case "ROOT": {
                        return "";
                    }break;
                default: {

                        // Para el resto de los roles, o es un USER_TYPE, o es un perfil basado en propiedad indirecta.
                        if($ownerProfileType[$key])
                        {
                            $parentUser=$oCurrentUser->data[$ownerProfileType[$key]["PARENT_USER"]];
                            if($parentUser)
                            $possibleOwners[]=$parentUser;
                        }
                        else {
                            if($oCurrentUser->isUserType($key))
                                return "";
                        }
                    }break

                    ;
            }
        }
        
        if(count($possibleOwners)>0)
            $subParts[]=$this->getSQLOwnershipExpression($modelDef,$possibleOwners,$prefix);
        else
        {
            if($noOwners!="")
                $subParts[]=$noOwners;
        }
        if(count($subParts)==0)
            return null;

        return implode(" OR ",$subParts);
    }
    function getSQLOwnershipExpression(& $modelDef,$possibleOwners,$prefix="") {
        if($modelDef["OWNERSHIP"]) {
            if(!is_array($modelDef["OWNERSHIP"]))         
            {
                return ($prefix==""?"":$prefix.".").$modelDef["OWNERSHIP"]." IN (".implode(",",$possibleOwners).")";
            }
            else {
                // Esta solucion solo permitira que OWNERSHIP se refiera, como maximo,
                // a una tabla que esta a 1 salto de nosotros.Es decir, teniendo A con referencia
                // a B, A podra declarar que el campo que contiene al owner, es un campo de B.
                $remoteObject=$modelDef["OWNERSHIP"]["OBJECT"];
                $localField=$modelDef["OWNERSHIP"]["LOCALFIELD"];
                // Se crea un modelo dummy para obtener informacion sobre ese objeto.
                include_once(PROJECTPATH."/objects/".$remoteObject."/".$remoteObject.".php");
                $dummyModel=new $remoteObject();
                $ownershipField=$dummyModel->getOwnershipField();
                $indexFields=$dummyModel->getIndexFields();

                return ($prefix==""?"":$prefix.".").$localField." IN (SELECT ".$indexFields." FROM ".$dummyModel->getTableName()." WHERE $ownershipField IN (".implode(",",$possibleOwners)."))";
            }
            // Falta la parte de los permisos heredados!!
        }
    }
}
global $oPermissions;

?>

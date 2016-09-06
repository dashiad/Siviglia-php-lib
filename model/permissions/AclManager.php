<?php

class AclException extends \lib\model\BaseException
{

    const ERR_GROUP_ALREADY_EXISTS = 1;
    const ERR_GROUP_DOESNT_EXIST = 2;
    const ERR_ITEM_NOT_FOUND = 3;
    const ERR_ITEM_ALREADY_EXIST = 4;
    const ERR_INVALID_ACL_SPECIFICATION = 5;
    const ERR_ACO_NOT_FOUND = 6;
    const ERR_ARO_NOT_FOUND = 7;

}

class AclManager
{

    const ARO = 0;
    const ACO = 1;
    const AXO = 2;

    var $conn;

    function __construct($serializer = null)
    {
        if (!$serializer)
        {
            global $APP_NAMESPACES;
            if(in_array("web",$APP_NAMESPACES))
                $tdb="web";
            else
                $tdb=$APP_NAMESPACES[0];
            $serializer = Registry::$registry["serializers"][$tdb];
        }

        $this->itemTypes = array("aro", "aco", "axo");
        $this->conn = $serializer->getConnection();
        
    }

    function install()
    {
        // Los campos "TYPE" en grupos,items,etc, significan : 0: ARO, 1:ACO, 2:AXO
        // Primera tabla : grupos. 
        $q[] = "CREATE TABLE IF NOT EXISTS _permission_groups (id smallint(8) AUTO_INCREMENT PRIMARY KEY NOT NULL,group_name varchar(30),group_type smallint(2),group_parent smallint(8) DEFAULT 0,group_path varchar(200),KEY USING HASH(group_type,group_name,group_parent),KEY(group_name,group_path))";
        $q[] = "CREATE TABLE IF NOT EXISTS _permission_items (id smallint(8) AUTO_INCREMENT PRIMARY KEY NOT NULL,item_type smallint(2),item_name varchar(20),item_value varchar(50),KEY USING HASH(item_type,item_name,item_value))";
        $q[] = "CREATE TABLE IF NOT EXISTS _permission_group_items (group_id smallint(8),item_id smallint(8),KEY (group_id),KEY(item_id))";
        $q[] = "CREATE TABLE IF NOT EXISTS _permissions (id smallint(8) AUTO_INCREMENT PRIMARY KEY,aro_type smallint(1),aco_type smallint(1),aro_id smallint(8),aco_id smallint(8),axo_type smallint(1) DEFAULT 0,axo_id smallint(8) DEFAULT 0,allow smallint(1) DEFAULT 1,enabled smallint(1) DEFAULT 1,ACLDATE TIMESTAMP,UNIQUE KEY(aro_type,aro_id,aco_type,aco_id,axo_type,axo_id))";
        $this->conn->batch($q);
    }

    function uninstall()
    {
        $q[] = "DROP TABLE IF EXISTS _permission_groups";
        $q[] = "DROP TABLE IF EXISTS _permission_items";
        $q[] = "DROP TABLE IF EXISTS _permission_group_items";
        $q[] = "DROP TABLE IF EXISTS _permissions";

        $this->conn->batch($q, true);
    }

    // This method is used to load a set of items from an array.
    // The item's sections are set to the name of its parent group, or to its type name.
    function createPermissions(& $currentArr, $section, $group, $type)
    {
        if (!$section)
        {
            $keys = array_keys($currentArr);
            for ($k = 0; $k < count($keys); $k++)
            {
                $name = $keys[$k];

                if (!is_array($currentArr[$name]))
                {
                    // Es un item.Hay que incluirlo como item, con nombre de seccion, el nombre del tipo
                    $this->add_object($type, $currentArr[$name], $type);
                }
                else
                {
                    // Hay que crear este grupo.
                    $gId = $this->add_group($name, 0, $type);
                    $this->createPermissions($currentArr[$name], $name, $gId, $type);
                }
            }
            return;
        }
        if (!is_array($currentArr))
        {
            // Si un item no es un array, es que es un elemento simple.Hay que crearlo primero,
            // y luego aniadirlo al grupo.El metodo add_object ya se encarga de chequear que el 
            // objeto ya exista...
            $id = $this->add_object($section, $currentArr, $type);
            if ($group)
                $this->add_group_object($group, $section, $currentArr);
            return;
        }
        $keys = array_keys($currentArr);
        for ($k = 0; $k < count($keys); $k++)
        {
            $name = $keys[$k];
            $cgroup = $group;
            if (is_array($currentArr[$name]))
            {
                $cgroup = $this->add_group($name, $group, $type);
            }
            $this->createPermissions($currentArr[$name], $section, $cgroup, $type);
        }
    }

    /**
     * add_acl()
     *
     * $aco, $aro and $axo are associative arrays, especifying to which GROUPS and ITEMS are we referring to.
     * Each one of them are, again, associative arrays.
     * The GROUPS sub-array has the form : [root-group]=>array("[group-name-1]","[group-name-2]"....)
     * The ITEMS sub-array has the form : [item-name(section)]=>array("[item-value]","[item-value]"....)
     *  ("GROUPS"=>array("<root-group>"=>array("<group-1>"...."<group-n>"),"<root-group>"=>....),
     *   "ITEMS"=>array("<name-1>"=>array("<value-1>"..."<value-n>"),"<name-2>"=>array("<value-1>"
     *  No numeric id's are needed.They're resolved from their names.
     */
    function add_acl($aco, $aro, $axo = NULL, $allow = 1, $enabled = 1)
    {

        $itemTypes = & $this->itemTypes;
        for ($k = 0; $k < count($itemTypes); $k++)
        {

            $curItem = $itemTypes[$k];
            $curVal = $$curItem;            
            if (!$curVal)
                continue;
            if ($curVal["ITEMS"])
            {                              
                $expr="'".(is_array($curVal["ITEMS"])?implode("','", $curVal["ITEMS"]):$curVal["ITEMS"])."'";
                    
                $q = "SELECT 0 as type,id FROM _permission_items WHERE item_type=" . $k . " AND item_value IN (" . $expr . ")";
            }
            else
            {
                $expr="'".(is_array($curVal["GROUPS"])?implode("','", $curVal["GROUPS"]):$curVal["GROUPS"])."'";
                $q = "SELECT 1 as type,id FROM _permission_groups WHERE group_type=" . $k . " AND group_name IN (" . $expr . ")";
            }


            $sources[$curItem] = "(" . $q . ") " . $curItem;
            $fields[] = $curItem . ".type as " . $curItem . "_type," . $curItem . ".id as " . $curItem . "_id";
        }
        $fullQuery = "SELECT " . implode(",", $fields) . ",$allow as allow,$enabled as enabled FROM " . $sources["aro"] . " LEFT JOIN " . $sources["aco"] . " ON 1=1";
        if ($sources["axo"])
            $fullQuery.=" LEFT JOIN " . $sources["axo"] . " ON 1=1";
        
        // Se ejecuta la query, para asegurarnos de que el aco y el aro existen.
        $data = $this->conn->select($fullQuery);

        if (count($data) == 0)
            throw new AclException(AclException::ERR_INVALID_ACL_SPECIFICATION);
        $fieldNames = null;
        $fieldValues = array();
        for ($k = 0; $k < count($data); $k++)
        {
            if (!$fieldNames)
                $fieldNames = array_keys($data[$k]);
            if ($data[$k]["aco_id"] == 0)
                throw new AclException(AclException::ERR_ACO_NOT_FOUND, array("aco" => $aco));

            if ($data[$k]["aro_id"] == 0)
                throw new AclException(AclException::ERR_ARO_NOT_FOUND, array("aro" => $aro));

            $fieldValues[] = "(" . implode(",", array_values($data[$k])) . ")";
        }
        $insertQuery = "INSERT INTO _permissions (" . implode(",", $fieldNames) . ") VALUES " . implode(",", $fieldValues);
        $this->conn->insert($insertQuery);
    }

    /**
     * del_acl()
     * Deletes a given ACL
     */
    function del_acl($acl_id)
    {
        $this->conn->delete("DELETE FROM _permissions where id in ('" . implode("','", (array) $acl_id) . "')");
    }

    function getRootGroupId($group_name, $type = AclManager::ARO)
    {
        $results = $this->conn->select("SELECT id FROM _permission_groups WHERE group_name='$group_name' AND group_parent=0 AND group_type=$type");
        if (!$results[0]["id"])
        {
            throw new AclException(AclException::ERR_GROUP_DOESNT_EXIST);
        }
        return $results[0]["id"];
    }

    /**
     * get_group_id()
     * Gets the group_id given the name or value.
     */
    function get_group_id($group_root, $group_name, $type = AclManager::ARO)
    {
        $pId = 0;
        if ($group_root)
            $pId = $this->getRootGroupId($group_root, $type);
        if ($pId)
            $q = "SELECT id FROM _permission_groups where group_name='$group_name' AND group_path LIKE '" . $pId . ",%'";
        else
            $q = "SELECT id FROM _permission_groups where group_name='$group_name' AND group_type='$type'"; // AND group_path=id";
        $results = $this->conn->select($q);
        if (!$results[0]["id"])
            throw new AclException(AclException::ERR_GROUP_DOESNT_EXIST);
        return $results[0]["id"];
    }

    /**
     * get_group_parent_id()
     * Grabs the parent_id of a given group
     */
    function get_group_parent_id($group_id)
    {
        $results = $this->conn->select("SELECT group_parent FROM _permission_groups WHERE id=$group_id");
        if (!$results[0]["group_parent"])
            throw new AclException(AclException::ERR_GROUP_DOESNT_EXIST);

        return $results[0]["group_parent"];
    }

    /**
     * add_group()
     * Inserts a group, defaults to be on the "root" branch.
     * The path is supposed to be in the form : "/a/b/c/d"
     * All paths must be absolute.
     */
    function getGroupFromPath($path, $type = AclManager::ARO)
    {
        // Si es una cadena, se espera que sean nombres de grupos separados por "/", que
        // es mas intuitivo que por comas.
        $parts = explode("/", $path);
        $currentPath = "";
        $lastId = 0;
        // El path no era absoluto...esto se considera un error.
        for ($k = 1; $k < count($parts); $k++)
        {
            if ($currentPath != "")
                $currentPath.=",";
            $q = "SELECT id from _permission_groups WHERE group_name='" . $parts[$k] . "' AND group_type=$type";
            

            if ($currentPath != "")
                $q.=" AND group_path like '," . $currentPath . "%'";
            
            $arr = $this->conn->select($q);
            

            if (!$arr)
            {
                echo $q;
                throw new AclException(AclException::ERR_GROUP_DOESNT_EXIST);
            }
            $currentPath.=$arr[0]["id"];
            $lastId = $arr[0]["id"];
        }
        
        return $lastId;
    }

    // Group parent is the id of the parent group (not the path).
    // If set to 0, it's a root group
    function add_group($group_name, $group_parent = 0, $type = AclManager::ARO)
    {
        $path = "";
        $q = "SELECT id from _permission_groups WHERE group_type=$type AND group_name='$group_name' AND " . ($group_parent ? "group_path LIKE ',$group_parent,%'" : "group_parent=0");
        $result = $this->conn->select($q);
        if (count($result) > 0)
        {
            throw new AclException(AclException::ERR_GROUP_ALREADY_EXISTS);
        }
        if ($group_parent)
        {

            // Buscamos que tipo de grupo es el padre...Y asi lo pasamos al hijo.
            $q = "SELECT group_type,group_path from _permission_groups where id=$group_parent";
            $result = $this->conn->select($q);
            if (count($result) != 1)
            {
                throw new AclException(AclException::ERR_GROUP_DOESNT_EXIST);
            }
            $type = $result[0]["group_type"];
            $path = $result[0]["group_path"] . ",";
        }

        if (!$group_parent)
        {
            $group_parent = 0;
            $path=",";
        }
        // Lo insertamos primero sin especificar el path.Una vez tengamos el id del nuevo grupo,
        // se construye el path.
        $group_id = $this->conn->insert("INSERT INTO _permission_groups (group_type,group_name,group_parent) VALUES ($type,'$group_name',$group_parent)");
        $path.=$group_id;
        $this->conn->update("UPDATE _permission_groups SET group_path='$path' WHERE id='$group_id'");
        return $group_id;
    }

    function __itemIdFromGroupAndId($group_id, $item_name, $item_value = "")
    {

        // From the group id, we'll know what kind of object (aro,aco,axo) is this.

        $gData = $this->conn->select("SELECT group_type FROM _permission_groups WHERE id=$group_id");
        if (count($gData) == 0)
            throw new AclException(AclException::ERR_GROUP_DOESNT_EXIST);

        $q = "SELECT id FROM _permission_items WHERE item_value='$item_value' AND item_type=" . $gData[0]["group_type"];
        $arr = $this->conn->select($q);
        if (count($arr) == 0)
            throw new AclException(AclException::ERR_ITEM_NOT_FOUND, array("group_id" => $group_id,
                "name" => $item_name,
                "value" => $item_value));
        return $arr[0]["id"];
    }

    /**
     * add_group_object()
     * Assigns an Object to a group
     */
    function add_group_object($group_id, $item_name_or_id, $item_value = "")
    {

        if ($item_value != "")
        {
            $item_id = $this->__itemIdFromGroupAndId($group_id, $item_name_or_id, $item_value);
        }
        else
            $item_id = $item_name_or_id;

        $res = $this->conn->select("SELECT group_id from _permission_group_items WHERE item_id=$item_id AND group_id=$group_id");
        if (count($res) == 0) // No existia la asignacion de item a grupo.
            $this->conn->insert("INSERT INTO _permission_group_items (group_id,item_id) VALUES ($group_id,$item_id)");

        return $item_id;
    }

    /**
     * del_group_object()
     * Removes an Object from a group.
     */
    function del_group_object($group_id, $item_name_or_id, $item_value = "")
    {

        $item_id = $this->__itemIdFromGroupAndId($group_id, $item_name_or_id, $item_value);
        $this->conn->delete("DELETE FROM _permission_group_items WHERE group_id=$group_id AND item_id=$item_id");
    }

    function rename_group($group_id, $newName)
    {
        
    }

    function reparent_group($group_id, $newParentId)
    {
        
    }

    /**
     * del_group()
     * deletes a given group
     * If reparent_children is true, the group childs are moved to their grandparent.If not, they're removed also.
     * All the acls referred to those groups are deleted too.
     */
    function del_group($group_id, $reparent_children = TRUE)
    {

        if (!$group_id)
            return;
        // Primero, obtenemos informacion del grupo:
        $groupInfo = $this->conn->select("SELECT * FROM _permission_groups WHERE id=$group_id");

        if (!$groupInfo || count($groupInfo) == 0)
            throw new AclException(AclException::ERR_GROUP_DOESNT_EXIST);

        if (!$groupInfo[0]["group_parent"])
            $reparent_children = FALSE;

        if ($reparent_children)
        {
            // Se mueven todos los hijos de este grupo, al padre.
            $this->conn->update("UPDATE _permission_groups SET group_parent=" . $groupInfo[0]["group_parent"] . " WHERE group_parent=$group_id");
        }
        else
        {

                $path = "%,$group_id,%";

            $groupsToDelete = array();
            $this->conn->selectCallback("SELECT id FROM _permission_groups WHERE group_path LIKE '$path'", function($arr)
                    {
                        $groupsToDelete[] = $arr["id"];
                    });
        }
        $groupsToDelete[] = $group_id;
        $groupCad = implode(",", $groupsToDelete);
        // Eliminacion de los grupos
        $qs[] = "DELETE FROM _permission_groups WHERE id IN ($groupCad)";
        $qs[] = "DELETE FROM _permission_group_items WHERE group_id IN ($groupCad)";
        // Determinar sobre que columna tenemos que hacer match en la tabla principal.
        switch ($groupInfo[0]["group_type"])
        {
            case AclManager::ARO:
                {
                    $column = "aro";
                }break;
            case AclManager::ACO:
                {
                    $column = "aco";
                }break;
            default:
                {
                    $column = "axo";
                }
        }
        $qs[] = "DELETE FROM _permissions WHERE " . $column . "_type=1 AND " . $column . "_id IN ($groupCad)";
        $this->conn->batch($qs);
    }

    /**
     * get_object_groups()
     * Gets all groups an object is a member of.
     */
    function get_object_groups($object_id, $type = AclManager::ARO)
    {

        return $this->conn->selectColumn("SELECT group_id FROM _permission_group_items WHERE item_id=$object_id", "group_id");
    }

    /**
     * add_object()
     * Inserts a new object
     */
    function add_object($item_name, $item_value, $item_type = AclManager::ARO)
    {
        // Si ya existe, devolvemos el id del objeto existente
        $id = $this->get_object($item_value, $item_type);
        if ($id)
            return $id;
        return $this->conn->insert("INSERT INTO _permission_items (item_name,item_value,item_type) VALUES ('$item_name','$item_value',$item_type)");
    }

    function get_object($item_value, $item_type = AclManager::ARO)
    {
        $arr = $this->conn->select("SELECT id FROM _permission_items WHERE  item_value='$item_value' AND item_type=$item_type");

        if (count($arr))
            return $arr[0]["id"];
        return null;
    }

    function set_object_name($item_id, $newName)
    {
        
    }

    function set_object_value($item_id, $newValue)
    {
        
    }

    /**
     * del_object()
     */
    function del_object($item_id)
    {
        // Se obtiene informacion (tipo) del item
        $arr = $this->conn->select("SELECT * FROM _permission_items WHERE id=$item_id");
        if (count($arr) == 0)
            throw new AclException(AclException::ERR_ITEM_NOT_FOUND);

        $type = $this->itemTypes[$arr[0]["item_type"]];
        $q = array("DELETE FROM _permission_items WHERE id=$item_id",
            "DELETE FROM _permission_group_items WHERE item_id=$item_id",
            "DELETE FROM _permissions WHERE " . $type . "_type=0 AND " . $type . "_id=$item_id");
        $this->conn->batch($q);
    }

    /**
     * acl_query
     */
    /*
      aco debe contener ITEM y GROUP
      aro y axo son arrays que contienen ITEM o GROUP

     */


    function acl_check($aco, $aro, $axo = null)
    {
        
        if (!isset($aco["ITEM"]) && !isset($aco["GROUP"]))
            return false;
        $parts = array("aro", "aco", "axo");
        
        for ($k = 0; $k < count($parts); $k++)
        {
            $current = $parts[$k];
            if (!$$current)
                continue;
            $c = $$current;
            $subQ = "(";
            if ($c["ITEM"])
            {

                $subQ.='SELECT g.group_type,IF(i.id is NULL,0,i.id) as id ,g.id as group_id,group_path from _permission_groups g
                            LEFT JOIN  _permission_group_items gi ON g.id=gi.group_id
                            LEFT JOIN _permission_items i ON i.id=gi.item_id WHERE
                             item_type=' . $k . ' AND item_value=\'' . $c["ITEM"] . '\'';
                if ($c["GROUP"])
                {
                    $subQ.=' UNION SELECT g.group_type,null as id ,g.id as group_id,group_path from _permission_groups g
                             WHERE g.group_name=\'' . $c["GROUP"] . '\'';
                }
            }
            else
            {
                $subQ.="SELECT $k as item_type,null as id,g.id as group_id,group_path from _permission_groups g WHERE group_type=$k AND group_name='" . $c["GROUP"] . "'";
            }
            $subqueries[] = $subQ . ")" . $current . "s";
        }
        
        
        $q = '
        SELECT aro_type,aro_id,axo_type,allow,        
            IF(aco_type=1,aco_id,0) as acoNumber,
            IF(aro_type=1,aro_id,0) as aroNumber,
            ACLDATE FROM _permissions p,' .
                implode(",", $subqueries);

        $q.=' WHERE 
                ((aro_type=0 AND aro_id=aros.id ) OR (aro_type=1 AND LOCATE(CONCAT(\',\',CONCAT(aro_id,\',\')),CONCAT(\',\',CONCAT(aros.group_path,\',\')))))
                AND
                ((aco_type=0 AND aco_id=acos.id) OR (aco_type=1 AND LOCATE(CONCAT(\',\',CONCAT(aco_id,\',\')),CONCAT(\',\',CONCAT(acos.group_path,\',\')))))';
        if ($axo)
        {

            $q.=' AND
                ((axo_type=0 AND axo_id=axos.id) OR (axo_type=1 AND  LOCATE(CONCAT(\',\',CONCAT(axo_id,\',\')),CONCAT(\',\',CONCAT(axos.group_path,\',\')))))';
        }
        $q.=' ORDER BY (aro_type*10+aco_type),aroNumber DESC, acoNumber DESC,ACLDATE DESC LIMIT 1';     
        
        $results = $this->conn->select($q);
        // Si no hay entradas, en allow habra un 0.
        return $results[0]['allow'];
    }

    function getUserPermissions($module, $moduleItem, $userId, $userGroup = "AllUsers")
    {
        
        $aro = array("GROUP" => $userGroup, "ITEM" => $userId);
        if ($moduleItem)
            $axo = array("GROUP" => $module, "ITEM" => $moduleItem);
        else
            $axo = array("GROUP" => $module);

        $parts = array("aro", "aco", "axo");
        
        for ($k = 0; $k < count($parts); $k++)
        {
            if ($k == 1)
                continue;

            $current = $parts[$k];
            if (!$$current)
                continue;
            $c = $$current;
            $subQ = "(";
            if ($c["ITEM"])
            {

                $subQ.='SELECT g.group_type,IF(i.id is NULL,0,i.id) as id ,g.id as group_id,group_path from _permission_groups g
                            LEFT JOIN  _permission_group_items gi ON g.id=gi.group_id
                            LEFT JOIN _permission_items i ON i.id=gi.item_id WHERE
                             item_type=' . $k . ' AND item_value=\'' . $c["ITEM"] . '\'';
                if ($c["GROUP"])
                {
                    $subQ.=' UNION SELECT g.group_type,null as id ,g.id as group_id,group_path from _permission_groups g
                             WHERE g.group_name=\'' . $c["GROUP"] . '\'';
                }
            }
            else
            {
                $subQ.="SELECT $k as item_type,null as id,g.id as group_id,group_path from _permission_groups g WHERE group_type=$k AND group_name='" . $c["GROUP"] . "'";
            }
            $subqueries[] = $subQ . ")" . $current . "s";
        }
        $q = '
        SELECT IF(group_name IS NULL, pi.item_value,pii.item_value) AS name,allow FROM
        (
        SELECT aco_type,aco_id,
            IF(aco_type=1,aco_id,0) as acoNumber,
            IF(aro_type=1,aro_id,0) as aroNumber,
            allow
            FROM _permissions p,' .
                implode(",", $subqueries);

        $q.=' WHERE 
                ((aro_type=0 AND aro_id=aros.id ) OR (aro_type=1 AND LOCATE(CONCAT(\',\',CONCAT(aro_id,\',\')),CONCAT(\',\',CONCAT(aros.group_path,\',\')))))
             AND
                ((axo_type=0 AND axo_id=axos.id) OR (axo_type=1 AND  LOCATE(CONCAT(\',\',CONCAT(axo_id,\',\')),CONCAT(\',\',CONCAT(axos.group_path,\',\')))))
                ORDER BY (aro_type*10+aco_type),aroNumber DESC, acoNumber DESC,ACLDATE DESC 
             ) p LEFT JOIN

                 _permission_groups pg ON (pg.id=aco_id OR pg.group_path LIKE CONCAT(\'%,\',CONCAT(aco_id,\',%\'))) AND pg.group_type=1 AND aco_type=1 LEFT JOIN
                _permission_group_items pgi ON pgi.group_id=pg.id LEFT JOIN _permission_items pii ON pgi.item_id=pii.id LEFT JOIN
                _permission_items pi ON pi.id=aco_id AND pi.item_type=1 AND aco_type=0 WHERE not(pi.item_value IS NULL AND pii.item_value IS NULL)';
        $results = $this->conn->select($q);
        if (!$results)
            return array();
        $perms = array();
        $allowed = array();
        foreach ($results as $k)
        {
            if ($perms[$k["name"]])
                continue;
            if ($k["allow"])
                $allowed[] = $k["name"];
            $perms[$k["name"]] = 1;
        }
        
        return $allowed;
        // Si no hay entradas, en allow habra un 0.
        //return $results[0]['allow'];
    }

    /*

      Las siguientes funciones son los principales metodos publicos para acceder al sistema de permisos
     */

    // A partir de una instancia del modelo , devuelve una especificacion de permisos
    function getModelPermissionSpec($item)
    {
        return array("ITEM" => $item->__getObjectName() . implode("_", $item->__getKeys()->get()),
            "GROUP" => $item->__getObjectName());
    }

    function canAccess2($reqPermission, $model, $item)
    {
        global $oCurrentUser;
        if ($item)
            $axo = $acl->getModelPermissionSpec($item);
        else
        {
            if ($model)
            {
                $objName = new \lib\reflection\model\ObjectDefinition($model);
                $axo = array("GROUP" => $item);
            }
            else
                $axo = null;
        }

        return $acl->acl_check(array("ITEM" => $reqPermission), array("ITEM" => $oCurrentUser->getId(), "GROUP" => "Users"), $axo);
    }

    function canAccess($permsDefinition, $user, $model = null)
    {
        $permsObj = new \lib\reflection\permissions\PermissionRequirementsDefinition($permsDefinition);
        if ($permsObj->isJustPublic())
            return true;
        if ($model)
        {
            $curState = $model->getState();
            $reqPermissions = $permsObj->getRequiredPermissionsForState($curState);
        }
        else
            $reqPermissions = $permsObj->getRequiredPermissions();

        if ($model)
        {
            $axoParam = $this->getModelPermissionSpec($model);
        }

        foreach ($reqPermissions as $key => $value)
        {
            $reqPerms = array();
            if ($value == "_PUBLIC_")
                return true;
            if ($value == "_OWNER_")
            {
                $owner = $model->getOwner();
                if (owner == $user->getId())
                    return true;
            }

            if ($this->acl_check(array("ITEM" => $value), array("ITEM" => $user->getId(), "GROUP" => "Users"), $axoParam))
                return true;
        }
        return false;
    }

    // Funcion para simplificar la busqueda de ids en los siguientes metodos.
    // Cada uno de los elementos, es un array, que contiene un elemento "ITEM" o un elemento "GROUP",  un flag "CREATE", en caso de que no se haya encontrado, y un valor "CREATEPATH" para, si se quiere crear, agregarlo a ese PATH.
    // Retorna un array con el id del ITEM y/o GROUP
    private function resolveAccessIds($aro, $aco, $axo)
    {
        foreach (array("aro", "aco", "axo") as $key => $value)
        {

            $current = $$value;

            if (!$current)
                continue;
            if ($current["ITEM"])
            {
                if ($current["CREATE"])
                {
                    $id = $this->add_object($current["NAME"], $current["ITEM"], $key);

                    if ($current["CREATEPATH"])
                    {
                        $groupId = $this->getGroupFromPath($current["CREATEPATH"], $key);
                        if (!$groupId)
                        {
                            // TODO: throw exception
                            return;
                        }
                        else
                        {
                            $this->add_group_object($groupId, $itemId);
                            $results[$value]["GROUP"] = $groupId;
                        }
                    }
                }
                else
                    $id = $this->get_object($current["ITEM"], $key);

                $results[$value]["ITEM"] = $id;
            }
            else
            {
                if ($current["GROUP"])
                {
                    if ($current["CREATE"]) // Si hay un CREATE, GROUP es simplemente un nombre. Si no, debe ser un path
                    {
                        if ($current["CREATEPATH"])
                        {
                            $groupParent = $this->getGroupFromPath($current["CREATEPATH"], $key);
                            if (!$groupParent)
                            {
                                // TODO: throw exception;
                                return;
                            }
                        }
                        else
                            $groupParent = 0;

                        $groupId = $this->add_group($current["GROUP"], $groupParent, $key);
                    }
                    else
                    {
                        $groupId = $this->getGroupFromPath($current["GROUP"], $key);
                    }
                    $results[$value]["GROUP"] = $groupId;
                }
            }
            return $results;
        }
    }

    private function getModulePath($model, $onlyParent = false)
    {
        if (is_object($model))
        {
            $objName = $model->__objectName;
        }
        else
        {
            $objName = new \lib\reflection\model\ObjectDefinition($model);
        }
        $objLayer = $objName->layer;
        if ($onlyParent)
            return "/AllObjects/Sys/" . $objLayer . "/" . $objLayer . "Modules";

        $objClass = $objName->className;
        return "/AllObjects/Sys/" . $objLayer . "/" . $objLayer . "Modules/" . $objClass;
    }

    public function getModelId($model)
    {
        $modelName = $model->__getObjectName();
        return $modelName . implode("_", array_values($model->__getKeys()->get()));
    }

    private function revokePermission($aro, $aco, $axo = null)
    {
        $result = $this->resolveAccessIds($aro, $aco, $axo);
        foreach (array("aro", "aco", "axo") as $key => $value)
        {
            $param = $$value;
            if (!$param && $value == "axo")
                break;

            // Si no se ha encontrado un elemento, se retorna.
            if (($param["ITEM"] && !$result[$value]["ITEM"]) ||
                    $param["GROUP"] && !$result[$value]["GROUP"])
                return;

            if ($param["ITEM"])
            {
                $selectParts[] = $value . "_type=0 AND " . $value . "_id=" . $result[$value]["ITEM"];
                $insertParts[] = "0," . $result[$value]["ITEM"];
            }
            else
            {
                $selectParts[] = $value . "_type=1 AND " . $value . "_id=" . $result[$value]["GROUP"];
                $insertParts[] = "1," . $result[$value]["ITEM"];
            }
            $insertFields[] = $value . "_type," . $value . "_id";
        }

        $q = "SELECT id FROM _permissions WHERE " . implode(" AND ", $selectParts) . " AND allow=1";
        $res = $this->conn->select($q);
        if (count($res) > 0)
        {
            // TODO: Y si hay mas de 1 resultado?
            $q = "UPDATE _permissions SET allow=0 WHERE id=" . $res[0]["id"];
            $this->conn->update($q);
        }
        else
        {
            // Se inserta una linea especifica denegando permisos a ese elemento.
            $q = "INSERT INTO _permissions (" . implode(",", $insertFields) . ",allow,enabled) VALUES (" . implode(",", $inserParts) . ",0,1)";
            $this->conn->insert($q);
        }
    }

    // Permissions debe ser un array
    function givePermissionOverItem($permissions, $userId, $model)
    {

        $itemValue = $this->getModelId($model);
        // Se llama a resolveAccessIds para asegurarnos de que los objetos se crean.
        $this->resolveAccessIds(array("ITEM" => $userId, "CREATE" => 1), null, array("ITEM" => $itemValue, "CREATE" => 1, "CREATEPATH" => $this->getModulePath($model)));

        $this->add_acl(
                array("ITEMS" => is_array($permissions) ? $permissions : array($permissions)), array("ITEMS" => array($userId)), array("ITEMS" => array($itemValue)));
    }

    function removePermissionOverItem($permission, $userId, $model)
    {
        // Esto es bastante mas complicado; Primero hay que ver si existe una entrada acl que explicitamente de ese permiso.Si existe, hay que cambiarla de allow, a no-allow.
        // Si no existe, hay que introducirla en la tabla.        
        $this->revokePermission(array("ITEM" => $userId), array("ITEM" => $permission), array("ITEM" => $this->getModelId($model))
        );
    }

    function addPermissionOverModule($permissions, $userId, $moduleName)
    {

        $this->add_acl(
                array("ITEMS" => is_array($permissions) ? $permissions : array($permissions)), array("ITEMS" => array($userId)), array("GROUPS" => array($moduleName)));
    }

    function removePermissionOverModule($permission, $userId, $moduleName)
    {
        $result = $this->revokePermission(array("ITEM" => $userId), array("ITEM" => $permission), array("GROUP" => $moduleName)
        );
    }

    function addGroupPermissionOverModule($permissions, $groupName, $moduleName)
    {
        $this->add_acl(
                array("ITEMS" => is_array($permissions) ? $permissions : array($permissions)), array("GROUPS" => array($groupName)), array("GROUPS" => array($moduleName))
        );
    }

    function removeGroupPermissionOverModule($permission, $groupName, $moduleName)
    {
        $this->revokePermission(array("GROUP" => $groupName), array("ITEM" => $permission), array("GROUP" => $moduleName));
    }

    function addUserToGroup($groupName, $userId)
    {
        $innerId = $this->get_object($userId);

        $result = $this->resolveAccessIds(array("ITEM" => $userId, "CREATE" => 1), null, null);

        $groupId = $this->get_group_id(null, $groupName, AclManager::ARO);
        if (!$groupId)
            return null;
        $this->add_group_object($groupId, $result["aro"]["ITEM"]);
    }

    function getUserGroups($userId)
    {
        $q = "SELECT group_name from 
            _permission_groups pg
                LEFT JOIN (SELECT CONCAT(',',CONCAT(group_path,',')) as mgroup 
                            from _permission_groups g
                                LEFT JOIN _permission_group_items gi ON g.id=gi.group_id
                                LEFT JOIN _permission_items i ON gi.item_id=i.id
                                WHERE i.item_value='" . $userId . "'
                            ) ig
                ON LOCATE(CONCAT(',',CONCAT(pg.group_path,',')),mgroup)>0 WHERE mgroup IS NOT NULL";
        return $this->conn->selectColumn($q, "group_name");
    }

    // Se busca si el usuario pertenece al grupo, o a alguno de sus padres.
    function userBelongsToGroup($groupName, $userId)
    {
        //_d($this->getUserGroups($userId));
        return in_array($groupName, $this->getUserGroups($userId));
    }

    function removeUserFromGroup($groupName, $userId)
    {
        $result = $this->resolveAccessIds(array("ITEM" => $userId, "CREATE" => 1), null, null);

        $groupId = $this->get_group_id(null, $groupName, AclManager::ARO);
        if (!$groupId)
            return null;
        $this->del_group_object($groupId, $result["ITEM"]);
    }

    function grantPermissionToGroup($groupName, $permissionName)
    {
        $this->add_acl(
                array("ITEMS" => array($permissionName)), array("GROUPS" => array($groupName)));
    }

    function revokePermissionToGroup($groupName, $permissionName)
    {
        $this->revokePermission(
                array("GROUP" => $groupName), array("ITEM" => $permissionName)
        );
    }

}

?>

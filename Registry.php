<?php
include_once(LIBPATH."/model/BaseTypedObject.php");

class RegistryPath extends \lib\model\PathObject
{
    var $registry;
    function __construct()
    {
        $this->registry=Registry::$registry;
    }
    function getPath($path,$context)
    {
        $context=new \lib\model\SimpleContext();
        return parent::getPath("/registry/".$path,$context);
    }
}
class Registry
{
    const REQUEST_LANGUAGE = "REQUEST_LANGUAGE";
    const PROJECT = "currentProject";
    const REQUEST = "request";
    const USER = "user";
    const USER_LANGUAGE_ISO = "userLangIso";
    public static $registry = null;
    public static $saved=false;

    function __construct($requestFormat = "html")
    {
        Registry::$registry = array();
    }
    static function initialize(Request $request=null)
    {
        Registry::$registry["params"]  = $request->getParameters();
        Registry::$registry["request"] = & $request;
        Registry::$registry["client"]  = $request->getClientData();
        Registry::$registry["action"]  = $request->getActionData();

    }
    static function getPermissionsManager()
    {
        if(!Registry::$registry["acl"])
        {
            include_once(PROJECTPATH."/lib/model/permissions/PermissionsManager.php");
            $oAcl=new PermissionsManager(\lib\storage\StorageFactory::getDefaultSerializer());
            Registry::$registry["acl"]=$oAcl;
        }
        return Registry::$registry["acl"];                             
    }
    static function getRequest()
    {
        return Registry::$registry["request"];
    }
    static function save()
    {
        if(Registry::$saved)
            return;
        Registry::$saved=true;

        unset($_SESSION["Registry"]["states"]);
        $_SESSION["Registry"]["states"] = Registry::$registry["states"];
        $_SESSION["Registry"]["SESSION"] = Registry::$registry["session"];

        if (Registry::$registry["newForm"])
        {
            // Si existian ficheros, hay que eliminarlos de lastAction, ya que no se pueden resetear.
            if(isset($_FILES) &&  !empty($_FILES))
            {                
                foreach(Registry::$registry["newForm"]["DATA"] as $key=>$value)
                {
                    if(isset($_FILES[$key]))
                    {
                        unset(Registry::$registry["newForm"]["DATA"][$key]);
                    }
                }
            }
            $_SESSION["Registry"]["lastForm"] = Registry::$registry["newForm"];
        }
        else
            unset($_SESSION["Registry"]["lastForm"]);

        if (isset(Registry::$registry["newAction"]))
        {
            
            $lastAction  = Registry::$registry["newAction"]->serialize();
            
            $_SESSION["Registry"]["lastAction"]=$lastAction;            
        }
        else
            unset($_SESSION["Registry"]["lastAction"]);

        global $oCurrentUser;
        
        unset($_SESSION["Registry"]["userId"]);
        if (isset($oCurrentUser))
        {
            if ($oCurrentUser->isLogged())
            {            
                $_SESSION["Registry"]["userId"] = $oCurrentUser->getId();

            }
        }
    }
    static function store($key, $value)
    {
        Registry::$registry[$key] = $value;
    }

    static function retrieve($key, $defaultValue=null)
    {
        if (!isset(Registry::$registry[$key])) {
            try
            {
                $obj=new RegistryPath();
                return $obj->getPath($key);
            }catch(\Exception $e)
            {
                if($defaultValue!=null)
                    return $defaultValue;
                throw new \RuntimeException('Registry invalid key');
            }
        }

        return Registry::$registry[$key];
    }
}

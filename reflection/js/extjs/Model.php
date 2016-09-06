<?php
namespace lib\reflection\js\extJs;
class Model
{
    var $model;
    var $stores;
    function __construct($model)
    {
        $this->model=$model;        
        $this->jsClassName=PROJECTNAME.".model.".$this->normalizeJsName($model);
        $this->stores=array();
    }
    function normalizeJsName($model)
    {
        if($model->objectName->isPrivate())        
            $cad=$model->objectName->getNamespaceModel().".";
        return $cad.$model->objectName->className;
    }
    static function create($layer,$objname,$model)
    {
        return new Model($model);
    }
    function generate()
    {
        $src="Ext.define('".$this->jsClassName."',{\n".
            "\textend: 'Ext.data.Model',\n";
     
        
        $keys=array_keys($this->model->getIndexFields());
        // TODO: Extjs solo va a permitir una columna indice por modelo.
        if(count($keys)>1)
        {
            // TODO : throw exception.
        }
        $src.="\tidProperty:'".$keys[0]."',\n";

        $src.="\tfields: [\n";
        $fields=$this->model->getFields();
        $validators=array();
        foreach($fields as $key=>$value)
        {
            $mappedType=$this->getExtJsType($key,$value);
            $curDef="{name:'".$key."',type:".$mappedType["NAME"].",";
            if($mappedType["DEFAULT"])
                $curDef.="defaultValue:'".$mappedType["DEFAULT"]."',";
            $curDef.=$mappedType["OPTIONS"];
            $curDef.='}';
            $fieldDefs[]=$curDef;
            if(isset($mappedType["VALIDATORS"]))
                $validators=array_merge($validators,$mappedType["VALIDATORS"]);
        }
        $src.="\t\t".implode(",\n\t\t",$fieldDefs)."\n";
        $src.="\t],\n";

        if(count($validators)>0)
        {
            $src.=",\tvalidations: [\n\t\t";
            $src.=implode("\n\t\t",$validators);
            $src.="\n\t],\n";
        }
        $assocs=$this->generateRelations();
        if(count($assocs)>0)
        {
            $src.="\tassociations:";
            $src.=json_encode($assocs,ENT_NOQUOTES);
            $src.=",\n";
        }
        $isPrivate=$this->model->objectName->isPrivate();
        
        $target=str_replace(".","/",$this->jsClassName);
        $src.="\tproxy:new Siviglia.extjs.Proxy('".$target."'),\n";        
        $src.='});';
        $this->src=$src;
    }

    function generateRelations()
    {
        $norm=$this->model->objectName->getNormalizedName();
           $fields=array_merge($this->model->getFields());
           $assocs=array();
        foreach($fields as $key=>$value)
        {
            
            if($value->isRelation())
            {
                $remModel=$value->getRemoteModel();
                $remModelObjName=$this->normalizeJsName($remModel);                
                $role=$value->getRole();
                $remoteFields=$value->getRemoteFieldNames();
                $localFields=$value->getLocalFieldNames();
                $remoteField=$remoteFields[0];
                $localField=$localFields[0];

                $assocName=$localField;

                switch($role)
                {
                case "HAS_ONE":{
                    $curAssoc=array("type"=>"hasOne","model"=>$remModelObjName,"foreignKey"=>$remoteField,"associationKey"=>$assocName);                    
                    
                }break;                
                case "BELONGS_TO":{
                    $curAssoc=array("type"=>"belongsTo","model"=>$remModelObjName,"associatedName"=>$assocName,"foreignKey"=>$remoteField);
                }break;
                case "HAS_MANY":{
                        $curAssoc=array("type"=>"hasMany","model"=>$remModelObjName,"associatedName"=>$assocName,"foreignKey"=>$remoteField);
                }break;
                default:
                    {
                        echo "UNKNOWN ASSOC";
                    }
            }
            
            // Ver : http://mytech.dsa.me/en/2011/05/13/extjs-right-way-association-bugs-proble/
                if($remModel->objectName->isPrivate())
                    {
                        $curAssoc["getterName"]="get".ucfirst($remModel->objectName->className);
                        $curAssoc["instanceName"]=$assocName;
                    }
                $assocs[]=$curAssoc;
            }
        }
        return $assocs;
    }

    function getExtJsType($name,$field)
    {        
        $type=$field->getRawType();
        $type=$type[$name];
        $type=$type->getRelationshipType();
        switch(get_class($type))
        {
        case "\\lib\\model\\types\\String":
            {
                $mappedType["NAME"]="types.STRING";

            }break;
        case "\\lib\\model\\types\\Integer":
            {
                $mappedType["NAME"]="types.INT";
            }break;
        case "\\lib\\model\\types\\Boolean":
            {
                $mappedType["NAME"]="types.BOOL";
                if($mappedType["DEFAULT"])
                    $mappedType["DEFAULT"]="true";
                else
                    $mappedType["DEFAULT"]="false";
            }break;
        case "\\lib\\model\\types\\DateTime":
            {
                $mappedType["NAME"]="types.DATE";
                $mappedType["OPTIONS"]="dateFormat:'d-m-Y H:i:s'";
            }break;
        default:
            {
                $parts=explode('\\',get_class($type));
                $typeName=strtoupper($parts[count($parts)-1]);
                $mappedType["NAME"]="types.".$typeName;
                $requiredTypes[]=array($typeName);
            }break;
        }
        $definition=$type->getDefinition();
        if($definition["REQUIRED"])
            $mappedType["VALIDATORS"][]="{type:'presence',field:'".$name."'}";
        if(is_a($type,"\\lib\\model\\types\\String"))
        {
            if($definition["MAXLENGTH"])
                $max=",max:".$definition["MAXLENGTH"];
            if($definition["MINLENGTH"])
                $min=",min:".$definition["MINLENGTH"];
            if($max || $min)
                $mappedType["VALIDATORS"][]="{type:'length',field:'".$name."'".$min.$max."}";
            if($definition["REGEXP"])
                $mappedType["VALIDATORS"][]="{type:'format',field:'".$name."',matcher:".$definition["REGEXP"]."}";
        }
        if(is_a($type,"\\lib\\model\\types\\Enum"))
        {
            $mappedType["VALIDATORS"][]="{type:'inclusion',field:'".$name."',list:['".implode("','",$type->getLabels())."']}";
        }

        
        unset($definition["TYPE"]);
        unset($definition["LABEL"]);
        unset($definition["SHORTLABEL"]);
        unset($definition["THUMBNAIL"]);
        unset($definition["TARGET_FILEPATH"]);
        unset($definition["TARGET_FILENAME"]);
        unset($definition["ISLABEL"]);
        unset($definition["DESCRIPTIVE"]);
        if(isset($definition["DEFAULT"]) && !isset($mappedType["DEFAULT"]))
            $mappedType["DEFAULT"]=$definition["DEFAULT"];
        unset($definition["DEFAULT"]);

        
        $encoding=json_encode($definition,ENT_NOQUOTES);
        $mappedType["OPTIONS"].=" extra:".$encoding;

        return $mappedType;
    }
    function save()
    {
        $target=WEBROOT."/scripts/extjs/app/model/".str_replace(".","/",$this->normalizeJsName($this->model)).".js";
        @mkdir(dirname($target),0777,true);
        file_put_contents($target,$this->src);
    }

    function addStore($name,$obj)
    {
        $this->stores[$name]=$obj;
    }
    function getStores()
    {
        return $this->stores;
    }
}
?>

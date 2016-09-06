<?php
namespace lib\reflection\js\extjs;


class Store
{
    var $jsName;
    var $model;
    var $jsModel;
    var $srcCode;
    var $dsName;
    function __construct($name,$datasource,$path,$model,$jsModel)
    {
        $this->name=$name;
        $this->dataSource=$datasource;
        $this->sourcePath=$path;
        $this->jsName=PROJECTNAME.".store.".$jsModel->normalizeJsName($model).".".$name;
        $this->model=$model;
        $this->jsModel=$jsModel;
    }
    static function create($name,$datasource,$path,$model,$jsModel)
    {
        return new Store($name,$datasource,$path,$model,$jsModel);
    }
    function generate()
    {
        $storeName=$this->jsName;
        $modelName=PROJECTNAME.".model.".$this->jsModel->normalizeJsName($this->model);
        $url=$this->sourcePath;
        $this->srcCode=<<<"SOURCE"
Ext.define('$storeName', {
    extend: 'Ext.data.Store',
    model:'$modelName',
    autoLoad:true,
    proxy:
    {
        type:'ajax',
        url:Siviglia.GLOBALS.WebPath+'$url?output=json'),
        reader:{
               type:'json',
               root:'data',
               successProperty:'success'
              }        
    },
    listeners:
    {
        load:function( store, records, successful, eOpts ){}
    }
});
SOURCE;
    }

    function save()
    {
        $target=WEBROOT."/scripts/extjs/app/store/".str_replace(".","/",$this->jsModel->normalizeJsName($this->model))."/".$this->name.".js";
        @mkdir(dirname($target),0777,true);
        file_put_contents($target,$this->srcCode);
    }
}

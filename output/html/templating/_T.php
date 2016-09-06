<?php
include_once(dirname(__FILE__)."/html/plugins/T.php");

class _T {
    private static $instance=null;
    private static $currentLanguage=null;
    private function __construct()
    {
        if(defined("_LOCAL_PREFIX_") && _T::$currentLanguage==null)
            _T::$currentLanguage=_LOCAL_PREFIX_;
        $this->pluginInstance=new \T(null,null,null);
        //$this->pluginInstance->loadTranslations(_LOCAL_PREFIX_);
    }

    static function getInstance()
    {
        $n=_T::$instance;
        if(_T::$instance==null)
        {
            _T::$instance=new _T();
        }
        return _T::$instance;
    }
    static function t($text,$params=null)
    {
        return _T::translate($text,$params);
    }
    static function translate($text,$params=null)
    {
        if($params==null)
            $params=_T::$currentLanguage;
        return _T::getInstance()->__translate($text,$params);
    }
    static function setLanguage($lang)
    {
        _T::$currentLanguage=$lang;
    }
    private function __translate($text,$params)
    {
        return $this->pluginInstance->getTranslationFromValue(_T::$currentLanguage,$text,$params);
        //return $this->pluginInstance->parseTranslation($params,$text,null);
    }
    static function loadTranslationFile($lang)
    {
        $pluginInstance=new T(null,null,null);
        $pluginInstance->loadTranslations($lang);
        return T::$translations[$lang];
    }

} 

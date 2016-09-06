<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 17/11/14
 * Time: 12:12
 */
include_once(dirname(__FILE__)."/../plugins/T.php");
class L2 {
    function __construct($parser,$protocol)
    {
        $this->parser=$parser;
        $this->translations=array();
    }
    function parse($content)
    {
        // Se buscan los @L aun no parseados.
        $obj= $this;
        $content=preg_replace_callback('/\[@L\](.*?)\[#\]/is',function($matches) use ($obj){
            $id=$obj->addTranslation($matches[1]);
            return "[@T][_ID]".$id."[#][_C]".$matches[1]."[#][#]";
        },$content);
        return $content;
    }
    function addTranslation($content)
    {
        $params=$this->parser->getPluginParams("L");
        $tLang=$params["lang"];
        try{
            $m=\getModel("ps_lang\\translations",array("lang"=>DEFAULT_LANGUAGE,"value"=>$content));
        }
        catch(\Exception $e)
        {
            $m=\getModel("ps_lang\\translations");
            $m->value=$content;
            $m->lang=$tLang;
            $m->id_string=md5($content);
            $m->save();
            // Tenemos que añadir esta traduccion a lo que esta usando el plugin T
            // Pero como es posible que aun no las haya cargado, las cargamos primero
            if(!$this->tPlugin)
                $this->tPlugin=new T($this->parser,null,null);
            $this->tPlugin->loadTranslations($tLang);
            // Y una vez cargadas, añadimos.
            \T::$translations[$tLang][$m->id_string]=$content;
        }
        return $m->id_string;
    }
    function getTranslations()
    {
        return $this->translations;
    }

} 
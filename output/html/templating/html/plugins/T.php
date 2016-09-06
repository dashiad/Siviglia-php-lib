<?php
/*

  Siviglia Framework templating engine

  BSD License

  Copyright (c) 2012, Jose Maria Rodriguez Millan
  All rights reserved.

  Redistribution and use in source and binary forms, with or without modification, are permitted provided that 
  the following conditions are met:

  * Redistributions of source code must retain the above copyright notice, this list of conditions and 
    the following disclaimer.
  * Redistributions in binary form must reproduce the above copyright notice, this list of conditions and 
    the following disclaimer in the documentation and/or other materials provided with the distribution.
  * Neither the name of the <ORGANIZATION> nor the names of its contributors may be used to endorse or 
    promote products derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, 
INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE 
ARE DISCLAIMED. 
IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, 
SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS 
OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, 
WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY 
OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.


Please, send bugs, feature requests or comments to: 
 
dashiad at hotmail.com 
 
You can find more information about this class at: 
 
http://xphperiments.blogspot.com 

*/
include_once(dirname(__FILE__)."/../../Plugin.php");
class T extends Plugin
{

    static $translations=null;
    static $loadedTranslations=null;
    static $defaultLangPath=null;
     function __construct($parentWidget,$layoutContents,$layoutManager)
     {
         $this->parentWidget=$parentWidget;         
         $this->layoutContents=$layoutContents;
         $this->layoutManager=$layoutManager;
     }

     function getSubElements()
     {

         $nEls=count($this->layoutContents);
         $elements=array();
         for($k=0;$k<$nEls;$k++)
         {
             $curEl=$this->layoutContents[$k];
             if(!$curEl["TYPE"]=="SUBWIDGET")
                 continue;
             $elements[$curEl["NAME"]]="";
             $text="";
             if(isset($curEl["CONTENTS"]))
             {
                 foreach($curEl["CONTENTS"] as $val)
                 {
                     if($val["TYPE"]=="HTML")
                         $text.=$val["TEXT"];
                 }
                 $elements[$curEl->name]["text"]=$text;
                 $elements[$curEl->name]["node"]=$curEl;
             }
         }
         return $elements;
     }
     function parse()
     {
        // Se utilizan los mismos parametros que en el plugin L
        $params=$this->layoutManager->getPluginParams("L");
        $tLang=$params["lang"];
        $el=$this->getSubElements();
        $id=$el["ID"]["text"];
        $textNode=$el["C"]["node"];
        $layoutContents=$textNode->contents;
        $nContents=count($layoutContents);
        if($nContents==0)
             return;

        $curVar=1;
        $phpCode=array();
        $newContents=array();
        $currentNode=array("TYPE"=>"HTML","TEXT"=>"");
        $newContents[]=$currentNode;
        /* Supongamos una lista de layoutContents del tipo HTML - PHP - HTML : ej: a <?php echo "p";?> b
         *  Para buscar la traduccion, hay que actuar como si fuera 1 solo layoutContent de tipo a %1 b
         *  Por eso, el resultado debe ser 1 solo layoutcontent de tipo HTML, aunque dentro, contenga codigo php.
         * 
         */
         $html="";
        for($k=0;$k<$nContents;$k++)
        {            
                if($layoutContents[$k]["TYPE"]=="HTML")
                {
                    $html.=$layoutContents[$k]["TEXT"];
                }
                else
                {
                    if($layoutContents[$k]["TYPE"]=="PHP")
                    {
                        $html.="{%".$curVar."}";
                        $phpCode[$curVar]=$layoutContents[$k]["TEXT"];
                        $curVar++;
                    }
                    else
                    {
                        // Se traduce el nodo HTML actual
                        $currentNode["TEXT"]=$this->parseTranslation($tLang,$id,$phpCode,$html);
                        // Se copia el nodo que hemos encontrado, tal cual (no es ni PHP ni HTML)
                        $newContents[]=$layoutContents[$k];
                        // Se crea el siguiente nodo HTML.
                        $currentNode=array("TYPE"=>"HTML","TEXT"=>"");
                        $newContents[]=$currentNode;
                        $html="";
                        $phpCode=array();
                        $curVar=1;
                    }
                    
                }
        }     
        // Al llegar aqui, nos queda el ultimo nodo HTML creado.Hay que parsearlo.
        $currentNode["TEXT"]=$this->parseTranslation($tLang,$id,$phpCode,$html);
        return $newContents;
     }

     function parseTranslation($tLang,$id,$phpCode,$html)
     {
         $this->loadTranslations($tLang);
         $search=array();
         if($phpCode)
         {
             if(!is_array($phpCode))
             {
                 breakme();
             }
             else
             {
                foreach($phpCode as $cKey=>$cValue)
                    $search["/{\\%".$cKey."}/"]=$cValue;
             }
         }

        if(is_array(T::$translations[$tLang]))
        {
                $target=T::$translations[$tLang][$id];
                if(!$target)
                {
                    // No existe para este idioma.Se copia del DEFAULT_LANGUAGE, y se almacena, con el mismo valor.
                    $this->loadTranslations(DEFAULT_LANGUAGE);
                    $target=T::$translations[DEFAULT_LANGUAGE][$id];
                    if(!$target)
                    {
                        $target=$html;
                    }

                    try
                    {
                        $m=\getModel("ps_lang\\translations",array("id_string"=>$id,"lang"=>$tLang));
                    }
                    catch(\Exception $e)
                    {
                         $m=\getModel("ps_lang\\translations");
                    }

                        $m->id_string=$id;
                        $m->value=$target;
                        $m->lang=$tLang;
                        $m->save();
                    T::$translations[$tLang][$id]=$target;

                }
                else
                {
                    if(trim($target)!=trim($html) && $tLang==DEFAULT_LANGUAGE)
                    {
                        // En teoria,deberia siempre existir.Esto es en el caso de que el precompiler no haya guardado por algun motivo.
                        try
                        {
                            $m=\getModel("ps_lang\\translations",array("lang"=>$tLang,"id_string"=>$id));
                            $html=$m->value;
                        }
                        catch(\Exception $e)
                        {
                            $m=\getModel("ps_lang\\translations");
                            $m->lang=$tLang;
                            $m->id_string=$id;
                            $m->value=trim($html);
                            $m->save();
                        }

                        $target=$html;
                    }
                }
        }
        return preg_replace(array_keys($search),array_values($search),$target);
     }

     function loadTranslations($lang)
     {
         if(T::$translations[$lang])
             return;
         $ds=\getDataSource('ps_lang\translations','FullList');
         $ds->lang=$lang;
         $it=$ds->fetchAll();
         $n=$ds->count();
         for($k=0;$k<$n;$k++)
         {
               T::$translations[$lang][$it[$k]->id_string]=$it[$k]->translated;
         }
     }
    function getTranslationFromValue($lang,$value,$params)
    {
        if($lang==DEFAULT_LANGUAGE)
            return $value;
        $this->loadTranslations(DEFAULT_LANGUAGE);
        $this->loadTranslations($lang);
        $l=T::$translations[DEFAULT_LANGUAGE];
        $foundId=null;
        if (is_array($l))
        {
            foreach($l as $id=>$data)
            {
                if($data==$value)
                {
                    if(isset(T::$translations[$lang][$id]))
                        return T::$translations[$lang][$id];
                    $foundId=$id;
                    break;
                }
            }
        }
        if(!$foundId)
        {
            // Tenemos que crear una para el DEFAULT_LANGUAGE
            $m=\getModel("ps_lang\\translations");
            $m->lang=DEFAULT_LANGUAGE;
            $m->value=$value;
            $m->save();
            $foundId=$m->id_string;
            T::$translations[DEFAULT_LANGUAGE][$foundId]=$value;
        }
        return $this->parseTranslation($lang,$foundId,$params,$value);
    }
    function saveTranslations($lang,$keyVals)
    {
        // Se van a guardar solo aquellas que se hayan modificado.
        T::loadTranslations($lang);
        $l=T::$translations[$lang];
        foreach($l as $key=>$value)
        {
            if($value!=$keyVals[$key])
            {
                $m=\getModel("ps_lang\\translations",array("id_string"=>$key,"lang"=>$lang));
                $m->value=$keyVals[$key];
                $m->save();
            }
        }
    }
}

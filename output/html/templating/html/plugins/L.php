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
class L extends Plugin
{
    static $translations=null;
    static $defaultLangPath=null;
     function __construct($parentWidget,$layoutContents,$layoutManager)
     {

         $this->parentWidget=$parentWidget;         
         $this->layoutContents=$layoutContents;
         $this->layoutManager=$layoutManager;
     }

     function parse()
     {                  
        $layoutContents=& $this->layoutContents;
        $nContents=count($layoutContents);
        if($nContents==0)
            return;
        $params=$this->layoutManager->getPluginParams("L");
        $tLang=$params["lang"];
        if(!$tLang)
        {
            debug("Error: es necesario pasar el parametro 'LANG' para el plugin L en el LayoutManager");
            exit();
        }

        if($tLang==DEFAULT_LANGUAGE)
        {            
            return $this->layoutContents;
        }
        
        if(!L::$translations[$tLang])
        {
            $this->loadTranslations($tLang);
        }
               
        
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
                        $currentNode->preparedContents=$this->parseTranslation($tLang,$html,$phpCode);
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
        $currentNode->preparedContents=$this->parseTranslation($tLang,$html,$phpCode);
        return $newContents;
     }
     function parseTranslation($tLang,$html,$phpCode)
     {

         $this->loadTranslations($tLang);
         $search=array();
         if( $tLang==DEFAULT_LANGUAGE )
         {
             $trimmed=str_replace("_"," ",$html);
             L::$translations[$tLang][$trimmed]=$trimmed;
         }
         else
         {
            
            $found=false;
            // strtolower added for the Downloads Platform
             $trimmed=trim($html);
        }

         $search=array();
         if($phpCode)
         {
            foreach($phpCode as $cKey=>$cValue)
                $search["/{\\%".$cKey."}/"]=$cValue;
         }
        $encoded=base64_encode($trimmed);
         $found=false;
        if(is_array(L::$translations[$tLang]))
        {
           
            if(isset(L::$translations[$tLang][$encoded]))
            {                                        
                 $found=true;

                $eo=L::$translations;
                if(L::$translations[$tLang][$encoded]) // Si isset, pero esta vacia, no se modifica nada
                     $target=L::$translations[$tLang][$encoded];
                else
                {
                    $target=$html;
                }
            }
        }
        if($found==false)
        {
            echo "ADDING PENDING TRANSLATION $trimmed<br>";
            $this->addPendingTranslation($tLang,$trimmed);
            $target=$html;
        }
        
        return preg_replace(array_keys($search),array_values($search),$target);
     }

     function getTranslationFile($lang,$mode="r")
     {
         if($this->layoutManager)
         {
            $params=$this->layoutManager->getPluginParams("L");
            $targetPath="";
            if( is_array($params) )
            {
                 if( array_key_exists("LANGPATH",$params) )
                {
                     $targetPath=$params["LANGPATH"];
                }
            }
            if($targetPath=="")
                 $targetPath=$this->layoutManager->getBasePath()."/lang/";
         }
         else
             $targetPath=\L::$defaultLangPath;

         $op=fopen($targetPath."/".$lang.".txt",$mode);

         return $op;
     }
     function loadTranslations($lang)
     {
         if(L::$translations==null)
         {
             $op=$this->getTranslationFile($lang);
             if(!$op)
             {
                 // No existe el fichero
                 return;
             }
             $lineNo=0;
             while($line=fgets($op))
             {
                 if(trim($line)=="")
                     continue;
                 $lineNo++;
                 $parts=explode("::=::",$line);
                 if(count($parts)!=2)
                 {
                     //echo "ERROR EN FICHERO DE TRADUCCION [".$lang."] en linea $lineNo: ".$line;
                     //exit();
			continue;
                 }
                 L::$translations[$lang][base64_encode(trim($parts[0]))]=trim($parts[1]);
             }
             fclose($op);                
         }
     }
     function addPendingTranslation($lang,$value)
     {
         global $currentPage;
         if($currentPage)
         {
            $op1=fopen("/tmp/llog","a");
             fputs($op1,"Aniadiendo $value desde pagina ".$currentPage->pageName);
             fclose($op1);
         }
         $op=$this->getTranslationFile($lang,"r");
         flock($op, LOCK_EX);
         $current=array();
         while($cad=fgets($op))
         {
             $p=explode("::=::",$cad);
             if(count($p)!=2)
                 continue;
             $current[trim($p[0])]=trim($p[1]);
         }

         flock($op, LOCK_UN);
         fclose($op);
         $op=$this->getTranslationFile($lang,"w");
         flock($op, LOCK_EX);
         foreach($current as $key2=>$value2)
             fwrite($op,$key2."::=::".$value2."\n");
         fwrite($op,$value."::=::\n");
         flock($op, LOCK_UN);


         fclose($op);
         L::$translations[$lang][$value]="";
     }


    function getHashedNameTranslation($lang){
        $hashed=array();
        //Carga las traducciones del lenguaje actual y crea la clave md5 para comparar con lo que llega de POST
        $this->loadTranslations($lang);
        $unhashed=L::$translations[$lang];
        foreach($unhashed as $key=>$value)
        {
            $hashed[md5($key)]=$key;
        }
        return $hashed;

    }
    function saveTranslation($lang,$postTranslation)
    {
        $hashed=$this->getHashedNameTranslation($lang);
        //Abre el fichero
        $op=$this->getTranslationFile($lang,"w");
        foreach($postTranslation as $clave=>$valor){
                    if ($hashed[$clave]!="")
                        fwrite($op,$hashed[$clave]."::=::".$valor."\n");
        }
        flock($op, LOCK_UN);
        fclose($op);
        //L::$translations[$lang]=$translation;
    }

    static function setLangPath($dir)
    {
        L::$defaultLangPath=$dir;

    }
}

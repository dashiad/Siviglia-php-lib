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

class CSS extends Plugin
{    
     static $requiredCSS;
     static $parsedWidgets;
     function __construct($parentWidget,$layoutContents,$layoutManager)
     {
         $this->layoutContents=$layoutContents;
         $this->layoutManager=$layoutManager;
     }

     function initialize()
     {
         CSS::$requiredCSS=array();
         CSS::$parsedWidgets=array();
     }

     function parse()
     {         
         $curWidget=$this->layoutManager->currentWidget;
         if(isset(CSS::$parsedWidgets[$curWidget["FILE"]]))
             return array();
         else
             CSS::$parsedWidgets[$curWidget["FILE"]]=1;
         $nEls=count($this->layoutContents);
         $elements=array();
         $nodes=array();
         $cssText="";
         for($k=0;$k<$nEls;$k++)
         {
             $curEl=$this->layoutContents[$k];

             if(!is_a($curEl,"CHTMLElement"))
             {
                 $elements[$curEl->name]=array();
                 $text="";
                 if($curEl->contents)
                 {
                     foreach($curEl->contents as $val)
                     {
                         if(is_a($val,"CHTMLElement"))
                         $text.=$val->preparedContents;                         
                     }
                     $elements[$curEl->name]=$text; 
                     $nodes[$curEl->name]=$curEl;
                 }
                continue;
             }            
             $cssText.=$curEl->preparedContents;             
         }         
        $this->processCSSFile($cssText,$elements);
        
            return array();
     }
     function processCSSFile($cssText,$elements)
     {         
         
         if(!isset($elements["FILE"]))
         {
             $layoutName=str_replace($this->layoutManager->getBasePath(),"",$this->layoutManager->getLayout());
             $parts=explode(".",$layoutName);
             unset($parts[count($parts)-1]);        
             $filename=implode(".",$parts);
         }
         else
             $filename=$elements["FILE"];

         $curWidget=$this->layoutManager->currentWidget;

         $subPath=str_replace($this->layoutManager->getBasePath(),"",str_replace("//","/",$curWidget["FILE"]));
         $p=basename($subPath);
         $p2=realpath(dirname($subPath));
         $subPath=$p2."/".$p;
         $sum=md5($subPath);
         $cssText=trim($cssText);
         if($cssText[0]=="<" && $cssText[strlen($cssText)-1]==">")
         {
             $cssText=substr($cssText,strpos($cssText,">")+1);
             $cssText=substr($cssText,0,-8);
         }

         $commentTextStart=" begin $sum";
         $commentTextEnd=" end $sum";         

         CSS::$requiredCSS[$filename][]="/* ".$commentTextStart." $subPath */\n".$cssText."\n/* ".$commentTextEnd." $subPath */\n";   
         
     }
     function postParse($contents)
     {
         
         $params=$this->layoutManager->getPluginParams("CSS");
         $targetPath="";
         if( is_array($params) )
         {
             if( array_key_exists("CSSPATH",$params) )
             {
                 $targetPath=$params["CSSPATH"];
             }
         }

         if($targetPath=="")
             $targetPath=$this->layoutManager->getBasePath()."/css";
         
         if(count(CSS::$requiredCSS)==0 )return;    
              
         foreach(CSS::$requiredCSS as $key=>$value)
         {
             if($key[0]=='/')
             {
                 if(array_key_exists("WEBPATH",$params))
                 {
                     $fullPath=$params["WEBPATH"]."/".$key.".css";
                 }
             }
             else
                    $fullPath=$targetPath."/".$key.".css";
             
             @mkdir(dirname($fullPath),0777,true);              
             file_put_contents($fullPath,implode("\n",$value));
         }
         return $contents;
     }
}

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
class SCRIPT extends Plugin
{
   static $jsCodes=array();
     function __construct($parentWidget,$layoutContents,$layoutManager)
     {      
         $this->layoutContents=$parentWidget;
         $this->layoutManager=$layoutManager;

     }
     
     function parse()
     {
         
         if( !is_array($this->layoutContents) )
         {
             $this->layoutContents=array($this->layoutContents);
         }
         $nEls=count($this->layoutContents[0]["CONTENTS"]);
         $elements=array();
         for($k=0;$k<$nEls;$k++)
         {
             $curEl=$this->layoutContents[0]["CONTENTS"][$k];
             
             if($curEl["TYPE"]=="SUBWIDGET")
                     continue;
             $elements[$curEl["NAME"]]=array();
             $text="";
             if($curEl["CONTENTS"])
             {
                foreach($curEl["CONTENTS"] as $val)
                {
                     if($val["TYPE"]=="HTML")
                         $text.=$val["TEXT"];
                }
                $elements[$curEl->name]=$text; 
                $nodes[$curEl->name]=$curEl;
             }
         }
         
        $this->processJsFile($elements["FILE"]?$elements["FILE"]:"Widgets",$elements["SRC"],$elements["TEMPLATE"]);
         return array();

     }
     function processJsFile($filename,$code,$layout)
     {         
         
         $curWidget=$this->layoutManager->currentWidget;
         $base=realpath(str_replace("//","/",$this->layoutManager->getBasePath()));
         $widgetFile=realpath(str_replace("//","/",$curWidget["FILE"]));
         $replaced=str_replace($base,"",$widgetFile);
         $subPath=$base."/".$replaced;
         $p=basename($subPath);
         $p2=realpath(dirname($subPath));
         $subPath=$p2."/".$p;
         
         $sum=md5($subPath);
         $commentTextStart=" begin $sum";
         $commentTextEnd=" end $sum";
                  
         $params=$this->layoutManager->getPluginParams("SCRIPT");
         $targetFile="";
         if( is_array($params) )
         {
         
             if( array_key_exists("SCRIPTPATH",$params) )
             {                 
                 $targetFile=$params["SCRIPTPATH"]."/";
             }
         }
         if($filename[0]=='/')
         {
             if(array_key_exists("WEBPATH",$params))
             {
                 $targetFile=$params["WEBPATH"];
             }
         }
         if($targetFile=="")
             $targetFile=SIVIGLIA_TEMPLATES_PATH."/../js";
         
         $targetFile.=$filename.".js";

         $code=trim($code);
         $code=str_replace("<script>","",$code);
         $code=str_replace("</script>","",$code);

         if($code[0]=="<" && $code[strlen($code)-1]==">")
         {
             $code=substr($code,strpos($code,">")+1);
             $code=substr($code,0,-9);
         }

         SCRIPT::$jsCodes[$targetFile][]=array($commentTextStart,$commentTextEnd,$code,$sum,$subPath);
     }

    function postParse($pageContents)
    {


        foreach(SCRIPT::$jsCodes as $targetFile=>$value)
        {

            if(is_file($targetFile))
            {
                $l=new Lock(dirname($targetFile),basename($targetFile));
                $l->lock();
                $contents=file_get_contents($targetFile);
                $l->unlock();
            }
            else
            {
                 @mkdir(dirname($targetFile),0777,true);
                $l=new Lock(dirname($targetFile),basename($targetFile));
                $contents="";
            }


            for($j=0;$j<count($value);$j++)
             {
                 $commentTextStart=$value[$j][0];
                 $commentTextEnd=$value[$j][1];
                 $code=$value[$j][2];
                 $sum=$value[$j][3];
                 $subPath=$value[$j][4];

                $pos1=strpos($contents,$commentTextStart);

                if($pos1)
                {
                    $pos2=strpos($contents,$commentTextEnd);
                    if(!$pos2)
                    {
                         _d("Posible fichero javascript corrupto: ".$targetFile.".No se encontro marca de fin para $subPath");
                        return;
                    }
                    $newCode=substr($contents,0,$pos1);
                    $newCode.=" begin $sum $subPath */\n".$code."\n/* ".substr($contents,$pos2);
                 }
                 else
                 {
                     $newCode=$contents."\n\n/* ".$commentTextStart." $subPath */\n".$code."\n/* ".$commentTextEnd." $subPath */\n";
                 }
                 $contents=$newCode;
             }
            $l=new Lock(dirname($targetFile),basename($targetFile));
            $l->lock();
             file_put_contents($targetFile,$contents);
            $l->unlock();
         }
        return $pageContents;
     }
}

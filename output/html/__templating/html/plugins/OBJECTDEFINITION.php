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

class OBJECTDEFINITION extends Plugin
{    

     function __construct($parentWidget,$layoutContents,$layoutManager)
     {
         $this->layoutContents=$layoutContents;
         $this->layoutManager=$layoutManager;
     }

     function initialize()
     {

     }
     function parse()
     {
        $spec=$this->parseNode($this->layoutContents,true);
        $object=$this->getNodesByTagName("OBJECT",$spec);
        $form=$this->getNodesByTagName("FORM",$spec);
        $output=$this->getNodesByTagName("OUTPUT",$spec);
        $datasource=$this->getNodesByTagName("DATASOURCE",$spec);
        $object=trim($object[0]);


        $o=new \lib\reflection\model\ObjectDefinition($object);

        if($form && count($form)>0)
        {
            $form[0]=trim($form[0]);
            // Se obtiene el fichero de definicion
            $file=$o->getDestinationFile("html/forms/".$form[0].".php");
            $target="FORM";

        }
        if($datasource && count($datasource)>0)
        {
            $datasource[0]=trim($datasource[0]);
            $file=$o->getDestinationFile("datasources/".$datasource[0].".php");
            $target="DATASOURCE";
        }
        // Se obtiene una instancia del preprocesador
         include_once(PROJECTPATH."/lib/output/html/templating/html/preCompilers/L2.php");
         $l2=new L2($this->layoutManager,$this->layoutManager->getTargetProtocol());
         // Se carga el fichero de definicion, y se hacen los replaces.
         $fContents=file_get_contents($file);
         $fContents=$l2->parse($fContents);
         // Se guarda el fichero.
         file_put_contents($file,$fContents);

         // Ahora si que se puede obtener el fichero como un simple json
         if($form && count($form)>0)
         {
             // Se obtiene el fichero de definicion
             $file=$o->getDestinationFile("html/forms/".$form[0].".php");
             $target=\lib\output\html\Form::getForm($object,$form[0],array());
             $def=$target->getDefinition();
             $definition=\lib\output\html\Form::getFormDefinition($def);
         }
         if($datasource && count($datasource)>0)
         {
             $target=\getDataSource($object,$datasource[0]);
             $def=$target->getDefinition();
         }
         if(!$def)
             $def="";
         else
             $def=json_encode($def,JSON_UNESCAPED_UNICODE);

         // Se devuelve el parseado del contenido.
        $result=$this->layoutManager->processContents($def);
         return array(new \CHTMLElement($result,$this->layoutContents[0]->parentWidget));
     }


}

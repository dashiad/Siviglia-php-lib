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
class Info extends Plugin
{
     function __construct($parentWidget,$layoutContents,$layoutManager)
     {
         $this->parentWidget=$parentWidget;         
         $this->layoutContents=$layoutContents;
         $this->layoutManager=$layoutManager;
     }

     function parse()
     {
        $this->getParams();
        $parentContents=$this->parentWidget->parentWidget->contents;

        $nContents=count($parentContents);
        $childWidgets=array();
        for($k=0;$k<$nContents;$k++)
        {

                    if(is_a($parentContents[$k],'\CWidgetItem') || is_a($parentContents[$k],'\CWidget'))
                    {

                        $childWidgets[]=$parentContents[$k]->name;
                        if($this->params["REMAP_NODES"])
                        {
                            $parentContents[$k]->name=$this->params["REMAP_NODES"];
                        }
                    }

        }
        $currentNode=new \CHTMLElement("",$this->layoutContents[0]->parentWidget);
        // Al llegar aqui, nos queda el ultimo nodo HTML creado.Hay que parsearlo.
        $currentNode->preparedContents="<?php global \$currentWidgetInfo; \$currentWidgetInfo=array('".implode("','",$childWidgets)."');?>";
        return array($currentNode);
     }
     function getParams()
     {
         $this->params=array();
         for($k=0;$k<count($this->layoutContents);$k++)
         {
             if(is_a($this->layoutContents[$k],'\CSubWidget'))
             {
                 $this->params[$this->layoutContents[$k]->name]=trim($this->layoutContents[$k]->contents[0]->preparedContents);
             }
         }
     }
}

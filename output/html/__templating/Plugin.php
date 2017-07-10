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

   abstract class Plugin
   {
        var $layoutContents;
        var $layoutManager;
        function __construct($layoutContents,$layoutManager)
        {
                 $this->layoutContents=$layoutContents;
                $this->layoutManager=$layoutManager;
        }
        abstract function parse();
        function initialize()
        {
            
        }
        function postParse($textContent)
        {
            return $textContent;
        }
        function parseNode($node,$preserveOrder=false)
        {
            if( !is_array($node) )
            {
                $node=array($node);
            }
            $htmlText="";
            $subElements=array();

            for($j=0;$j<count($node);$j++)
            {
                if(!$node[$j]->contents)
                {
                    if(is_a($node[$j],"CHTMLElement") || is_a($node[$j],"CPHPElement"))
                    {
                        $htmlText.=$node[$j]->preparedContents;
                    }
                    continue;
                }
                // Si el contenido a su vez es un plugin, con
                if($node[$j]->isPlugin)
                {
                    $htmlText.=$this->parseNode($node[$j]->contents,$preserveOrder);
                    continue;
                }
                $name=$node[$j]->name;
                $result=$this->parseNode($node[$j]->contents,$preserveOrder);

                if($preserveOrder)
                  $subElements[]=array($name,$result);
                else
                    $subElements[$name][]=$result;

             }

             if(count(array_keys($subElements))>0)
                 return $subElements;
            return $htmlText;
        }
       // El array de elementos es el devuelto por parseNode cuando
       // preserveOrder=true, es decir, un array de arrays de 2 elementos:
       // el primero es el tag, el segundo el elemento.
       function getNodesByTagName($tag,$elements)
       {
           $result=array();
           for($k=0;$k<count($elements);$k++)
           {
               if($elements[$k][0]==$tag)
                   $result[]=$elements[$k][1];
           }
           return $result;
       }
       // A partir de un array devuelto por parseNode, con preserveOrder=true,
       // devuelve un array equivalente al devuelto por preserveOrder=false
       function mergeNodes($arr)
       {
           $result=array();
           for($k=0;$k<count($arr);$k++)
               $result[$arr[$k][0]][]=$arr[$k][1];
           return $result;
       }
   }

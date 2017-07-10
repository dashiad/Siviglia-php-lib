<?php
namespace lib\output\html\templating\html\plugins;

class ONCE extends \lib\output\html\templating\Plugin
{    
     static $requiredONCE;
     function __construct($parentWidget,$layoutContents,$layoutManager)
     {
         $this->layoutContents=$layoutContents;
         $this->layoutManager=$layoutManager;
     }

     function initialize()
     {
         ONCE::$requiredONCE=array();
     }

     function parse()
     {         
         $curWidget=$this->layoutManager->currentWidget;
         if(ONCE::$requiredONCE[$curWidget["FILE"]])
             return;
         ONCE::$requiredONCE[$curWidget["FILE"]];

         $nEls=count($this->layoutContents);
         for($k=0;$k<$nEls;$k++)
         {
             $curEl=$this->layoutContents[$k];             
             $text.=$curEl->preparedContents;             
         } 
         return new CHTMLElement($text);
     }     
}?>

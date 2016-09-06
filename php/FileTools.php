<?php
namespace lib\php;
class FileTools
{
   static function recurse_copy($src,$dst) { 
       $dir = opendir($src); 
       @mkdir($dst); 
       while(false !== ( $file = readdir($dir)) ) { 
           if (( $file != '.' ) && ( $file != '..' )) { 
               if ( is_dir($src . '/' . $file) ) { 
                   recurse_copy($src . '/' . $file,$dst . '/' . $file); 
               } 
               else { 
                   copy($src . '/' . $file,$dst . '/' . $file); 
               } 
           } 
       } 
       closedir($dir); 
   }

    static function  delTree($dir) {
        chmod($dir,0777);
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {

            if(is_dir("$dir/$file"))
            {
                FileTools::delTree("$dir/$file");
            }
            else
            {
                chmod($dir."/".$file,0777);
                unlink("$dir/$file");
            }
        }
        return rmdir($dir);
    }
}

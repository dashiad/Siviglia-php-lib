<?php
   namespace platform\storage;

   class SphinxManager
   {
       static $instance;
       private function __construct()
       {
       }
       static function getInstance()
       {
           if( !SphinxManager::$instance )
               SphinxManager::$instance=new SphinxManager();
           return SphinxManager::$instance;
       }
        function regenerateConfig()
        {
                $configFile=SPHINX_CONFIG_PATH."/sphinx.conf.default";
                $info=file_get_contents($configFile);
                if( $info===false)
                {
                    echo "Error opening Sphinx configuration files.\nExiting..\n";
                    exit();
                }
                $search=array('$MYSQLDBHOST$','$MYSQLDBUSER$','$MYSQLDBPASS$','$MYSQLDATABASE$','$MYSQLPORT$',
                              '$SPHINX_LOCAL_DATA_PATH$','$SPHINX_CONFIG_PATH$','$SPHINX_HOST$','$SPHINX_PORT$');

                $replace=array(MYSQLDBHOST,MYSQLDBUSER,MYSQLDBPASS,MYSQLDATABASE,3306,SPHINX_LOCAL_DATA_PATH,SPHINX_CONFIG_PATH,SPHINX_HOST,SPHINX_PORT);
                $customized=str_replace($search,$replace,$info);
                if( !is_dir(SPHINX_LOCAL_CONFIG_PATH) )
                    mkdir(SPHINX_LOCAL_CONFIG_PATH,0777,true);

                if( !is_dir(SPHINX_LOCAL_DATA_PATH) )
                    mkdir(SPHINX_LOCAL_DATA_PATH,0777,true);

                if(file_put_contents(SPHINX_LOCAL_CONFIG_PATH."/sphinx.conf",$customized)===false)
                {
                    echo "Error saving Sphinx configuration file : ".SPHINX_LOCAL_CONFIG_PATH."/sphinx.conf"."\nExiting...\n";
                    exit();
                }                
        }
        function reIndex()
        {
            
            exec("echo \"#########\" >> ".SPHINX_LOCAL_DATA_PATH."indexer.log");
            exec("date >> ".SPHINX_LOCAL_DATA_PATH."indexer.log");
            exec(SPHINX_BIN_PATH."indexer --config ".SPHINX_LOCAL_CONFIG_PATH."sphinx.conf --all --rotate >> ".SPHINX_LOCAL_DATA_PATH."indexer.log");
            exec(SPHINX_BIN_PATH."indexer --config ".SPHINX_LOCAL_CONFIG_PATH."t3-sphinx.conf --all --rotate >> ".SPHINX_LOCAL_DATA_PATH."indexer.log");
        }
        function updateCrontab()
        {
            $output = shell_exec('crontab -l'); 
            $lines=explode("\n",$output);
            $cronLine="0 0/6 * * * root ".PROJECTPATH."bin/sphinx_reindex.php";
            $found=false;
            $nLines=count($lines);
            for( $k=0;$k<$nLines && $found==false;$k++ )
            {
                if(strpos($lines[$k],$cronLine)!==false )
                    $found=true;
            }
            if( $found )
                return;
            $lines[]=$cronLine."\n";            
            file_put_contents("/tmp/crontab.txt",implode("\n",$lines));
            echo exec('crontab /tmp/crontab.txt');            
        }
        function install()
        {            
            $this->regenerateConfig();
            $this->reIndex();
            $this->updateCrontab();
        }
        function run()
        {
            exec("killall searchd");
            exec(SPHINX_BIN_PATH."searchd --config ".SPHINX_LOCAL_CONFIG_PATH."sphinx.conf");
        }
   }

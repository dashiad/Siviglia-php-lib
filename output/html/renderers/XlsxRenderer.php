<?php
namespace lib\output\html\renderers;

class XlsxRenderer
{
    public function render($page, $requestedPath, $outputParams)
    {
        include_once(PROJECTPATH."/lib/output/xls/xlsxwriter.class.php");
        global $oCurrentUser;

        $sources=$page->definition["SOURCES"];

        //Columnas
        $columns = array();
        if (isset($outputParams['columns'])) {
            $columns = explode(',', $outputParams['columns']);
        }

        foreach($sources as $key=>$definition)
        {
            switch($definition["ROLE"])
            {

                case 'dynlist':
                case 'list':
                case 'view':{
                    $obj=  \lib\datasource\DataSourceFactory::getDataSource($definition["OBJECT"], $definition["NAME"]);
                    $obj->setParameters($page);

                    /*
                    $paging=$obj->getPagingParameters();
                    if(!$paging->{"*__count"}->hasOwnValue())
                    {
                        $paging->__count=5000; // Maximo de 5000 elementos
                    }
                    */

                    $obj->fetchAll();
                    $iterator=$obj->getIterator();
                    $dsDef=$obj->getOriginalDefinition();

                    if ($columns) {
                        foreach($dsDef["FIELDS"] as $key=>$value) {
                            if (in_array($key, $columns)) {
                                $fields[$key]=$value;
                            }
                        }
                    }
                    else{
                        $fields=$dsDef["FIELDS"];
                    }

                    $headers=array();
                    foreach($fields as $k=>$v)
                    {
                        $type=\lib\model\types\TypeFactory::getType(null,$v);
                        if(is_a($type,'\lib\model\types\Integer'))
                            $headers[$k]='Entero';
                        if(is_a($type,'\lib\model\types\Money'))
                        {
                            $headers[$k]="Moneda";
                        }else
                        {
                            if(is_a($type,'\lib\model\types\Decimal'))
                                $headers[$k]='Decimal';
                        }
                        if(is_a($type,'\lib\model\types\String'))
                            $headers[$k]='Texto';
                        if(is_a($type,'\lib\model\types\Date'))
                            $headers[$k]='Fecha';
                        if(is_a($type,'\lib\model\types\Date'))
                            $headers[$k]='FechaHora';
                        if(!isset($headers[$k]))
                            $headers[$k]='General';
                    }

                    $iterator=$obj->getIterator();
                    if($definition["ROLE"]=='view') {
                        if ($columns) {
                            $aux = $iterator->getFullRow();
                            $count = 0;
                            $data = array();
                            foreach($aux as $key=>$val) {
                                if (in_array($key, $columns)) {
                                    $data[$key] = $val;
                                }
                            }
                        }
                        else {
                            $data=$iterator->getFullRow();
                        }
                    }
                    else {
                        if ($columns) {
                            $aux = $iterator->getFullData();
                            $count = 0;
                            $data = array();
                            for($i=0;$i<count($aux);$i++) {
                                foreach($aux[$i] as $key=>$val) {
                                    if (in_array($key, $columns)) {
                                        $data[$count][$key] = $val;
                                    }
                                }
                                ++$count;
                            }
                        }
                        else {
                            $data = $iterator->getFullData();
                        }
                    }

                    $writer = new \XLSXWriter();
                    $writer->setAuthor('Siviglia Framework');
                    $writer->writeSheet($data,'Sheet1',$headers);

                    $now = new \DateTime();
                    $filename = $definition["OBJECT"]."-".$definition["NAME"]."_".$now->format('Y-m-d').".xlsx";

                    header('Content-disposition: attachment; filename='.$filename);
                    header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    //header('Content-Length: ' . filesize($file));
                    header('Content-Transfer-Encoding: binary');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    ob_clean();
                    flush();
                    $writer->writeToStdOut();
                    die();
                }break;
                default:{
                }break;
            }
        }

        \Registry::$registry["PAGE"]=$page;
    }
}

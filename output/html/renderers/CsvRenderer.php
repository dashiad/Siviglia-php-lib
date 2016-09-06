<?php
namespace lib\output\html\renderers;

class CsvRenderer
{
    public function render($page, $requestedPath, $outputParams)
    {
        global $oCurrentUser;

        $sources=$page->definition["SOURCES"];

        //Output params
        if (isset($outputParams['separator'])) {
            $separator = $outputParams['separator'];
        }
        else {
            $separator = ';';
        }

        //Enclosure
        if (isset($outputParams['enclosure'])) {
            $enclosure = $outputParams['enclosure'];
        }
        else {
            $enclosure = '"';
        }

        //No header
        if (isset($outputParams['noheader']) && $outputParams['noheader']) {
            $noHeader = true;
        }

        //Extension
        if (isset($outputParams['extension'])) {
            $extension = $outputParams['extension'];
        }
        else {
            $extension = 'csv';
        }

        //Line break
        if (isset($outputParams['linebreak'])) {
            switch ($outputParams['linebreak']) {
                case 'unix':
                    $lineBreakCharacter = "\n";
                    break;
                case 'windows':
                default:
                    $lineBreakCharacter = "\r\n";
                    break;
            }
        }
        else {
            $lineBreakCharacter = "\r\n";
        }

        //Filename
        if (isset($outputParams['filename'])) {
            $filename = $outputParams['filename'];
        }
        else {
            $now = new \DateTime();
            $filename = $sources[0]["OBJECT"]."-".$sources[0]["NAME"]."_".$now->format('Y-m-d');
        }

        //Encoding
        $isAnsi = false;
        if (isset($outputParams['ansi']) && $outputParams['ansi'] == 1) {
            $isAnsi = true;
        }

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
                    if(!$paging->{"*__count"}->hasOwnValue()) {
                        $paging->__count=3000; // Maximo de 3000 elementos
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

                    $headers=array_keys($fields);

                    $iterator=$obj->getIterator();
                    if($definition["ROLE"]=='view')
                    {
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
                    else
                    {
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

                    $output = fopen("php://output",'w') or die("Can't open php://output");
                    header("Content-Type:application/csv");
                    header("Content-Disposition:attachment;filename=".$filename.".".$extension);
                    header('Content-Transfer-Encoding: binary');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    ob_clean();
                    flush();

                    if (! $noHeader) {
                        if ($isAnsi) {
                            array_walk($headers, array($this, 'encodeCSV'));
                        }
                        if ($enclosure === 'null') {
                            $str = implode($separator, $headers);
                            fputs($output, $str . $lineBreakCharacter);
                        }
                        else {
                            fputcsv($output, $headers,$separator,$enclosure);
                        }
                    }

                    foreach($data as $cdata) {
                        if ($isAnsi) {
                            array_walk($cdata, array($this, 'encodeCSV'));
                        }
                        if ($enclosure === 'null') {
                            $str = implode($separator, $cdata);
                            fputs($output, $str . $lineBreakCharacter);
                        }
                        else {
                            fputcsv($output, $cdata,$separator, $enclosure);
                        }
                    }
                    fclose($output) or die("Can't close php://output");
                    die();
                }break;

                default:{

                }break;
            }
        }

        \Registry::$registry["PAGE"]=$page;
    }

    function encodeCSV(&$value, $key)
    {
        $value = iconv('UTF-8', 'ISO-8859-1', $value);
    }
}
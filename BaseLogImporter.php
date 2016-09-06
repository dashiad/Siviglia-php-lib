<?php
 class BaseLogImporter
 {
     var $sourceName;
     var $relatedTo;
     var $db;
     function __construct($db,$sourceName,$relatedTo)
     {
         $this->db=$db;
         $this->sourceName=$sourceName;
         $this->relatedTo=$relatedTo;
     }
     function importDir($dir)
     {
         $op=opendir($dir);
         while($fname=readdir($op))
         {
             echo "PROCESANDO FICHERO ".$dir."/".$fname."<br>";
             if(!is_dir($dir."/".$fname))
                 $this->importFile($dir."/".$fname);
         }
     }
     function savePayment($data)
     {

	    $data["payment_source"]="'".mysql_escape_string($this->sourceName)."'";
	    $data["id_source"]="'".mysql_escape_string($data["id_source"])."'";

	    $data["related_to"]="'".mysql_escape_string($this->relatedTo)."'";
        if(isset($data["related_id"]))
	        $data["related_id"]=intval($data["related_id"]);
	    $data["date"]=$this->reformatDate($data["date"]);
        if(isset($data["effective_date"]))
	        $data["effective_date"]=$this->reformatDate($data["effective_date"]);

	    $data["nombre"]=trim($data["nombre"]);
	    $data["tipo"]=intval($data["tipo"]);
	    $data["estado"]=inval($data["estado"]);
        $data["neto"]=str_replace(array(".",","),array("","."),$data["neto"]);
        if(!isset($data["comision"]))
            $data["bruto"]=$data["neto"];
        else
	        $data["bruto"]=str_replace(array(".",","),array("","."),$data["bruto"]);
        if(isset($data["comision"]))
	        $data["comision"]=str_replace(array(".",","),array("","."),$data["comision"]);
        else
             $data["comision"]=0;
        if(isset($data["email"]))
	        $data["email"]="'".mysql_escape_string($data["email"])."'";
         if(isset($data["referenced_payment"]))
             $data["referenced_payment"]="'".mysql_escape_string($data["referenced_payment"])."'";
	    if(isset($data["name"]))
            $data["name"]="'".mysql_escape_string($data["name"])."'";
	    if(isset($data["address"]))
            $data["address"]="'".mysql_escape_string($data["address"])."'";
	    if(isset($data["city"]))
            $data["city"]="'".mysql_escape_string($data["city"])."'";
         if(isset($data["cp"]))
             $data["cp"]="'".mysql_escape_string($data["cp"])."'";

         $this->saveArray("PaymentLog",$data);
     }

     function saveArray($tableName,$dbrow)
     {
         $q="INSERT INTO $tableName (".implode(",",array_keys($dbrow)).") VALUES ('".implode("','",array_values($dbrow))."')";
         $res=$this->db->Execute($q);
     }

     function reformatDate($date)
     {
         $dateParts=explode("/",$date);
         return $dateParts[2]."/".$dateParts[1]."/".$dateParts[0];
     }
     function fuzzyMatchAgainstOrders()
     {
         $q="SELECT id_log,UNIX_TIMESTAMP(date) as unixFecha,destino,destinatario,importe from ASMCODLog where id_order IS NULL";

         $rows=$this->db->ExecuteS($q);
         $nRows=count($rows);

         // Primero se va a intentar simplemente por la cantidad
         for($k=0;$k<$nRows;$k++)
         {
             //$c=utf8_decode($rows[$k]["Consignatario"]);
             $c=$rows[$k]["destinatario"];

             //$c1=utf8_encode($c);
             echo "<hr> Row $k<br>";
             $imp=$rows[$k]["importe"];
             $sq="SELECT id_order from ps_orders o LEFT JOIN ps_address a ON o.id_customer=a.id_customer where total_paid=".$imp." and module='cashondelivery' AND '".mysql_escape_string($c)."' LIKE CONCAT('%',a.lastname,'%') AND ABS(UNIX_TIMESTAMP(invoice_date)-".$rows[$k]["unixFecha"].") < 3600*24*10 GROUP BY (id_order)";
             $results=$psconnection->ExecuteS($sq);
             if(count($results)>1)
             {
                 var_dump($results);
                 echo "RESULTADOS MULTIPLES EN $sq <br><br>";
             }
             if(count($results)==1)
             {
                 echo "MATCHED:: ".$results[0]["id_order"].":".$imp."<br>";
                 $this->db->ExecuteS("UPDATE ASMCODLog set id_order=".$results[0]["id_order"]." WHERE id_log=".$rows[$k]["id_log"]);
             }
             if(count($results)==0)
             {
                 for($j=0;$j<strlen($c);$j++)
                     echo $c[$j]." -- [".ord($c[$j])."]<br>";
                 echo "NOT FOUND :: ".$sq."<br><br>";
             }
         }
         $this->db->Execute("UPDATE ASMCODLog m,MANUAL_ASSIGNMENTS cm set m.id_order=cm.id_order WHERE m.expedicion=cm.Identifier and cm.module='ASM'");
     }
 }
?>
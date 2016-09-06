<?php
//*******************README*****************************************************************************************************************
/*

Voici un fichier d'exemple d'appels à la librairie, il reste donc à cutomiser les paramètres de connexion ci-dessous, le fichier emst.class.php contient chaque méthode
Les fichiers SoapClientEMST.php - emst.class.php doivent être présent pour que cela fonctionne.

*/
//*******************FIN DE README*****************************************************************************************************************


//In faut inclure ce fichier obligatoirement.
include_once("emst.class.php");

	
//On instancie la classe avec IDMLIST, LOGIN, PASSWORD et éventuellement un array avec des options (pour le proxy par exemple)

$test = new EMST(
              2094219773,
              "XXX_WSSOAP",
              "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx");


//Appel de la fonction FIND, avec un array en param
$value1 = $test->Find(array('1','email@address.ext'), true);
echo "<h3>Find avec un tableau de plusieurs utilisateurs</h3>";
if(!is_array($value1)) echo $value1;
else
{
    foreach ($value1  as $datas) {
        foreach ($datas as $data){
            echo $data->string[0].' - '.$data->string[1];
            echo "<br />";
        }
        echo "--------------------<br />";
    }   
}




/*
//Appel de la fonction FIND, avec un email en param
$value = $test->Find('email@address.ext', true);
if(!is_array($value)) echo $value;
else
{
 echo "<h3>Find avec un email</h3>";
foreach ($value  as $datas) {
    foreach ($datas as $data){
        //echo $data[0].' - '.$data[1];
        //echo "<br />";
        var_dump($data);
    }
}   
}
*/

/*

//Appel de la fonction FIND, avec un email en param
$value = $test->Find('email@compa*');
echo "<h3>Find avec un email</h3>";
echo( $value);

$value = $test->Find(array('0','13'),true);
echo "<h3>Find avec un tableau (un seul résultat - réponse multiple)</h3>";
echo($value);

$value = $test->Find(array('0','13'));
echo "<h3>Find avec un tableau (plusieurs résultats - réponse unique)</h3>";
echo($value);

$value = $test->Find(array('0','13'),true);
echo "<h3>Find avec un tableau (plusieurs résultats - réponse multiples)</h3>";
echo($value);
*/

/*
echo "<h3>Abonnement d'un utilisateur (sauf si déjà désabo)</h3>";
echo $test->Subscribe(array('1','email@address.ext'), true, true);
*/

//echo "<h3>Désabonnement d'un utilisateur</h3>";
//echo $test->Unsubscribe(array('1','aa*'), true);  
//echo $test->Unsubscribe(array('1','email@address.ext'), true);


//echo "<h3>suppression d'un utilisateur</h3>";
//echo $test->DeleteUser(array('1','email@address.ext'), true);

/*
echo "<h3>Ajout d'un utilisateur</h3/>";
echo $test->AddUserByEmail('email@address.ext', true);

*/
/*
echo "<h3>Ajout avec modification automatique si existant d'un utilisateur</h3>";
$critere = array(
            0   => array(
                0   => 7,
                1   => "Jean Paul"),
            1   => array(
                0   => 6,
                1   => "Gauthier"
            )
);*/

//echo $test->Add(array('1','domain.com'),$critere);
//echo $test->Add(array('1','email@address.ext'),array(array(1,"email@address.ext"),array(6,"XXX")));


//List Config
//appel d'un listing des configuration d'envoi
//echo $test->ListConfigs(2);


//Track Link
//$Code = '<html><h1>Test</h1><br /><a href="http://www.experian.fr">Experian</a></html>';
//echo $test->TrackLink($Code,$Ishtml=true);

//Get Trigger Camp List
//echo $test->GetTriggerCampList(2);



//Create trigger Camp
/*
$nom = "Gauthier";
$htmlsrc = "<html><h1>Test</h1><br /><a href='http://www.experian.fr'>Experian</a></html>";
$txtsrc = "Test Experian";
$sujet = "Gauthier_sujet";
$id_conf = 0;
echo $test->CreateTriggerCamp($nom, $htmlsrc, $txtsrc, $sujet, $id_conf)
*/

//CreateTriggerCampWithIdCampaign
//echo $test->CreateTriggerCampWithIdCampaign(3467849);

//Send Mail
/*$id_campagne = 10446246;
$id_campagneid_user = 13;
$htmlsrc = "<html><h1>Test</h1><br /><a href='http://www.experian.fr'>Experian</a></html>";
$txtsrc = "Test Experian";
$sujet = "Gauthier_sujet";
echo $test->SendMail($id_campagne, $id_campagneid_user, $htmlsrc, $txtsrc, $sujet, true);*/

?>
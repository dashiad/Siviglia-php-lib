<?php

function callSivigliaForm($sivigliaUrl,$object,$formName,$keys,$params)
{
    $json=array(
        "object"=>$object,
        "name"=>$formName,
        "FIELDS"=>$params,
        "keys"=>$keys
    );
    //open connection
    $ch = curl_init();

    //set the url, number of POST vars, POST data
    echo $sivigliaUrl."/action.php?output=json";
    curl_setopt($ch,CURLOPT_URL, $sivigliaUrl."/action.php?output=json");
    curl_setopt($ch,CURLOPT_POST, 1);
    curl_setopt($ch,CURLOPT_POSTFIELDS, "json=".json_encode($json));
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    //execute post
    $result = curl_exec($ch);
    //close connection
    curl_close($ch);

    return json_decode($result);
}
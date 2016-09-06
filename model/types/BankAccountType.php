<?php namespace lib\model\types;
class BankAccountTypeException extends \lib\model\BaseTypedException
{
    const ERR_INVALID_IBAN = 1;
    const ERR_INVALID_CCC = 2;
    const TXT_INVALID_IBAN= "IBAN no valido";
    const TXT_INVALID_CCC = "CCC no valido";
}
class BankAccountType extends StringType
{
    function __construct($def,$value=false)
    {
        StringType::__construct(array("TYPE"=>"BankAccount","MAXLENGTH"=>10),$value);
    }

    static function validateCCC($ccc)
    {
        //$ccc sería el 20770338793100254321
        $valido = true;

        ///////////////////////////////////////////////////
        //    Dígito de control de la entidad y sucursal:
        //Se multiplica cada dígito por su factor de peso
        ///////////////////////////////////////////////////
        $suma = 0;
        $suma += $ccc[0] * 4;
        $suma += $ccc[1] * 8;
        $suma += $ccc[2] * 5;
        $suma += $ccc[3] * 10;
        $suma += $ccc[4] * 9;
        $suma += $ccc[5] * 7;
        $suma += $ccc[6] * 3;
        $suma += $ccc[7] * 6;

        $division = floor($suma/11);
        $resto    = $suma - ($division  * 11);
        $primer_digito_control = 11 - $resto;
        if($primer_digito_control == 11)
            $primer_digito_control = 0;

        if($primer_digito_control == 10)
            $primer_digito_control = 1;

        if($primer_digito_control != $ccc[8])
            $valido = false;

        ///////////////////////////////////////////////////
        //            Dígito de control de la cuenta:
        ///////////////////////////////////////////////////
        $suma = 0;
        $suma += $ccc[10] * 1;
        $suma += $ccc[11] * 2;
        $suma += $ccc[12] * 4;
        $suma += $ccc[13] * 8;
        $suma += $ccc[14] * 5;
        $suma += $ccc[15] * 10;
        $suma += $ccc[16] * 9;
        $suma += $ccc[17] * 7;
        $suma += $ccc[18] * 3;
        $suma += $ccc[19] * 6;

        $division = floor($suma/11);
        $resto = $suma-($division  * 11);
        $segundo_digito_control = 11- $resto;

        if($segundo_digito_control == 11)
            $segundo_digito_control = 0;
        if($segundo_digito_control == 10)
            $segundo_digito_control = 1;

        if($segundo_digito_control != $ccc[9])
        {
            throw new BankAccountTypeException(BankAccountTypeException::ERR_INVALID_CCC);
        }

        return $valido;
    }



    static function validateIBAN($iban)
    {
        $iban = strtolower(str_replace(' ','',$iban));
        $Countries = array('al'=>28,'ad'=>24,'at'=>20,'az'=>28,'bh'=>22,'be'=>16,'ba'=>20,'br'=>29,'bg'=>22,'cr'=>21,'hr'=>21,'cy'=>28,'cz'=>24,'dk'=>18,'do'=>28,'ee'=>20,'fo'=>18,'fi'=>18,'fr'=>27,'ge'=>22,'de'=>22,'gi'=>23,'gr'=>27,'gl'=>18,'gt'=>28,'hu'=>28,'is'=>26,'ie'=>22,'il'=>23,'it'=>27,'jo'=>30,'kz'=>20,'kw'=>30,'lv'=>21,'lb'=>28,'li'=>21,'lt'=>20,'lu'=>20,'mk'=>19,'mt'=>31,'mr'=>27,'mu'=>30,'mc'=>27,'md'=>24,'me'=>22,'nl'=>18,'no'=>15,'pk'=>24,'ps'=>29,'pl'=>28,'pt'=>25,'qa'=>29,'ro'=>24,'sm'=>27,'sa'=>24,'rs'=>22,'sk'=>24,'si'=>19,'es'=>24,'se'=>24,'ch'=>21,'tn'=>24,'tr'=>26,'ae'=>23,'gb'=>22,'vg'=>24);
        $Chars = array('a'=>10,'b'=>11,'c'=>12,'d'=>13,'e'=>14,'f'=>15,'g'=>16,'h'=>17,'i'=>18,'j'=>19,'k'=>20,'l'=>21,'m'=>22,'n'=>23,'o'=>24,'p'=>25,'q'=>26,'r'=>27,'s'=>28,'t'=>29,'u'=>30,'v'=>31,'w'=>32,'x'=>33,'y'=>34,'z'=>35);

        if(!(strlen($iban) == $Countries[substr($iban,0,2)]))
            throw new BankAccountTypeException(BankAccountTypeException::ERR_INVALID_IBAN);



        $MovedChar = substr($iban, 4).substr($iban,0,4);
        $MovedCharArray = str_split($MovedChar);
        $NewString = "";

        foreach($MovedCharArray AS $key => $value){
            if(!is_numeric($MovedCharArray[$key])){
                    $MovedCharArray[$key] = $Chars[$MovedCharArray[$key]];
            }
            $NewString .= $MovedCharArray[$key];
        }

        if(bcmod($NewString, '97') == 1)
        {
            return TRUE;
        }
        else{
            throw new BankAccountTypeException(BankAccountTypeException::ERR_INVALID_IBAN);
        }
    }

}
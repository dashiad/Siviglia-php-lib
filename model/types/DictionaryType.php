<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 22/06/2016
 * Time: 17:44
 */

namespace lib\model\types;


class DictionaryType extends \lib\model\types\BaseType
{
    function validate($val)
    {
        parent::validate($val);
        switch($this->definition["elements"])
        {
            case "MODEL":
            {
                // Se espera un array de modelos.
                for($k=0;$k<count($val);$k++)
                {

                }
            }
        }
    }
}
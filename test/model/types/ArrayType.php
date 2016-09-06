<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 21/06/2016
 * Time: 16:34
 */

namespace lib\test\model\types;
include_once(LIBPATH."/model/types/BaseType.php");


class ArrayType extends \PHPUnit_Framework_TestCase
{


    function testSimple()
    {
        $def=array("TYPE"=>"Array","ELEMENTS"=>array("TYPE"=>"Integer"));
        $t=new \lib\model\types\ArrayType($def,array(25));
        $this->assertEquals(25,$t[0]);
        $this->assertEquals(1,$t->count());
    }
    function testValidate()
    {
        $def=array("TYPE"=>"Array","ELEMENTS"=>array("TYPE"=>"Integer"));
        $t=new \lib\model\types\ArrayType($def);
        $t->validate(array(25));
        $t->setValue(array(25));
        $this->assertEquals(25,$t[0]);
        $this->assertEquals(1,$t->count());
    }
    function testDoesntValidate()
    {
        $def=array("TYPE"=>"Array","ELEMENTS"=>array("TYPE"=>"Integer","MIN"=>5));
        $t=new \lib\model\types\ArrayType($def);

        $this->setExpectedException('\lib\model\types\ArrayTypeException',
            '',
            \lib\model\types\ArrayTypeException::ERR_ERROR_AT);
        $t->validate(array(25,4));
    }
    function testInvalidAccess()
    {
        $def=array("TYPE"=>"Array","ELEMENTS"=>array("TYPE"=>"Integer","MIN"=>5));
        $t=new \lib\model\types\ArrayType($def);
        $this->setExpectedException('\lib\model\types\ArrayTypeException',
            '',
            \lib\model\types\ArrayTypeException::ERR_INVALID_OFFSET);
        $v=$t[0];
    }
}
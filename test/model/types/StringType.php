<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 21/06/2016
 * Time: 16:34
 */

namespace lib\test\model\types;
include(LIBPATH."/model/types/BaseType.php");


class StringType extends \PHPUnit_Framework_TestCase
{

    /*
     *   class StringTypeException extends BaseTypeException {
      const ERR_TOO_SHORT=100;
      const ERR_TOO_LONG=101;
      const ERR_INVALID_CHARACTERS=102;

      const TXT_TOO_SHORT="El campo debe tener al menos %min% caracteres";
      const TXT_TOO_LONG="El campo debe tener un máximo de %max% caracteres";
      const TXT_INVALID_CHARACTERS="Valor incorrecto";

      const REQ_TOO_SHORT="MINLENGTH";
      const REQ_TOO_LONG="MAXLENGTH";
      const REQ_INVALID_CHARACTERS="REGEXP";
  }
     */
    function testSimple()
    {
        $def=array("TYPE"=>"String");
        $t=new \lib\model\types\StringType($def,"test");
        $this->assertEquals("test",$t);
        $this->assertEquals("test",$t->getValue());
    }
    function testSimple2()
    {
        // Si un tipo tiene un valor por defecto, hasValue() devuelve true, pero hasOwnValue devuelve false
        $def=array("TYPE"=>"String","DEFAULT"=>"Lala");
        $t=new \lib\model\types\StringType($def);
        $this->assertEquals(true,$t->hasValue());
        $this->assertEquals(false,$t->hasOwnValue());
        $t->setValue("Adios");
        $this->assertEquals(true,$t->hasValue());
        $this->assertEquals(true,$t->hasOwnValue());
        $this->assertEquals("Adios",$t->getValue());
    }
    // Testeo de valor NULL
    function testNullValue()
    {
        $def=array("TYPE"=>"String");
        $t=new \lib\model\types\StringType($def);
        $this->assertEquals(false,$t->hasValue());
        $this->assertEquals(false,$t->hasOwnValue());
    }
    // Testeo de valor demasiado corto
    function testShort()
    {
        $def=array("TYPE"=>"String","MINLENGTH"=>4);
        $t=new \lib\model\types\StringType($def);
        $this->setExpectedException('\lib\model\types\StringTypeException',
            '',
            \lib\model\types\StringTypeException::ERR_TOO_SHORT);
        $t->validate("aa");
    }
    function testNotShort()
    {
        $def=array("TYPE"=>"String","MINLENGTH"=>4);
        $t=new \lib\model\types\StringType($def);
        $t->validate("aaaa");
        $t->setValue("aaaa");
        $this->assertEquals("aaaa",$t->getValue());
    }
    // Testeo de valor demasiado largo
    function testLong()
    {
        $def=array("TYPE"=>"String","MAXLENGTH"=>4);
        $t=new \lib\model\types\StringType($def);
        $this->setExpectedException('\lib\model\types\StringTypeException',
            '',
            \lib\model\types\StringTypeException::ERR_TOO_LONG);
        $t->validate("aaaaa");
    }
    function testNotLong()
    {
        $def=array("TYPE"=>"String","MAXLENGTH"=>4);
        $t=new \lib\model\types\StringType($def);
        $t->validate("aaaa");
        $t->setValue("aaaa");
        $this->assertEquals("aaaa",$t->getValue());
    }
    // Testeo de valor que no hace match de regexp
    function testNotRegexp()
    {
        $def=array("TYPE"=>"String","REGEXP"=>'/^[^a]*$/');
        $t=new \lib\model\types\StringType($def);
        $this->setExpectedException('\lib\model\types\StringTypeException',
            '',
            \lib\model\types\StringTypeException::ERR_INVALID_CHARACTERS);
        $t->validate("aaaaa");
    }
    // Testeo de valor que hace match de regexp
    function testRegexp()
    {
        $def=array("TYPE"=>"String","REGEXP"=>'/^a+$/');
        $t=new \lib\model\types\StringType($def);
        $t->validate("aaaaa");
        $t->setValue("aaaa");
        $this->assertEquals("aaaa",$t->getValue());
    }
}
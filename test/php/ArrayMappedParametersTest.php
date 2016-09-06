<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 21/09/15
 * Time: 14:30
 */

namespace lib\test\php;
use lib\php\ArrayMappedParameters;
use lib\php\ArrayMappedParametersException;

class aReq extends ArrayMappedParameters
{
    var $a;
    var $b;
    var $c;
}
class bReq extends aReq {}
/*
 * Definicion de clase, con 2 parametros requeridos, uno opcional, uno con valor por defecto.
 */

class a extends ArrayMappedParameters
{
    var $one;
    var $two;
    var $three;
    var $four=4;
    var $five;
    static $__definition=array("fields"=>array("three"=>array("required"=>false)));
}
class b extends ArrayMappedParameters
{
    var $aRel;
    var $optRel;
    static $__definition=array(
        "fields"=>array(
            "aRel"=>array("relation"=>'\lib\test\php\aReq'),
            "optRel"=>array("required"=>false)
        ));
}
class c extends ArrayMappedParameters
{
    var $aRel;
    static $__definition=array(
        "fields"=>array(
            "aRel"=>array("instanceof"=>'\lib\test\php\aReq')
        ));
}


class ArrayMappedParametersTest extends \PHPUnit_Framework_TestCase{
    function testUnserialize()
    {
        $aReqInst=new aReq(array("a"=>1,"b"=>2,"c"=>3));
        $this->assertEquals($aReqInst->a,1);
    }

    function testRequiredParameter()
    {
        $this->setExpectedException('\lib\php\ArrayMappedParametersException',
                                    '',
                                    ArrayMappedParametersException::ERR_REQUIRED_PARAMETER);
        $aInst=new a(array("one"=>1,"two"=>2));
    }
    function testRequiredParameter2()
    {

        $aInst=new a(array("one"=>1,"two"=>2,"five"=>5));
        $this->assertEquals($aInst->five,5);
    }
    // Test de relacion, enviando un array como parametro.
    function testRelation1()
    {
        $def=array("aRel"=>array(array("a"=>1,"b"=>2,"c"=>3),array("a"=>3,"b"=>4,"c"=>5)));
        $bInst=new b($def);
        $this->assertEquals(2,count($bInst->aRel));
        $className=get_class($bInst->aRel[0]);
        $this->assertEquals($className,'lib\test\php\aReq');
        $this->assertEquals(2,$bInst->aRel[0]->b);
    }
    // Test de relacion, enviando una instancia como parametro.
    function testRelation2()
    {
        $def=array("aRel"=>new aReq(array("a"=>1,"b"=>2,"c"=>3)));
        $bInst=new b($def);
        $this->assertEquals(1,count($bInst->aRel));
        $className=get_class($bInst->aRel[0]);
        $this->assertEquals($className,'lib\test\php\aReq');
        $this->assertEquals(2,$bInst->aRel[0]->b);
    }
    // Test de relacion, enviando un array con una instancia como parametro.
    function testRelation3()
    {
        $def=array("aRel"=>array(new aReq(array("a"=>1,"b"=>2,"c"=>3))));
        $bInst=new b($def);
        $this->assertEquals(1,count($bInst->aRel));
        $className=get_class($bInst->aRel[0]);
        $this->assertEquals($className,'lib\test\php\aReq');
        $this->assertEquals(2,$bInst->aRel[0]->b);
    }
    // Test de relacion, enviando un array con una instancia incorrecta como parametro
    function testRelation4()
    {
        $v=new a(array("one"=>1,"two"=>2,"five"=>5));
        $def=array("aRel"=>array($v));

        $this->setExpectedException('\lib\php\ArrayMappedParametersException',
            '',
            ArrayMappedParametersException::ERR_UNEXPECTED_CLASS);
        $bInst=new b($def);
    }
    // Test de relacion, enviando un valor que no es un array o un objeto.

    // Test de relacion, enviando un array asociativo como parametro
    function testRelation6()
    {
        $def=array("aRel"=>array("a"=>1,"b"=>2,"c"=>3));
        $bInst=new b($def);
        $this->assertEquals(1,count($bInst->aRel));
        $className=get_class($bInst->aRel[0]);
        $this->assertEquals($className,'lib\test\php\aReq');
        $this->assertEquals(2,$bInst->aRel[0]->b);
    }
    // Test de relacion, enviando una clase derivada como parametro
    function testRelation7()
    {
        $def=array("aRel"=>new bReq(array("a"=>1,"b"=>2,"c"=>3)));
        $bInst=new b($def);
        $this->assertEquals(1,count($bInst->aRel));
        $className=get_class($bInst->aRel[0]);
        $this->assertEquals(true,is_a($bInst->aRel[0],'\lib\test\php\bReq'));
        $this->assertEquals(2,$bInst->aRel[0]->b);
    }

    function testInstanceOf()
    {
        $def=array("aRel"=>array("a"=>1,"b"=>2,"c"=>3));
        $cInst=new c($def);
        $className=get_class($cInst->aRel);
        $this->assertEquals($className,'lib\test\php\aReq');
        $this->assertEquals(2,$cInst->aRel->b);

    }

}

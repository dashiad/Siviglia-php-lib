<?php
/**
 * Created by PhpStorm.
 * User: JoseMaria
 * Date: 24/09/15
 * Time: 15:36
 */

namespace Vocento\PublicationBundle\Tests\Lib\HTTP\Domain;
use Vocento\PublicationBundle\Lib\HTTP\Domain\Domain;
use Vocento\PublicationBundle\Lib\HTTP\Domain\DomainException;


class DomainTest extends \PHPUnit_Framework_TestCase {

    var $baseDomain;
    function SetUp()
    {
        $this->baseDomain=new Domain("http://www.abc.es");
    }

    function testDomainConstructor1()
    {
        $this->assertEquals($this->baseDomain->domain, 'http://www.abc.es');
    }

    function testDomainConstructor2()
    {
        $this->assertEquals($this->baseDomain->domainName, 'www.abc.es');
    }

    function testDomainConstructor3()
    {
        $this->assertEquals($this->baseDomain->domainParts[0], 'www');
    }

    function testDomainConstructor4()
    {
        $this->assertEquals($this->baseDomain->domainParts[2], 'es');
    }

    function testDomainConstructor5()
    {
        $this->assertEquals($this->baseDomain->info["scheme"], 'http');
    }

    function testDomainConstructor6()
    {
        $this->assertEquals($this->baseDomain->getScheme(), "http");
    }

    function testDomainConstructor7()
    {
        $this->assertEquals($this->baseDomain->isSecure(), false);
    }

    function testDomainConstructorError()
    {
        $this->setExpectedException('\Vocento\PublicationBundle\Lib\HTTP\Domain\DomainException',
            '',
            DomainException::ERR_INVALID_DOMAIN);
        $dom=new Domain("www.abc.es");
    }

    function testDomainConstructorError2()
    {
        $this->setExpectedException('\Vocento\PublicationBundle\Lib\HTTP\Domain\DomainException',
            '',
            DomainException::ERR_INVALID_DOMAIN);
        $dom=new Domain("a.es");
    }

    function testDomainPrefix()
    {
        $this->assertEquals($this->baseDomain->getDomainPrefix(),"www");
    }
    function testDomainPrefix2()
    {
        $this->assertEquals($this->baseDomain->getDomainPrefix(array("www")),true);
    }
    function testDomainPrefix3()
    {
        $this->assertEquals($this->baseDomain->getDomainPrefix(array("aaa")),false);
    }
}
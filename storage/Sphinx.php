<?php
namespace platform\storage;
include_once("lib/storage/Sphinx/Sphinx.php");

class Sphinx extends \SphinxClient
{
    private function __construct()
    {
        \SphinxClient::SphinxClient();
        $this->SetServer(SPHINX_HOST,SPHINX_PORT);
        $this->SetConnectTimeout ( 1 );
        $this->SetArrayResult ( true );
        $this->SetWeights ( array ( 100, 1 ) );
    }
}

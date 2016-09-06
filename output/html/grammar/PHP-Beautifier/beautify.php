<?php
/**
 * Command line beautifier
 * 
 * First parameter is input file/directory and the second parameter is the output file/directory
 * If the second parameter is not specified then the original files will be overwritten
 */
include 'PhpBeautifier.inc';

//command line
if ( isset($_SERVER['argv'][1]) )
{
	$beautify = new PhpBeautifier();
	$beautify -> tokenSpace = true;
	$beautify -> blockLine = true;
	$beautify -> optimize = true;
	
	if ( is_dir( $_SERVER['argv'][1] ) )
	{
		$beautify -> folder( $_SERVER['argv'][1],$_SERVER['argv'][2]);
	} else
	{
		$beautify -> file( $_SERVER['argv'][1],$_SERVER['argv'][2]);
	}
	
}

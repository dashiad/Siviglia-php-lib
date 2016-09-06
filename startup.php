<?php
include_once("init.php");

global $currentProject;
$router=$currentProject->getRouter();
$router->route($request);
Registry::save();
$currentProject->cleanup();

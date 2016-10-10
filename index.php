<?php

/* To handle 401 access, else all calls will end in error */
include './classes/Config.php';
Config::init();
Config::loadClass('BotSector');

$botSector = new BotSector();
try
{
	$botSector->update();
	readfile('./static/app.html');
}
catch (Exception $e)
{
	echo 'An error occurred: '.$e->getMessage();
}

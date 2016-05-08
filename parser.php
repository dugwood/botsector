<?php

/**
 * Parser for log files
 *
 * @author Yvan Taviaud / DugWood - www.dugwood.com
 * @since 22/12/2013
 */
set_time_limit(180);
include './classes/Config.php';
Config::init();
Config::loadClass('BotSector');

if (Config::$demo === true)
{
	echo json_encode(array('status' => 'done'));
	exit;
}

$botSector = new BotSector();
echo json_encode($botSector->analyzeFile());

<?php

/**
 * Main index
 *
 * @author Yvan Taviaud / DugWood - www.dugwood.com
 * @since 22/12/2013
 */
include './classes/Config.php';
Config::init();
Config::loadClass('BotSector');

$botSector = new BotSector();

try
{
	$action = isset($_GET['action']) ? $_GET['action'] : '';
	$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
	$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('m');
	$domain = isset($_GET['domain']) ? (int) $_GET['domain'] : 0;
	$directory = isset($_GET['directory']) ? (int) $_GET['directory'] : 0;
	$crawler = isset($_GET['crawler']) ? (int) $_GET['crawler'] : 0;
	$type = isset($_GET['type']) ? (int) $_GET['type'] : 0;
	$graph = isset($_GET['graph']) ? $_GET['graph'] : '';

	switch ($action)
	{
		case 'years':
		case 'months':
		case 'domains':
		case 'directories':
		case 'crawlers':
		case 'types':
			$filters = $botSector->getFilters($action, $year, $month, ['domain' => $domain, 'directory' => $directory, 'crawler' => $crawler, 'type' => $type]);
			echo json_encode($filters);
			return;

		case 'graph':
			$plot = $botSector->getGraph($graph, $year, $month, $domain, $directory, $crawler, $type);
			echo json_encode($plot);
			return;

		case 'exportBots':
			$bots = $botSector->exportBots();
			echo $bots;
			return;
	}
}
catch (Exception $e)
{
	echo 'An error occurred: '.$e->getMessage();
}

<?php

/**
 * Configuration class
 */
set_exception_handler(array('Config', 'handleException'));

class Config
{

	private static $config = array();

	const development = false;

	public static $demo = false;

	public static function init()
	{
		if (strpos($_SERVER['REQUEST_URI'], '.php') !== false)
		{
			header('Content-Type: text/html; charset=utf-8');
		}
		ini_set('display_errors', 0);
		$configFile = __DIR__.'/../config/config.ini.php';
		if (defined('DEV_SERVER') && DEV_SERVER === true)
		{
			ini_set('display_errors', 1);
			error_reporting(E_ALL | E_STRICT);
			$configFile = __DIR__.'/../../config/dev.ini.php';
			self::loadClass('Debug_Time');
			self::loadClass('Debug_RegExp');
		}
		elseif (isset($_SERVER['BOTSECTORDEMO']))
		{
			$configFile = __DIR__.'/../../config/demo.ini.php';
		}
		self::$config = parse_ini_file($configFile, true);

		if (self::$config === false)
		{
			throw new Exception('Configuration file could not be read.');
		}

		if (empty(self::$config['security']['user']) || empty(self::$config['security']['password']))
		{
			throw new Exception('Security credentials are empty');
		}

		if (self::$config['security']['user'] === 'demo' && self::$config['security']['password'] === 'demo')
		{
			self::$demo = true;
		}

		if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_USER'] !== self::$config['security']['user'] || !isset($_SERVER['PHP_AUTH_PW']) || $_SERVER['PHP_AUTH_PW'] !== self::$config['security']['password'])
		{
			header('WWW-Authenticate: Basic realm="BotSector login"');
			header('HTTP/1.0 401 Unauthorized');
			throw new Exception('Wrong security credentials');
		}
	}

	public static function get($group, $variable)
	{
		if (!isset(self::$config[$group][$variable]) || empty(self::$config[$group][$variable]))
		{
			throw new Exception('Configuration option not set: «'.$variable.'»');
		}
		return self::$config[$group][$variable];
	}

	public static function loadClass($class)
	{
		if (!class_exists($class, false))
		{
			include __DIR__.'/'.str_replace('_', '/', preg_replace('~[^a-z_]+~i', '', $class)).'.php';
		}
	}

	public static function handleException($e)
	{
		echo 'Script ended because of an error: '.$e->getMessage();
		if (self::development === true)
		{
			echo '<p>Time taken: <strong>'.(time() - $_SERVER['REQUEST_TIME']).' seconds</strong><br/>';
			echo 'Memory peak usage: <strong>'.number_format(memory_get_peak_usage() / 1024 / 1024, 3).'MB</strong></p>';
		}
		exit;
	}

}

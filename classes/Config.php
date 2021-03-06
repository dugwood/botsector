<?php

/**
 * Configuration class
 */
set_exception_handler(array('Config', 'handleException'));

class Config
{

	private static $config = array();
	public static $development = false;
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
			self::loadClass('Debug_Time');
			self::loadClass('Debug_RegExp');
		}
		else
		{
			self::$development = false;
		}
		self::$config = parse_ini_file($configFile, true);

		if (self::$config === false)
		{
			throw new Exception('Configuration file could not be read (config/config.ini.php).');
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

	public static function set($group, $variable, $value)
	{
		$configFile = __DIR__.'/../config/config.ini.php';
		$config = file($configFile);
		for ($line = 0; $line < count($config); $line ++)
		{
			/* Look for the group */
			if (trim($config[$line]) === '['.$group.']')
			{
				break;
			}
		}
		if ($line === count($config))
		{
			throw new Exception('Can\'t find group «'.$group.'» in configuration file (config/config.ini.php)');
		}
		for (; $line < count($config); $line ++)
		{
			/* Look for the variable */
			if (strpos(trim($config[$line]), $variable.' =') === 0)
			{
				break;
			}
		}
		if ($line === count($config))
		{
			throw new Exception('Can\'t find variable «'.$variable.'» in configuration file (config/config.ini.php)');
		}
		$config[$line] = $variable.' = '.$value;
		if (file_put_contents($configFile, implode('', $config)) === false)
		{
			throw new Exception('Configuration file (config/config.ini.php) can\'t be written to.');
		}
		self::init();
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
		if (self::$development === true)
		{
			echo '<p>Time taken: <strong>'.(time() - $_SERVER['REQUEST_TIME']).' seconds</strong><br/>';
			echo 'Memory peak usage: <strong>'.number_format(memory_get_peak_usage() / 1024 / 1024, 3).'MB</strong></p>';
		}
		exit;
	}

}

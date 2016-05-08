<?php

/**
 * Débug de temps (diff de temps entre 2 points dans le code)
 *
 * @author Yvan
 * @since 07/01/2009
 */
class Debug_Time
{

	private static function output($text, $v)
	{
		if (!isset($_SERVER['REQUEST_URI']))
		{
			echo "\n------------------------------------------------\n";
			echo '	', number_format(($v) * 1000, 3), ' ms -- ', $text, "\n";
			echo "------------------------------------------------\n";
		}
		else
		{
			echo '<div class="error">', number_format(($v) * 1000, 3), ' ms -- ', $text, '</div>';
		}
	}

	/**
	 * Dump du temps écoulé
	 *
	 * @param string	$text	Texte à afficher à côté du débug. Si «true», dump de tous les temps dans l'ordre de rapidité
	 * @param integer	$m		Si non nul, nombre d'appels au bout dequel le script s'arrête via exit();
	 * @return string
	 * */
	public static function show($text = '', $m = 0)
	{
		static $max = 0;
		static $time = 0;
		static $buffer = array();
		static $bufferCount = 0;
		if ($text === true)
		{
			asort($buffer);
			if (isset($_SERVER['REQUEST_URI']))
			{
				echo '<div class="error"><strong>Sorted results</strong></div>';
			}
			else
			{
				echo "\n---------------- Sorted results ----------------\n";
			}
			foreach ($buffer as $k => $v)
			{
				self::output($k, $v);
			}
			return true;
		}
		if ($m !== 0)
		{
			$max = $m;
		}
		$max--;
		if ($time === 0)
		{
			$time = microtime(true);
		}
		$t = microtime(true);
		if ($text === '')
		{
			$text = $bufferCount;
		}
		$buffer[$text] = $t - $time;
		$bufferCount++;
		self::output($text, $t - $time);
		$time = $t;
		if ($max === 0)
		{
			die('Arrêt débug');
		}
	}

}
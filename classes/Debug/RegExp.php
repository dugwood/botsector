<?php

/**
 * Débug d'une expression régulière
 *
 * @author Yvan Taviaud - dugwood.com
 * @since 18/01/2008
 */
class Debug_RegExp
{

	/**
	 * Teste une expression régulière pour tous les caractères jusqu'à trouver la partie bloquante
	 *
	 * @param string $reg Expression régulière
	 * @param string $text Texte de contrôle
	 * @param string $startAt Démarrage à la première occurrence de cette chaîne
	 * @return array Informations sur le problème
	 */
	public static function debug($reg, $text, $startAt = false)
	{
		/** Test pour voir si l'expression ne serait pas déjà bonne ! */
		if (preg_match_all($reg, $text, $matches) > 0)
		{
			return true;
		}

		/** Recherche des délimiteurs */
		$delimiter = $reg[0];
		$last = strrpos($reg, $delimiter);
		if ($last === false || $last < strlen($reg) - 5)
		{
			return 'Pas de délimiteurs ?';
		}
		/** Recherche des options */
		$options = substr($reg, $last + 1);
		$reg = substr($reg, 1, $last - 1);
		$lastWorkingReg = array();
		$longuestMatch = 0;
		if ($startAt !== false)
		{
			$pos = strpos($text, $startAt);
			if ($pos !== false)
			{
				$text = substr($text, $pos);
			}
		}
		for ($i = 0, $max = strlen($reg); $i < $max; $i++)
		{
			/** Test de toutes les expressions régulières possibles */
			$tempReg = substr($reg, 0, $i + 1);
			$lastChar = $tempReg[strlen($tempReg) - 1];
			if ($lastChar === '^' || $lastChar === '\\')
			{
				continue;
			}
			/* Recherche des parenthèses */
			$notClosedParenthesis = substr_count($tempReg, '(') - substr_count($tempReg, '\(') - (substr_count($tempReg, ')') - substr_count($tempReg, '\)'));
			if ($notClosedParenthesis > 0)
			{
				$tempReg .= str_repeat(')', $notClosedParenthesis);
			}
			$result = @preg_match_all($delimiter.$tempReg.$delimiter.$options, $text, $matches);
			if ($result !== 0 && $result !== false && ($length = strlen($matches[0][0])) > 0 && strlen($matches[0][0]) > $longuestMatch)
			{
				$longuestMatch = $length;
				/** L'expression régulière a fonctionné */
				$pos = strpos($text, $matches[0][0]) + strlen($matches[0][0]);
				if ($pos < 10)
				{
					$tempText = substr($text, 0, $pos);
				}
				else
				{
					$tempText = substr($text, $pos - 10, 10);
				}
				$tempText .= '[==>>chr='.substr($text, $pos, 1).', ord='.ord(substr($text, $pos, 1)).']'.substr($text, $pos, 200);
				$lastWorkingReg = array('Expression' => $delimiter.str_replace('\n', "\\n\n", $tempReg).$delimiter.$options.'[==>>chr='.substr($reg, $i + 1, 1).', ord='.ord(substr($reg, $i + 1, 1)).']'.substr($reg, $i + 1, 100), 'Texte' => $tempText, 'Résultats' => $matches);
			}
		}
		if ($lastWorkingReg === array())
		{
			return __METHOD__.': Aucun match n\'a pu avoir lieu';
		}
		return $lastWorkingReg;
	}

}
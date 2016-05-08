<?php

/**
 * Main class for dealing with almost everything here
 *
 * @author Yvan Taviaud / DugWood - www.dugwood.com
 * @since 22/12/2013
 */
Config::loadClass('Database');

class BotSector
{

	const browsers = 1;
	const unknownBots = 2;
	const realBrowsers = 3;

	/** @var string $directory Directory where to look for files to analyze */
	private $directory = '';

	/** @var array $extensions Extensions to analyze */
	private $extensions = array();

	/** @var Database $this->db */
	private $db = false;

	/** @var array $types */
	private $types = array(
		1 => array('HTML', '66CC66', array('html', 'htm', 'php', 'php3', 'php4', 'php5', 'phtml', 'cgi', 'asp', 'aspx', 'jsp', 'pl', 'dll')),
		2 => array('XML', '008800', array('rss', 'xml')),
		3 => array('RESOURCES', 'FF6666', array('js', 'css', 'swf', 'pdf', 'zip', 'rar', 'xls', 'xlsx', 'doc', 'docx')), // DO NOT ADD «txt» filetype!
		4 => array('MEDIA', '6666FF', array('jpg', 'jpeg', 'gif', 'png', 'ico', 'mp4', 'flv', 'mp3', 'ogg')),
		5 => array('ROBOTS', '888888', array()),
		6 => array('UNKNOWN', '666666', array()));
	private $removedDirectories = array();

	public function __construct()
	{
		$directory = Config::get('logs', 'directory');
		$extensions = explode(',', Config::get('logs', 'extensions'));
		if ($directory === false || !is_dir($directory))
		{
			throw new Exception('Directory defined as «logs.directory» doesn\'t exists');
		}
		if (count($extensions) < 1 || count($extensions) === 1 && $extensions[0] === '')
		{
			throw new Exception('No extensions defined in «logs.extensions»');
		}

		$this->directory = rtrim($directory, '/').'/';
		$this->extensions = array_flip($extensions);

		$this->db = Database::getInstance();

		return true;
	}

	public function getFilters($type, $year, $month, $parameters)
	{
		$return = array();
		$where = array();
		if ($type === 'domains' || $type === 'crawlers' || $type === 'types')
		{
			$this->db->bind('YEARMONTH', sprintf('%04d-%02d-%%', $year, $month));
			$where[] = 'BST_DATE LIKE :YEARMONTH';
		}
		if (!empty($parameters['domain']))
		{
			$this->db->bind('DOMAIN', $parameters['domain']);
			$where[] = 'bs.BDM_ID = :DOMAIN';
		}
		if (!empty($parameters['directory']))
		{
			$this->db->bind('DIRECTORY', $parameters['directory']);
			$where[] = 'bs.BDR_ID = :DIRECTORY';
		}
		if (!empty($parameters['crawler']) && $parameters['crawler'] > 0)
		{
			$this->db->bind('CRAWLER', $parameters['crawler']);
			$where[] = 'bs.BCR_ID = :CRAWLER';
		}
		$sqlTypes = array();
		if (!empty($parameters['type']) && isset($this->types[$parameters['type']]))
		{
			$sqlTypes[$parameters['type']] = 'BST_'.$this->types[$parameters['type']][0].'_HITS';
		}
		else
		{
			foreach ($this->types as $id => $t)
			{
				$sqlTypes[$id] = 'BST_'.$t[0].'_HITS';
			}
		}
		if (Config::$demo === true)
		{
			$this->db->bind('DUGWOOD', 'www.dugwood.%');
			$where[] = 'bs.BDM_ID IN (SELECT BDM_ID FROM BOTSECTOR_DOMAINS WHERE BDM_DOMAIN LIKE :DUGWOOD)';
		}
		if (count($where) === 0)
		{
			$where = '1 = 1';
		}
		else
		{
			$where = implode(' AND ', $where);
		}

		switch ($type)
		{
			case 'domains':
				$return[0] = array('id' => 0, 'domain' => 'All', 'hits' => 0);
				$results = $this->db->select('SELECT bs.BDM_ID, SUM('.implode(' + ', $sqlTypes).') hits, BDM_DOMAIN FROM BOTSECTOR_STATISTICS bs LEFT JOIN BOTSECTOR_DOMAINS bd USING(BDM_ID) WHERE '.$where.' GROUP BY bs.BDM_ID HAVING hits > 0 ORDER BY hits DESC');
				foreach ($results as $r)
				{
					$return[] = array('id' => (int) $r['BDM_ID'], 'domain' => $r['BDM_DOMAIN'], 'hits' => (int) $r['hits']);
					$return[0]['hits'] += (int) $r['hits'];
				}
				break;

			case 'directories':
				if (empty($parameters['domain']))
				{
					break;
				}
				$return[0] = array('id' => 0, 'directory' => 'All', 'hits' => 0);
				$results = $this->db->select('SELECT bs.BDR_ID, SUM('.implode(' + ', $sqlTypes).') hits, BDR_DIRECTORY FROM BOTSECTOR_STATISTICS bs LEFT JOIN BOTSECTOR_DIRECTORIES bd USING(BDR_ID) WHERE '.$where.' GROUP BY bs.BDR_ID HAVING hits > 0 ORDER BY hits DESC');
				foreach ($results as $r)
				{
					$return[] = array('id' => (int) $r['BDR_ID'], 'directory' => $r['BDR_DIRECTORY'], 'hits' => (int) $r['hits']);
					$return[0]['hits'] += (int) $r['hits'];
				}
				break;

			case 'crawlers':
				$return[0] = array('id' => 0, 'name' => 'All', 'hits' => 0);
				$results = $this->db->select('SELECT bs.BCR_ID, SUM('.implode(' + ', $sqlTypes).') hits, BCR_NAME FROM BOTSECTOR_STATISTICS bs LEFT JOIN BOTSECTOR_CRAWLERS bc USING(BCR_ID) WHERE '.$where.' GROUP BY bs.BCR_ID HAVING hits > 0 ORDER BY hits DESC');
				$ignoreCrawler = 0;
				if (!empty($parameters['crawler']) && $parameters['crawler'] < 0)
				{
					$ignoreCrawler = (string) abs($parameters['crawler']);
				}
				foreach ($results as $r)
				{
					$return[] = array('id' => (int) $r['BCR_ID'], 'name' => $r['BCR_NAME'], 'hits' => (int) $r['hits'], 'ignore' => (int) $r['BCR_ID'] === self::browsers);
					if ($r['BCR_ID'] !== $ignoreCrawler)
					{
						$return[0]['hits'] += (int) $r['hits'];
					}
				}
				break;

			case 'types':
				$return[0] = array('id' => 0, 'name' => 'All', 'hits' => 0);
				$sql = array();
				foreach ($sqlTypes as $id => $t)
				{
					$sql[] = 'SUM('.$t.') hits_'.$id;
				}
				$result = $this->db->select('SELECT '.implode(', ', $sql).' FROM BOTSECTOR_STATISTICS bs WHERE '.$where, true);
				foreach ($result as $k => $r)
				{
					$id = (int) substr($k, 5);
					$return[$id] = array('id' => $id, 'name' => $this->types[$id][0], 'hits' => (int) $r);
					$return[0]['hits'] += (int) $r;
				}
				break;

			case 'years':
				$results = $this->db->select('SELECT YEAR(MIN(BST_DATE)) m, YEAR(MAX(BST_DATE)) M FROM BOTSECTOR_STATISTICS bs WHERE '.$where, true);
				if (isset($results['m']))
				{
					for ($year = (int) $results['m']; $year <= $results['M']; $year++)
					{
						$return[] = $year;
					}
				}
				break;

			case 'months':
				$this->db->bind('YEAR', $year.'-%');
				$where .= ' AND BST_DATE LIKE :YEAR';
				$results = $this->db->select('SELECT MONTH(BST_DATE) m, SUM('.implode(' + ', $sqlTypes).') FROM BOTSECTOR_STATISTICS bs WHERE '.$where.' GROUP BY MONTH(BST_DATE)');
				foreach ($results as $r)
				{
					$return[] = (int) $r['m'];
				}
				break;
		}

		return $return;
	}

	public function getGraph($graph, $year, $month, $domain, $directory, $crawler, $type)
	{
		/* List of dates */
		$dates = array();
		$days = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
		for ($day = 1; $day <= $days; $day++)
		{
			$dates[] = sprintf('%04d-%02d-%02d', $year, $month, $day);
		}
		/* Filters */
		$this->db = Database::getInstance();
		$where = array();
		$binds = array();
		if ($domain > 0)
		{
			$binds['DOMAIN'] = $domain;
			$where[] = 'bs.BDM_ID = :DOMAIN';
		}
		if ($directory > 0)
		{
			$binds['DIRECTORY'] = $directory;
			$where[] = 'bs.BDR_ID = :DIRECTORY';
		}
		if ($crawler !== 0)
		{
			if ($crawler < 0)
			{
				$binds['CRAWLER'] = abs($crawler);
				$where[] = 'bs.BCR_ID != :CRAWLER';
			}
			else
			{
				$binds['CRAWLER'] = $crawler;
				$where[] = 'bs.BCR_ID = :CRAWLER';
			}
		}
		$sqlTypeHits = $sqlTypeSizes = array();
		if ($type > 0)
		{
			$sqlTypeHits[$type] = 'bs.BST_'.$this->types[$type][0].'_HITS';
			$sqlTypeSizes[$type] = 'bs.BST_'.$this->types[$type][0].'_SIZE';
		}
		else
		{
			foreach ($this->types as $id => $t)
			{
				$sqlTypeHits[$id] = 'bs.BST_'.$t[0].'_HITS';
				$sqlTypeSizes[$id] = 'bs.BST_'.$t[0].'_SIZE';
			}
		}

		$binds['YEARMONTH'] = sprintf('%04d-%02d-%%', $year, $month);
		$where[] = 'bs.BST_DATE LIKE :YEARMONTH';
		if (Config::$demo === true)
		{
			$binds['DUGWOOD'] = 'www.dugwood.%';
			$where[] = 'bs.BDM_ID IN (SELECT BDM_ID FROM BOTSECTOR_DOMAINS WHERE BDM_DOMAIN LIKE :DUGWOOD)';
		}
		$this->db->bind($binds);
		$where = implode(' AND ', $where);

		$columns = array('x');
		foreach ($dates as $d)
		{
			$columns[] = $d;
		}
		$json = array('chart' => $graph, 'columns' => array($columns));

		switch ($graph)
		{
			case 'topBots':
				/* First look for the top bots */
				$bots = $this->db->select('SELECT bs.BCR_ID, bc.BCR_NAME FROM BOTSECTOR_STATISTICS bs LEFT JOIN BOTSECTOR_CRAWLERS bc USING(BCR_ID) WHERE '.$where.' AND bs.BCR_ID != '.self::browsers.' GROUP BY bs.BCR_ID ORDER BY SUM('.implode(' + ', $sqlTypeHits).') DESC LIMIT 10', 'BCR_ID');

				if (count($bots) === 0)
				{
					break;
				}
				/* Then select hits */
				$sql = array();
				foreach ($bots as $bot => &$name)
				{
					$name = $name['BCR_NAME'];
					$this->db->bind('BOT'.$bot, $bot);
					$sql[] = ':BOT'.$bot;
					unset($name);
				}
				$this->db->bind($binds);
				$results = $this->db->select('SELECT BST_DATE, BCR_ID, SUM('.implode(' + ', $sqlTypeHits).') hits FROM BOTSECTOR_STATISTICS bs WHERE '.$where.' AND bs.BCR_ID IN ('.implode(', ', $sql).') GROUP BY BST_DATE, BCR_ID ORDER BY hits DESC');
				$hits = array();
				foreach ($dates as $d)
				{
					foreach ($bots as $bot => $name)
					{
						$hits[$bot][$d] = 0;
					}
				}
				foreach ($results as $result)
				{
					$hits[$result['BCR_ID']][$result['BST_DATE']] = (int) $result['hits'];
				}
				foreach ($bots as $bot => $name)
				{
					$columns = array_values($hits[$bot]);
					array_unshift($columns, $name);
					$json['columns'][] = $columns;
				}
				$json['colours'] = array();
				break;

			case 'botsVsBrowsersHits':
				$results = $this->db->select('SELECT BST_DATE, SUM('.implode(' + ', $sqlTypeHits).') hits, IF(BCR_ID != '.self::browsers.', \'bot\', \'browser\') _group FROM BOTSECTOR_STATISTICS bs WHERE '.$where.' GROUP BY BST_DATE, BCR_ID != '.self::browsers);
				$hits = array();
				foreach ($dates as $d)
				{
					$hits['bot'][$d] = 0;
					$hits['browser'][$d] = 0;
				}
				foreach ($results as $result)
				{
					$hits[$result['_group']][$result['BST_DATE']] = (int) $result['hits'];
				}
				$columns = array_values($hits['bot']);
				array_unshift($columns, 'bots');
				$json['columns'][] = $columns;
				$columns = array_values($hits['browser']);
				array_unshift($columns, 'browsers');
				$json['columns'][] = $columns;
				$json['colours'] = ['ff0000', '0000ff'];
				break;

			case 'botsVsBrowsersBandwidth':
				$results = $this->db->select('SELECT BST_DATE, SUM('.implode(' + ', $sqlTypeSizes).') bandwidth, IF(BCR_ID != '.self::browsers.', \'bot\', \'browser\') _group FROM BOTSECTOR_STATISTICS bs WHERE '.$where.' GROUP BY BST_DATE, BCR_ID != '.self::browsers);
				$bandwidth = array();
				foreach ($dates as $d)
				{
					$bandwidth['bot'][$d] = 0;
					$bandwidth['browser'][$d] = 0;
				}
				foreach ($results as $result)
				{
					$bandwidth[$result['_group']][$result['BST_DATE']] = round((int) $result['bandwidth'] / 1024 / 1024, 3);
				}
				$columns = array_values($bandwidth['bot']);
				array_unshift($columns, 'bots');
				$json['columns'][] = $columns;
				$columns = array_values($bandwidth['browser']);
				array_unshift($columns, 'browsers');
				$json['columns'][] = $columns;
				$json['colours'] = ['ff0000', '0000ff'];
				break;

			case 'fileTypes':
				$sql = array();
				foreach ($sqlTypeHits as $id => $t)
				{
					$sql[] = 'SUM('.$t.') hits_'.$id;
				}
				$results = $this->db->select('SELECT BST_DATE, '.implode(', ', $sql).' FROM BOTSECTOR_STATISTICS bs WHERE '.$where.' GROUP BY BST_DATE');
				$data = array();
				foreach ($dates as $d)
				{
					foreach ($this->types as $t)
					{
						$data[$t[0]][$d] = 0;
					}
				}
				foreach ($results as $result)
				{
					foreach ($result as $k => $r)
					{
						if (strpos($k, 'hits_') === 0)
						{
							$data[$this->types[(int) substr($k, 5)][0]][$result['BST_DATE']] = (int) $r;
						}
					}
				}
				$json['colours'] = array();
				foreach ($this->types as $type)
				{
					$json['colours'][] = $type[1];
				}
				foreach ($this->types as $type)
				{
					$columns = array_values($data[$type[0]]);
					array_unshift($columns, $type[0]);
					$json['columns'][] = $columns;
				}
				break;

			default :
				return false;
		}

		return $json;
	}

	public function getFilesLeft()
	{
		$where = 'BLG_STATUS = \'NEW\'';
		if (Config::get('logs', 'use_server_ip') > 0)
		{
			$this->db->bind('SERVER', $this->getServer());
			$where .= ' AND BLG_SERVER = :SERVER';
		}
		$count = $this->db->select('SELECT COUNT(*) c FROM BOTSECTOR_LOGS WHERE '.$where, true);
		if (isset($count['c']))
		{
			return (int) $count['c'];
		}
		return 0;
	}

	private function getNextFile()
	{
		/* Is there already a log running? */
		$parsing = $this->db->select('SELECT COUNT(*) c FROM BOTSECTOR_LOGS WHERE BLG_STATUS = \'PARSING\'', true);
		if (isset($parsing['c']) && $parsing['c'] > 0)
		{
			/* Fix old parsed files */
			$this->db->update('UPDATE BOTSECTOR_LOGS SET BLG_STATUS = \'NEW\' WHERE BLG_STATUS = \'PARSING\' AND BLG_STARTED < SUBDATE(NOW(), INTERVAL 2 HOUR)');
			return array('status' => 'waiting');
		}
		/* First, let's have a look in the database, there may be some available files */
		$where = 'BLG_STATUS = \'NEW\'';
		if (Config::get('logs', 'use_server_ip') > 0)
		{
			$this->db->bind('SERVER', $this->getServer());
			$where .= ' AND BLG_SERVER = :SERVER';
		}
		$file = $this->db->select('SELECT BLG_ID, BLG_PATH FROM BOTSECTOR_LOGS WHERE '.$where.' ORDER BY BLG_PATH DESC LIMIT 1', true);
		if (isset($file['BLG_ID']) && file_exists($file['BLG_PATH']))
		{
			return array('status' => 'run', 'id' => (int) $file['BLG_ID'], 'path' => $file['BLG_PATH']);
		}

		/* Then let's parse the whole directory for files */
		$files = $this->parseDirectory($this->directory);
		if ($files === false || count($files) === 0)
		{
			throw new Exception('No file could be find in the «logs.directory» with allowed extensions by «logs.extensions»');
		}
		$return = array('status' => 'done');
		foreach ($files as $file)
		{
			$this->db->bind('FILE', $file);
			$where = 'BLG_PATH = :FILE';
			if (Config::get('logs', 'use_server_ip') > 0)
			{
				$this->db->bind('SERVER', $this->getServer());
				$where .= ' AND BLG_SERVER = :SERVER';
			}
			$exists = $this->db->select('SELECT BLG_ID FROM BOTSECTOR_LOGS WHERE '.$where.' LIMIT 1', true);
			if (!isset($exists['BLG_ID']))
			{
				$this->db->bind('FILE', $file);
				$this->db->bind('SERVER', $this->getServer());
				$newId = $this->db->insert('INSERT INTO BOTSECTOR_LOGS (BLG_ID, BLG_SERVER, BLG_PATH, BLG_STATUS) VALUES (NULL, :SERVER, :FILE, \'NEW\')');
				/* It's better to reload so that the lock with «PARSING» works better */
				$return['status'] = 'reload';
			}
		}
		return $return;
	}

	private function parseDirectory($directory)
	{
		$files = array();
		$d = dir($directory);
		while (($entry = $d->read()) !== false)
		{
			if ($entry[0] === '.')
			{
				continue;
			}
			$path = $d->path.$entry;
			if (is_dir($path))
			{
				$files = array_merge($files, $this->parseDirectory($path.'/'));
			}
			elseif (is_file($path) && filemtime($path) < $_SERVER['REQUEST_TIME'] - 7200) // Only files older than 2 hours
			{
				$check = $entry;
				$extension = pathinfo($check, PATHINFO_EXTENSION);
				/* Compressed log files are allowed too */
				if ($extension === 'gz')
				{
					$check = substr($entry, 0, strrpos($entry, '.'));
					$extension = pathinfo($check, PATHINFO_EXTENSION);
				}
				if ($extension === '')
				{
					$check = str_replace('_', '.', $check);
					$extension = pathinfo($check, PATHINFO_EXTENSION);
				}
				if (isset($this->extensions[$extension]))
				{
					$files[] = $path;
				}
			}
		}
		return $files;
	}

	public function analyzeFile()
	{
		/* Let's find a new file to analyze */
		$file = $this->getNextFile();
		if ($file['status'] !== 'run')
		{
			return array('status' => $file['status'], 'time' => 0, 'files' => 0);
		}

		if (Config::development !== true)
		{
			$this->db->bind('ID', $file['id']);
			$this->db->update('UPDATE BOTSECTOR_LOGS SET BLG_STATUS = \'PARSING\', BLG_STARTED = NOW() WHERE BLG_ID = :ID');
		}
		/* Remove old stats */
		$this->db->bind('ID', $file['id']);
		$this->db->delete('DELETE FROM BOTSECTOR_STATISTICS WHERE BLG_ID = :ID');

		$startTime = time();

		$months = array('Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4, 'May' => 5, 'Jun' => 6, 'Jul' => 7, 'Aug' => 8, 'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12);

		$statistics = array();
		if (substr($file['path'], -3) === '.gz')
		{
			$f = gzopen($file['path'], 'r');
		}
		else
		{
			$f = fopen($file['path'], 'r');
		}
		$totalLines = 0;
		if (Config::development === true)
		{
			$microtime = -1 * microtime(true);
		}
		$error = false;
		$defaultStat = array();
		foreach ($this->types as $type)
		{
			$defaultStat[$type[0]] = array(0, 0);
		}
		while (!feof($f))
		{
			$buffer = stream_get_line($f, 8192000); // 4096 * 2000
			$buffer .= stream_get_line($f, 4096, "\n"); // Stop at the end of a line
			if (substr($buffer, -1) !== "\n")
			{
				$buffer .= "\n";
			}
			$lines = substr_count($buffer, "\n");
			$totalLines += $lines;
			$max = preg_match_all('~^[0-9.:]+ - .*? \[(\d+)/([A-Za-z]+)/(\d+):.+?\] "(?:HEAD|GET|POST) https?://([a-zA-Z0-9\.\-_]+?)(?::\d+)?(/.*?) HTTP/1\.." (-|\d+) (-|\d+) ".*?" "(.*?)"$~m', $buffer, $matches);
			/* if ($lines > 10 && $lines * 0.95 >= $max)
			  {
			  echo "<pre>Not parsed lines:\n";
			  for ($i = 0; $i < $max; $i++)
			  {
			  $buffer = str_replace($matches[0][$i]."\n", '', $buffer);
			  }
			  print_r($buffer);
			  throw new Exception('Wrong count: lines ('.$lines.') vs matches ('.$max.')');
			  } */
			$allDates = array();
			$knownDates = array();
			for ($i = 0; $i < $max; $i++)
			{
				$status = (int) $matches[6][$i];
				/* Ignore HTTP statuses below 200 (mostly «100 Continue») */
				if ($status < 200)
				{
					continue;
				}
				$path = $matches[5][$i];
				if ($path === '')
				{
					$path = '/';
				}
				/* Remove Query-string */
				if (($pos = strpos($path, '?')) !== false)
				{
					$path = substr($path, 0, $pos);
				}
				/* If it's an error, group by specific directories, so that it doesn't fill the database */
				if ($status >= 300 && $status !== 304)
				{
					if ($status < 400)
					{
						$prepend = '/botsector-redirects';
					}
					elseif ($status < 500)
					{
						$prepend = '/botsector-client-errors';
					}
					else
					{
						$prepend = '/botsector-server-errors';
					}
					$path = $prepend.substr($path, strrpos($path, '/'));
				}

				$date = &$knownDates[$matches[3][$i]][$matches[2][$i]][$matches[1][$i]];
				if (!isset($date))
				{
					$date = sprintf('%04d-%02d-%02d', $matches[3][$i], $months[$matches[2][$i]], $matches[1][$i]);
				}
				$allDates[$date] = $date;
				if (($crawler = $this->getCrawler($matches[8][$i], $matches[0][$i])) === false)
				{
					$error = true;
				}
				if (($domain = $this->getDomain($matches[4][$i], false)) === false)
				{
					/* Call with 200 status AND (standard browser OR bot reads robots.txt) => domain allowed */
					if (($status < 300 && ($crawler === self::browsers || $crawler !== self::browsers && $matches[5][$i] === '/robots.txt')) === false)
					{
						continue;
					}
					$domain = $this->getDomain($matches[4][$i], true);
				}
				$category = $this->getCategoryAndType($domain, $path);
				$stat = &$statistics[$date][$domain][$category['directory']][$crawler];
				if (!isset($stat))
				{
					$stat = $defaultStat;
				}
				$stat[$category['type']][0] ++;
				$stat[$category['type']][1] += (int) $matches[7][$i];

				unset($stat);
				unset($date);
			}
		}
		fclose($f);
		unset($knownDates);

		if (Config::development === true)
		{
			$microtime += microtime(true);
			echo '<br/>Lines: <strong>'.$totalLines.' - '.ceil($totalLines / $microtime).'/s</strong>';
			if ($error === true)
			{
				throw new Exception('Found errors, no insertion');
			}
		}

		$inserts = 0;
		if (Config::development === true)
		{
			$microtime = -1 * microtime(true);
		}
		/* Start a transaction (this should speed things up) */
		$this->db->begin();
		foreach ($statistics as $date => $dates)
		{
			foreach ($dates as $domain => $domains)
			{
				foreach ($domains as $directory => $directories)
				{
					while (isset($this->removedDirectories[$directory]))
					{
						$directory = $this->removedDirectories[$directory];
					}
					foreach ($directories as $userAgent => $statistic)
					{
						$inserts++;
						$this->insertStatistic($date, $file['id'], $domain, $directory, $userAgent, $statistic);
					}
				}
			}
		}
		/* Force last entries to be injected in database */
		$this->insertStatistic(0, 0, 0, 0, 0, 0);
		$this->db->commit();

		$this->removedDirectories = array();
		if (Config::development === true)
		{
			$microtime += microtime(true);
			echo '<br/>Inserts: <strong>'.$inserts.' - '.ceil($inserts / $microtime).'/s</strong>';
		}
		$this->db->bind('ID', $file['id']);
		if (count($allDates) === 0)
		{
			$this->db->bind('MIN', '0000-00-00');
			$this->db->bind('MAX', '0000-00-00');
		}
		else
		{
			$this->db->bind('MIN', min($allDates));
			$this->db->bind('MAX', max($allDates));
		}
		$this->db->update('UPDATE BOTSECTOR_LOGS SET BLG_MIN_DATE = :MIN, BLG_MAX_DATE = :MAX, BLG_STATUS = \'PARSED\', BLG_FINISHED = NOW() WHERE BLG_ID = :ID');
		return array('status' => 'reload', 'time' => time() - $startTime, 'files' => $this->getFilesLeft());
	}

	private function insertStatistic($date, $log, $domain, $directory, $crawler, $statistics)
	{
		static $binds = array(), $inserts = array(), $insertQuery = false;
		if (count($inserts) > 500 || $date === 0)
		{
			$this->db->bind($binds);
			if ($insertQuery === false)
			{
				$insertQuery = '
					INSERT INTO
						BOTSECTOR_STATISTICS
					(BST_DATE, BLG_ID, BDM_ID, BDR_ID, BCR_ID';
				foreach ($this->types as $type)
				{
					$insertQuery .= ', BST_'.$type[0].'_HITS, BST_'.$type[0].'_SIZE';
				}
				$insertQuery.=') VALUES ';
			}
			if (count($inserts) > 0)
			{
				$this->db->insert($insertQuery.implode(',', $inserts));
			}
			$binds = array();
			$inserts = array();
		}
		if ($date === 0)
		{
			return true;
		}
		$index = count($inserts);
		$binds['DATE'.$index] = $date;
		$binds['LOG'.$index] = $log;
		$binds['DOMAIN'.$index] = $domain;
		$binds['DIRECTORY'.$index] = $directory;
		$binds['CRAWLER'.$index] = $crawler;
		$sql = '(:DATE'.$index.', :LOG'.$index.', :DOMAIN'.$index.', :DIRECTORY'.$index.', :CRAWLER'.$index;
		foreach ($statistics as $type => $stat)
		{
			$binds[$type.'_HITS'.$index] = $stat[0];
			$binds[$type.'_SIZE'.$index] = $stat[1];
			$sql .= ', :'.$type.'_HITS'.$index.', :'.$type.'_SIZE'.$index;
		}
		$inserts[] = $sql.')';
	}

	private function getCrawler($userAgentString, $fullString = '')
	{
		static $ua = false, $userAgents = array(), $unknownBrowsers = array(), $unknownRegular = false, $patterns = false;
		/* Remove numbers (1234 => 0) and languages (fr-fr, en-US...) */
		$userAgent = $initialUserAgent = preg_replace('~(?:\d+)~', '0', preg_replace('~; [a-z]{2}\-[a-zA-Z]{2}(;|\))~', '\\1', $userAgentString));
		if (isset($userAgents[$userAgent]))
		{
			return $userAgents[$userAgent];
		}
		if ($ua === false)
		{
			$ua = array();
			$results = $this->db->select('SELECT BCR_ID, BCR_SIGNATURE FROM BOTSECTOR_CRAWLERS');
			foreach ($results as $r)
			{
				$r['BCR_ID'] = (int) $r['BCR_ID'];
				if ($r['BCR_ID'] > self::unknownBots)
				{
					foreach (explode("\n", trim($r['BCR_SIGNATURE'])) as $signature)
					{
						$signature = trim($signature);
						if ($signature !== '' && strpos($signature, '-- ') !== 0)
						{
							if (isset($ua[$signature]))
							{
								throw new Exception('Signature already set: '.$signature);
							}
							$ua[$signature] = $r['BCR_ID'];
						}
					}
				}
			}
		}
		/* Some signatures come with double spaces */
		if (!isset($ua[$userAgent]) && strpos($userAgent, '  ') !== false)
		{
			$userAgent = preg_replace('~\s+~', ' ', $userAgent);
		}
		if (isset($ua[$userAgent]))
		{
			/* Category 3 is real browsers with bot signature, such as the «MSIE Crawler» */
			if ($ua[$userAgent] === self::realBrowsers)
			{
				$userAgents[$initialUserAgent] = self::browsers;
				return self::browsers;
			}
			$userAgents[$initialUserAgent] = $ua[$userAgent];
			return $ua[$userAgent];
		}
		$isBot = preg_match('~(?:bot|spider|crawl|rss|feed|https?://|\.(?:com|ly|net|org|us)/)~i', $userAgent) === 1;
		if (Config::development === false && $isBot === true)
		{
			$userAgents[$initialUserAgent] = self::unknownBots;
			return self::unknownBots;
		}
		elseif (Config::development === true)
		{
			if (!isset($unknownBrowsers[$userAgent]))
			{
				$unknownBrowsers[$userAgent] = 0;
			}
			if ($isBot === true && $unknownBrowsers[$userAgent] > 5 && $unknownBrowsers[$userAgent] < 100)
			{
				$unknownBrowsers[$userAgent] = 100;
			}
			if ($unknownBrowsers[$userAgent] ++ === 100)
			{
				if ($unknownRegular === false)
				{
					$result = $this->db->select('SELECT BCR_SIGNATURE FROM BOTSECTOR_CRAWLERS WHERE BCR_ID = 1', true);
					$unknownRegular = array();
					$addedRegular = array();
					foreach (explode("\n", trim($result['BCR_SIGNATURE'])) as $signature)
					{
						$signature = trim($signature);
						if ($signature !== '' && strpos($signature, '-- ') !== 0)
						{
							if (isset($addedRegular[$signature]))
							{
								throw new Exception('Duplicate signature in Browsers: '.$signature);
							}
							$addedRegular[$signature] = true;
							$unknownRegular[] = $signature;
						}
					}
					unset($addedRegular);
					$unknownRegular = '~^(?:'.implode('|', $unknownRegular).')$~D';
				}
				/* Remove all majors/minors versions */
				$userAgent = preg_replace('~(0\.|0_|0,)*0~', '0', $userAgent);
				/* Remove useless characters */
				$userAgent = str_replace(array(',', '"', '+', '[', ']'), array(';', '', ' ', '(', ')'), $userAgent);
				/* Missing last parenthesis, remove the last word and add a new parenthesis */
				if (substr_count($userAgent, '(') !== substr_count($userAgent, ')'))
				{
					$userAgent = substr($userAgent, 0, strrpos($userAgent, ';'));
					$userAgent .= ')';
				}
				/* First, fix multiple parenthesis */
				while (preg_match('~\(([^)]*)\(([^)]+)\)~', $userAgent, $parenthesis) === 1)
				{
					$userAgent = str_replace($parenthesis[0], '('.trim($parenthesis[1]).'; '.$parenthesis[2], $userAgent);
				}
				$parameters = array();
				/* Then find out what are all info here */
				preg_match_all('~\([^)]+\)~', $userAgent, $params);
				foreach ($params[0] as $param)
				{
					$userAgent = str_replace($param, ' ', $userAgent);
					/* Error on missing semi-colon, such as «Mozilla/0.0 (LG-T0 AppleWebkit/0 Browser/Phantom/V0.0 Widget/LGMW/0.0 MMS/LG-MMS-V0.0/0.0 Java/ASVM/0.0 Profile/MIDP-0.0 Configuration/CLDC-0.0)» */
					if (strlen($param) > 40 && substr_count($param, ';') === 0)
					{
						$param = str_replace(' ', ';', $param);
					}
					$parameters = array_merge($parameters, explode(';', trim($param, '();')));
				}
				$userAgent = str_replace(';', ' ', $userAgent);
				/* Find out all useful data in rest of ua string, such as Xxxxxx/0 */
				$userAgent = ' '.$userAgent.' ';
				if ($patterns === false)
				{
					$patterns = array(
						'like Gecko',
						'like iPhone OS 0 Mac OS X',
						'Mobile',
						'Mobile/[0A-Za-z]+',
						'[A-Z]+ [A-Z]0/0',
						'[A-Z][A-Za-z]+ [A-Z]+ 0',
						'[A-Z]+0? 0',
						'[A-Za-z]+/[A-Z]+-0',
						'[A-Za-z]+0?/0b?',
						'[A-Za-z]+0?/0\.EU',
						'[A-Z]+0?',
						'[A-Za-z]+',
						'[a-z0]+',
						'[A-Z_]+',
						'[A-Z\-]+/0',
						'[A-Z\-]+',
						'[A-Za-z0]+/0',
						'[A-Za-z\-]+0?/[A-Za-z0]+',
						'[A-Za-z]+/0/[a-z]0',
						'[A-Z]+/[A-Z]+/[A-Z0]+',
						'[A-Za-z]+/0/[A-Za-z0]+/[A-Z0]+',
						'[A-Z]+/[A-Za-z\-0]+/0',
						'[A-Za-z\-=_0]+',
						'-'
					);
					$patterns = '~\s+(?:'.implode('|', $patterns).')\s+~';
				}
				while (preg_match($patterns, $userAgent, $match) === 1)
				{
					$userAgent = ' '.trim(str_replace($match[0], ' ', $userAgent)).' ';
					$parameters[] = $match[0];
				}

				if (trim($userAgent) !== '')
				{
					$parameters[] = 'Exploding User-Agent string didn\'t work for: '.$userAgent;
				}

				foreach ($parameters as $parameter)
				{
					$parameter = trim($parameter);
					if ($parameter !== '' && preg_match($unknownRegular, $parameter) !== 1)
					{
						echo '<fieldset>';
						echo 'Unknown UA: <strong>'.$userAgentString.'</strong><br/>';
						echo 'Signature: <strong>'.$initialUserAgent.'</strong><br/>';
						echo 'Parameter: <strong>'.$parameter.'</strong> (all parameters: <em>'.implode('</em>, <em>', $parameters).'</em>)<br/>';
						echo 'Reverse DNS: '.gethostbyaddr(substr($fullString, 0, strpos($fullString, ' '))).'<br/>';
						echo 'Request: '.$fullString.'<br/>';
						if ($isBot === true)
						{
							$nextKey = $this->db->select('SELECT MAX(BCR_ID) + 10 next FROM BOTSECTOR_CRAWLERS', true);
							preg_match('~https?://[^\)]+~i', $userAgentString, $url);
							echo 'Bot: INSERT INTO `BOTSECTOR`.`BOTSECTOR_CRAWLERS` (`BCR_ID`, `BCR_NAME`, `BCR_SIGNATURE`, `BCR_WEBSITE`) VALUES ('.$nextKey['next'].', \''.str_replace('\'', '\'\'', $userAgentString).'\', \''.str_replace('\'', '\'\'', $initialUserAgent).'\', \''.(isset($url[0]) ? $url[0] : '').'\');<br/>';
						}
						echo '</fieldset>';
						return false;
					}
				}
			}
		}
		else
		{
			$userAgents[$initialUserAgent] = self::browsers;
		}
		return self::browsers;
	}

	public function getExtensions()
	{
		static $extensions = false;
		if ($extensions === false)
		{
			$extensions = array();
			foreach ($this->types as $type)
			{
				foreach ($type[2] as $extension)
				{
					$extensions[$extension] = $type[0];
				}
			}
		}
		return $extensions;
	}

	/**
	 * Detect categories in path
	 *
	 * @param integer $domain
	 * @param string $path
	 */
	private function getCategoryAndType($domain, $path)
	{
		static $directories = array(), $subdirectories = array(), $unknownExtension = array(), $stopMerging = false, $extensions = false, $maxLevels = 0, $maxDirectories = 0;
		if ($maxLevels === 0)
		{
			$maxLevels = (int) max(1, Config::get('websites', 'max_levels') + 1);
			$maxDirectories = (int) max(10, Config::get('websites', 'max_directories'));
		}
		if (strpos($path, '/lang') !== false || strpos($path, '/page') !== false)
		{
			$found = true;
			while ($found === true)
			{
				$found = false;
				/* Reverse languages bad placements */
				if (preg_match('~/(?:lang|language)/([a-z]+)$~D', $path, $language) === 1)
				{
					$path = '/'.$language[1].str_replace($language[0], '', $path);
					$found = true;
				}
				/* Remove pages info */
				if (preg_match('~/(?:page|pages)(?:/|:)[0-9]+(/|$)~D', $path, $page) === 1)
				{
					$path = str_replace($page[0], $page[1], $path);
					$found = true;
				}
			}
		}
		$pathInfo = parse_url($path);
		if (empty($pathInfo['path']))
		{
			$pathInfo = parse_url(preg_replace('~/+~', '/', str_replace(':', '.', $path)));
			if (empty($pathInfo['path']))
			{
				$pathInfo = parse_url('/');
			}
		}
		$fileInfo = pathinfo($pathInfo['path']);
		/* No extension? It must be an HTML page */
		if (!isset($fileInfo['extension']))
		{
			$type = 'HTML';
		}
		else
		{
			$extension = $fileInfo['extension'];
			if ($extensions === false)
			{
				$extensions = $this->getExtensions();
			}
			if (isset($extensions[$extension]))
			{
				$type = $extensions[$extension];
			}
			elseif (($extension = strtolower($extension)) && isset($extensions[$extension]))
			{
				$type = $extensions[$extension];
			}
			elseif ($extension === 'txt')
			{
				if ($fileInfo['filename'] === 'robots')
				{
					$type = 'ROBOTS';
				}
				else
				{
					$type = 'RESOURCES';
				}
			}
			elseif (stripos($fileInfo['extension'], 'htm') === 0)
			{
				$type = 'HTML';
			}
			else
			{
				if (Config::development === true)
				{
					if (!isset($unknownExtension[$extension]))
					{
						$unknownExtension[$extension] = 0;
					}
					$unknownExtension[$extension] ++;
					if (count($unknownExtension[$extension]) > 3)
					{
						trigger_error('Unknown type: '.$extension.' '.json_encode($fileInfo));
					}
				}
				$type = 'UNKNOWN';
			}
		}

		/* Get all directories currently in the database */
		if (!isset($directories[$domain]))
		{
			$directories[$domain] = array();
			$this->db->bind('DOMAIN', $domain);
			foreach ($this->db->select('SELECT BDR_ID, BDR_DIRECTORY, BDR_SUBDIRECTORIES FROM BOTSECTOR_DIRECTORIES WHERE BDM_ID = :DOMAIN') as $d)
			{
				// format: array(directory's id, include subdirectories)
				$directories[$domain][$d['BDR_DIRECTORY']] = array((int) $d['BDR_ID'], (int) $d['BDR_SUBDIRECTORIES'] > 0);
			}
		}
		$directory = $fileInfo['dirname'];
		/* Most probably an error */
		if (strlen($directory) > 500)
		{
			$directory = substr($directory, 0, 500);
		}
		if (!isset($directories[$domain][$directory]))
		{
			if (!isset($subdirectories[$domain][$directory]))
			{
				/* Too many levels, truncate the path */
				if (substr_count($directory, '/') >= $maxLevels)
				{
					$exploded = explode('/', $directory);
					while (count($exploded) > $maxLevels)
					{
						array_pop($exploded);
					}
				}
				else
				{
					$exploded = explode('/', $directory);
				}
				/* Look for directories that include subdirectories */
				while (count($exploded) > 1) // don't test the root
				{
					$parentDirectory = implode('/', $exploded);
					if (isset($directories[$domain][$parentDirectory]) && $directories[$domain][$parentDirectory][1] === true)
					{
						$subdirectories[$domain][$directory] = $parentDirectory;
						break;
					}
					array_pop($exploded);
				}
			}
			if (isset($subdirectories[$domain][$directory]))
			{
				$directory = $subdirectories[$domain][$directory];
			}
		}
		if (!isset($directories[$domain][$directory]))
		{
			/** Too many directories ? Let's try to merge them */
			if ($path !== '/' && count($directories[$domain]) >= $maxDirectories)
			{
				if ($stopMerging === true || $this->mergeDirectories($domain) === false)
				{
					$stopMerging = true;
					/* If there's no point merging directories, just return the root path with the current file */
					if (($pos = strrpos($path, '/')) !== false)
					{
						$path = substr($path, $pos);
					}
				}
				/* Will create the list again */
				unset($directories[$domain]);
				unset($subdirectories[$domain]);
				return $this->getCategoryAndType($domain, $path);
			}
			$directories[$domain][$directory] = array($this->insertDirectory($domain, $directory), false);
		}
		return array('type' => $type, 'directory' => $directories[$domain][$directory][0]);
	}

	private function insertDirectory($domain, $directory, $subdirectories = false)
	{
		$this->db->bind('DOMAIN', $domain);
		$this->db->bind('DIRECTORY', $directory, false);
		$this->db->bind('SUBDIRECTORIES', $subdirectories ? 1 : 0);
		$this->db->bind('CHECKSUM', md5($directory).'-'.strlen($directory).'-'.crc32($directory));
		return $this->db->insert('INSERT INTO BOTSECTOR_DIRECTORIES (BDR_ID, BDM_ID, BDR_DIRECTORY, BDR_SUBDIRECTORIES, BDR_CHECKSUM) VALUES (NULL, :DOMAIN, :DIRECTORY, :SUBDIRECTORIES, :CHECKSUM)');
	}

	/**
	 * Handle new domains, or returns known domains from database
	 *
	 * @param string $domain
	 * @param boolean $insert Allow inserting the domain
	 */
	private function getDomain($domain, $insert)
	{
		static $domains = array();

		if (isset($domains[$domain]))
		{
			return $domains[$domain];
		}
		$loweredDomain = strtolower($domain);
		$this->db->bind('DOMAIN', $loweredDomain);
		$result = $this->db->select('SELECT BDM_ID FROM BOTSECTOR_DOMAINS WHERE BDM_DOMAIN = :DOMAIN LIMIT 1', true);
		if (isset($result['BDM_ID']))
		{
			$domains[$loweredDomain] = (int) $result['BDM_ID'];
		}
		elseif ($insert !== true)
		{
			return false;
		}
		else
		{
			$this->db->bind('DOMAIN', $loweredDomain);
			$domains[$loweredDomain] = $this->db->insert('INSERT INTO BOTSECTOR_DOMAINS (BDM_ID, BDM_DOMAIN) VALUES (NULL, :DOMAIN)');
		}
		if ($domain !== $loweredDomain)
		{
			$domains[$domain] = &$domains[$loweredDomain];
		}
		return $domains[$loweredDomain];
	}

	/**
	 * Merging tool so that the number of directories doesn't expand too much. Default is websites.max_directories = 1000
	 *
	 * @param integer $domain
	 */
	private function mergeDirectories($domain)
	{
		$directories = array();

		/* First, find the directory with the most subdirectories */
		$this->db->bind('DOMAIN', $domain);
		foreach ($this->db->select('SELECT BDR_DIRECTORY FROM BOTSECTOR_DIRECTORIES WHERE BDM_ID = :DOMAIN') as $d)
		{
			if ($d === '/')
			{
				continue;
			}
			$exploded = explode('/', $d['BDR_DIRECTORY']);
			array_pop($exploded);
			while (count($exploded) > 1) // ignore root
			{
				$directory = &$directories[implode('/', $exploded)];
				if (!isset($directory))
				{
					$directory = pow(2, count($exploded) - 1); // add a little effect on deep directory structure
				}
				$directory++;
				unset($directory);
				array_pop($exploded);
			}
		}
		arsort($directories);

		/* Let's merge according to the first result */
		list($directory, ) = each($directories);

		$this->db->bind('DOMAIN', $domain);
		$this->db->bind('DIRECTORY', $directory.'/%', false);
		$mergedDirectories = $this->db->select('SELECT BDR_ID FROM BOTSECTOR_DIRECTORIES WHERE BDM_ID = :DOMAIN AND BDR_DIRECTORY LIKE :DIRECTORY');
		if (count($mergedDirectories) === 0)
		{
			/* No sub directories, we're stuck... */
			return false;
		}

		$this->db->bind('DOMAIN', $domain);
		$this->db->bind('DIRECTORY', $directory, false);
		$result = $this->db->select('SELECT BDR_ID FROM BOTSECTOR_DIRECTORIES WHERE BDM_ID = :DOMAIN AND BDR_DIRECTORY = :DIRECTORY', true);
		if (isset($result['BDR_ID']))
		{
			$mainDirectory = (int) $result['BDR_ID'];
			$this->db->bind('DIRECTORY', $mainDirectory);
			/* Set the directory as inclusive */
			$this->db->update('UPDATE BOTSECTOR_DIRECTORIES SET BDR_SUBDIRECTORIES = 1 WHERE BDR_ID = :DIRECTORY');
		}
		else
		{
			$mainDirectory = $this->insertDirectory($domain, $directory, true);
		}

		$updateQuery = array();
		foreach ($this->types as $type)
		{
			$updateQuery[] = 'BST_'.$type[0].'_HITS = BST_'.$type[0].'_HITS + :'.$type[0].'HITS';
			$updateQuery[] = 'BST_'.$type[0].'_SIZE = BST_'.$type[0].'_SIZE + :'.$type[0].'SIZE';
		}
		$updateQuery = implode(', ', $updateQuery);
		foreach ($mergedDirectories as $d)
		{
			$dir = (int) $d['BDR_ID'];
			$this->removedDirectories[$dir] = $mainDirectory;

			/* Start a transaction (this should speed things up) */
			$this->db->begin();

			/* First, merge statistics */
			while (true)
			{
				$this->db->bind('DOMAIN', $domain);
				$this->db->bind('DIRECTORY', $dir);
				$statistics = $this->db->select('SELECT * FROM BOTSECTOR_STATISTICS WHERE BDM_ID = :DOMAIN AND BDR_ID = :DIRECTORY LIMIT 100');
				if (count($statistics) === 0)
				{
					break;
				}
				foreach ($statistics as $s)
				{
					$binds = array(
						'DATE' => $s['BST_DATE'],
						'LOG' => (int) $s['BLG_ID'],
						'DOMAIN' => $domain,
						'DIRECTORY' => $mainDirectory,
						'CRAWLER' => (int) $s['BCR_ID']
					);

					/* Check if line already exists */
					$this->db->bind($binds);
					$exists = $this->db->select('SELECT COUNT(*) c FROM BOTSECTOR_STATISTICS WHERE BST_DATE = :DATE AND BLG_ID = :LOG AND BDM_ID = :DOMAIN AND BDR_ID = :DIRECTORY AND BCR_ID = :CRAWLER', true);
					if ($exists['c'] == 0)
					{
						$this->db->bind($binds);
						$this->db->insert('INSERT INTO BOTSECTOR_STATISTICS (BST_DATE, BLG_ID, BDM_ID, BDR_ID, BCR_ID) VALUES (:DATE, :LOG, :DOMAIN, :DIRECTORY, :CRAWLER)');
					}
					/* Update statistics */
					foreach ($this->types as $type)
					{
						$this->db->bind($type[0].'HITS', (int) $s['BST_'.$type[0].'_HITS']);
						$this->db->bind($type[0].'SIZE', (int) $s['BST_'.$type[0].'_SIZE']);
					}
					$this->db->bind($binds);
					$this->db->update('UPDATE BOTSECTOR_STATISTICS SET '.$updateQuery.' WHERE BST_DATE = :DATE AND BLG_ID = :LOG AND BDM_ID = :DOMAIN AND BDR_ID = :DIRECTORY AND BCR_ID = :CRAWLER');

					/* Remove the current one */
					$binds['DIRECTORY'] = $dir;
					$this->db->bind($binds);
					$this->db->delete('DELETE FROM BOTSECTOR_STATISTICS WHERE BST_DATE = :DATE AND BLG_ID = :LOG AND BDM_ID = :DOMAIN AND BDR_ID = :DIRECTORY AND BCR_ID = :CRAWLER');
				}
			}
			/* Delete the directory */
			$this->db->bind('DIRECTORY', $dir);
			$this->db->delete('DELETE FROM BOTSECTOR_DIRECTORIES WHERE BDR_ID = :DIRECTORY');
			$this->db->commit();
		}

		return true;
	}

	private function getServer()
	{
		static $server = false;
		if ($server === false)
		{
			foreach (array('server', 'hostname', 'gethostname') as $source)
			{
				switch ($source)
				{
					case 'server':
						/* From $_SERVER */
						$server = $_SERVER['SERVER_ADDR'];
						break;

					case 'hostname':
						/* Let's try from UNIX hostname command */
						$server = trim(shell_exec('hostname -i'));
						break;

					case 'gethostname':
						/* From DNS resolution */
						$server = gethostbyname(gethostname());
						break;
				}
				if (preg_match('~^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$~D', $server) === 1 && strpos($server, '127.') !== 0 && strpos($server, '192.') !== 0)
				{
					break;
				}
				$server = '0.0.0.0';
			}
		}
		return $server;
	}

}

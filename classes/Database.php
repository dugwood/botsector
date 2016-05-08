<?php

/**
 * Database handling class
 */
class Database
{

	private static $_instance = false;
	private $connection = false;
	private $debugVars = array(true, '', true);
	private $binds = array();

	public static function getInstance()
	{
		if (self::$_instance === false)
		{
			self::$_instance = new Database();
		}
		return self::$_instance;
	}

	private function __construct()
	{

	}

	private function getConnection()
	{
		if ($this->connection !== false)
		{
			return $this->connection;
		}
		$this->connection = mysqli_init();
		$this->connection->real_connect(Config::get('database', 'host'), Config::get('database', 'user'), Config::get('database', 'password'), Config::get('database', 'database'));
		if (mysqli_connect_errno() !== 0)
		{
			if (Config::development === true)
			{
				throw new Exception('Can\'t connect to MySQL server, check your parameters: '.$this->connection->error);
			}
			else
			{
				throw new Exception('Can\'t connect to MySQL server, check your parameters');
			}
			$this->connection = false;
			return false;
		}
		return $this->connection;
	}

	/**
	 * Binding variables
	 *
	 * @param mixed		$binds
	 * @param mixed		$value
	 * @param boolean	$escapeHtml
	 * @return mixed
	 */
	public function bind($binds, $value = false, $escapeHtml = true)
	{
		if (!is_array($binds))
		{
			$binds = array($binds => $value);
			$value = false;
		}
		$val = true;
		if ($value !== false)
		{
			$inResult = '';
		}
		foreach ($binds as $name => $val)
		{
			if (!is_int($val) && !is_float($val) && $val !== 'NULL' && $val !== 'NOW()' && $val !== 'CURDATE()' && $val !== 'UNIX_TIMESTAMP()')
			{
				if ($escapeHtml === true)
				{
					$val = htmlspecialchars($val);
				}
				$this->binds[':'.$value.$name] = "'".$this->getConnection()->real_escape_string($val)."'";
			}
			else
			{
				$this->binds[':'.$value.$name] = $val;
			}
			if ($value !== false)
			{
				$inResult .= ':'.$value.$name.', ';
			}
		}
		if ($value === false)
		{
			return $val;
		}
		return substr($inResult, 0, -2);
	}

	/**
	 * Order bindings
	 *
	 * @param string $query
	 */
	function prepare(&$query)
	{
		/* Rerverse natural sort */
		krsort($this->binds);
		$query = strtr($query, $this->binds);
		if ($this->debugVars[0] === false)
		{
			if (isset($_SERVER['REQUEST_URI']))
			{
				$this->debugVars[1] .= '<pre style="font-size:8px">QUERY: '.htmlspecialchars($query)."</pre>\n";
			}
			else
			{
				$this->debugVars[1] .= 'QUERY: '.$query."\n";
			}
		}
	}

	/**
	 * Select query
	 *
	 * @param	string	$query
	 * @param	string	$columnIndex
	 * @return	array
	 */
	public function select($query, $columnIndex = false)
	{
		$connection = $this->getConnection();
		if ($connection === false)
		{
			return false;
		}

		$this->prepare($query);

		if (Config::development === true)
		{
			$time = -1 * microtime(true);
		}
		$resource = $connection->query($query);
		if (Config::development === true)
		{
			$time += microtime(true);
		}

		if ($resource === false)
		{
			if (Config::development === true)
			{
				$debug = debug_backtrace(false);
				trigger_error('SELECT query failed: '.$connection->error.', '.$query.', in '.$debug[0]['file'].'@'.$debug[0]['line']);
			}
			else
			{
				trigger_error('SELECT query failed: '.$connection->error.', '.$query, E_USER_WARNING);
				throw new Exception('SELECT query failed');
			}
			return false;
		}

		if ($columnIndex === false)
		{
			$results = array();
			while ($results[] = $resource->fetch_assoc())
			{

			}
			array_pop($results);
		}
		elseif ($columnIndex === true)
		{
			$results = $resource->fetch_assoc();
			if (!is_array($results))
			{
				$results = array();
			}
		}
		else
		{
			$results = array();
			while ($result = $resource->fetch_assoc())
			{
				$results[$result[$columnIndex]] = $result;
			}
		}
		$resource->close();

		if (Config::development === true)
		{
			$this->explain($query, $connection, $time);
		}

		$this->binds = array();

		return $results;
	}

	/**
	 * INSERT query
	 *
	 * @param	string	$query
	 * @return	integer
	 */
	public function insert($query)
	{
		$connection = $this->getConnection();
		if ($connection === false)
		{
			return false;
		}

		$this->prepare($query);

		$resource = $connection->query($query);
		if ($resource === false)
		{
			if (Config::development === true)
			{
				trigger_error('INSERT query failed: '.$connection->error.', '.$query);
			}
			else
			{
				trigger_error('INSERT query failed: '.$connection->error.', '.$query, E_USER_WARNING);
				throw new Exception('INSERT query failed');
			}
			return false;
		}
		$this->binds = array();

		$return = $connection->insert_id;
		if ($return === 0)
		{
			$return = $connection->affected_rows;
		}

		return $return;
	}

	/**
	 * UPDATE query
	 *
	 * @param	string	$query
	 * @return	integer
	 */
	public function update($query)
	{
		$connection = $this->getConnection();
		if ($connection === false)
		{
			return false;
		}

		$this->prepare($query);

		if (Config::development === true)
		{
			$time = -1 * microtime(true);
		}
		$resource = $connection->query($query);
		if (Config::development === true)
		{
			$time += microtime(true);
		}

		if ($resource === false)
		{
			if (Config::development === true)
			{
				trigger_error('UPDATE query failed: '.$connection->error.', query: '.$query);
			}
			else
			{
				trigger_error('UPDATE query failed: '.$connection->error.', '.$query, E_USER_WARNING);
				throw new Exception('UPDATE query failed');
			}
			return false;
		}

		$this->binds = array();
		$affected = $connection->affected_rows;

		if (Config::development === true)
		{
			$this->explain($query, $connection, $time);
		}

		return $affected;
	}

	/**
	 * DELETE query
	 *
	 * @param	string	$query
	 * @return	integer
	 */
	public function delete($query)
	{
		$connection = $this->getConnection();
		if ($connection === false)
		{
			return false;
		}

		$this->prepare($query);

		if (Config::development === true)
		{
			$time = -1 * microtime(true);
		}
		$resource = $connection->query($query);
		if (Config::development === true)
		{
			$time += microtime(true);
		}

		if ($resource === false)
		{
			if (Config::development === true)
			{
				trigger_error('DELETE query failed '.$connection->error.', query: '.$query);
			}
			else
			{
				trigger_error('DELETE query failed: '.$connection->error.', '.$query, E_USER_WARNING);
				throw new Exception('DELETE query failed');
			}
			return false;
		}

		$this->binds = array();
		$affected = $connection->affected_rows;

		if (Config::development === true)
		{
			$this->explain($query, $connection, $time);
		}

		return $affected;
	}

	/**
	 * Explain of queries
	 *
	 * @param string $query
	 * @param resource $connection
	 * @param float $time
	 */
	private function explain($query, $connection, $time)
	{
		static $triggered = array(false, false);
		if ($this->debugVars[2] === false || $connection === false || $this->queryToSelect($query) === false)
		{
			return false;
		}
		$time *= 1000;
		if ($this->debugVars[0] === false)
		{
			if (isset($_SERVER['REQUEST_URI']))
			{
				$this->debugVars[1] .= '<pre';
				if ($time > 3)
				{
					$this->debugVars[1] .= ' style="color:red;font-weight:bold"';
				}
				$this->debugVars[1] .= '>SPEED: '.number_format($time, 3).'ms</pre>';
			}
			else
			{
				$this->debugVars[1] .= "\033[1mSPEED:";
				if ($time > 3)
				{
					$this->debugVars[1] .= "\033[0;31m";
				}
				else
				{
					$this->debugVars[1] .= "\033[0m";
				}
				$this->debugVars[1] .= ' '.number_format($time, 3)."ms\033[0m\n";
			}
		}
		elseif ($triggered[0] === false)
		{
			if (($pos = strpos($query, 'max-query-time=')) === false)
			{
				$maxTime = 3;
			}
			else
			{
				$maxTime = (int) substr($query, $pos + 15, 5);
			}
			if ($time > $maxTime)
			{
				$min = 100000;
				for ($i = 0; $i < 2; $i++)
				{
					$time = -1 * microtime(true);
					$resource = $connection->query($query);
					$time += microtime(true);
					if (is_resource($resource))
					{
						$resource->close();
					}
					$time *= 1000;
					$min = min($min, $time);
					if ($time <= $maxTime)
					{
						break;
					}
				}
				if ($i === 3)
				{
					$triggered[0] = true;
					trigger_error('Query too slow ('.number_format($min, 1).' ms): '.$query);
				}
			}
		}

		$maxCost = 1500;
		if ($this->debugVars[0] === false)
		{
			$resource = $connection->query('EXPLAIN '.$query);
			if ($resource !== false)
			{
				$results = array();
				$cols = array('id' => 2, 'select_type' => 11, 'table' => 5, 'type' => 4, 'possible_keys' => 13, 'key' => 3, 'key_len' => 7, 'ref' => 3, 'rows' => 4, 'Extra' => 5);
				while ($result = $resource->fetch_assoc())
				{
					foreach ($cols as $id => $length)
					{
						$cols[$id] = max(strlen($result[$id]), $length);
					}
					$results[] = $result;
				}
				$sprintf = '| %- '.implode('s | %- ', $cols).'s |';
				$str = '- EXPLAIN '.str_repeat('-', array_sum($cols) + count($cols) * 3 - 9)."\n";
				$str .= call_user_func_array('sprintf', array_merge(array($sprintf), array_keys($cols)))."\n";
				foreach ($results as $r)
				{
					$str .= call_user_func_array('sprintf', array_merge(array($sprintf), $r))."\n";
				}
				$str .= str_repeat('-', array_sum($cols) + count($cols) * 3 + 1)."\n";
				if (isset($_SERVER['REQUEST_URI']))
				{
					$this->debugVars[1] .= "<pre style=\"font-size:8px\">".stripslashes(str_replace(array('Using temporary', 'Using filesort', 'ALL'), array('<strong style="color:#f60">Using temporary</strong>', '<strong style="color:#f00">Using filesort</strong>', '<strong style="color:#f00">ALL</strong>'), $str))."</pre>";
				}
				else
				{
					$this->debugVars[1] .= str_replace(array('Using temporary', 'Using filesort', 'ALL'), array("\033[0;33mUsing temporary\033[0m", "\033[0;31mUsing filesort\033[0m", "\033[0;31mALL\033[0m"), $str)."\n";
				}
				$resource->close();
			}
			$resource = $connection->query('SHOW STATUS LIKE \'last_query_cost\'');
			list(, $cost) = $resource->fetch_row();
			$resource->close();
			$cost = $cost == 0 ? 'inconnu' : (string) (int) $cost;
			if (isset($_SERVER['REQUEST_URI']))
			{
				$this->debugVars[1] .= "<pre style=\"font-size:8px;";
				if ($cost > $maxCost)
				{
					$this->debugVars[1] .= 'color:#f00;';
				}
				$this->debugVars[1] .= "\">QUERY COST: $cost\n\n\n</pre>";
			}
			else
			{
				$this->debugVars[1] .= "\033[1mQUERY COST: ";
				if ($cost > $maxCost)
				{
					$this->debugVars[1] .= "\033[0;31m";
				}
				else
				{
					$this->debugVars[1] .= "\033[0m";
				}
				$this->debugVars[1] .= "$cost\033[0m\n\n";
			}
		}
		elseif ($triggered[1] === false)
		{
			if (($pos = strpos($query, 'max-query-cost=')) !== false)
			{
				$maxCost = (int) substr($query, $pos + 15, 20);
			}
			$resource = $connection->query('SHOW STATUS LIKE \'last_query_cost\'');
			if (is_resource($resource))
			{
				list(, $cost) = $resource->fetch_row();
				$resource->close();
				if ($cost > $maxCost)
				{
					$triggered[1] = true;
					trigger_error('Query too costly ('.ceil($cost).'): '.$query);
				}
			}
		}
	}

	private function queryToSelect(&$query)
	{
		$oldquery = $query;
		if (strpos($query, 'DELETE') !== false)
		{
			$query = preg_replace('~DELETE\s+FROM~s', 'SELECT 1 FROM', $query);
		}
		elseif (strpos($query, 'UPDATE') !== false && strpos($query, 'FOR UPDATE') === false)
		{
			if (strpos($query, 'WHERE') === false)
			{
				$query .= '\n WHERE 1=1';
			}
			if (preg_match('~SET(.+)WHERE~s', $query, $match) !== 1 || preg_match_all('~[A-Z0-9_]+\s*=(.+?,)~is', $match[1].',', $matches) === 0)
			{
				return false;
			}
			$query = preg_replace('~^\s*UPDATE~s', 'SELECT '.trim(implode('', $matches[1]), ',').' FROM ', $query);
			$query = str_replace($match[0], ' WHERE', $query);
		}
		elseif (strpos($query, 'SELECT') === false)
		{
			return false;
		}
		if (empty($query))
		{
			return false;
		}
		return true;
	}

	public function begin()
	{
		$connection = $this->getConnection();
		if ($connection === false)
		{
			return false;
		}

		$resource = $connection->query('BEGIN');

		if ($resource === false)
		{
			throw new Exception('BEGIN query failed');
		}
		return true;
	}

	public function rollback()
	{
		$connection = $this->getConnection();
		if ($connection === false)
		{
			return false;
		}

		$resource = $connection->query('ROLLBACK');

		if ($resource === false)
		{
			throw new Exception('ROLLBACK query failed');
		}
		return true;
	}

	public function commit()
	{
		$connection = $this->getConnection();
		if ($connection === false)
		{
			return false;
		}

		$resource = $connection->query('COMMIT');

		if ($resource === false)
		{
			throw new Exception('COMMIT query failed');
		}
		return true;
	}

	public function debug($stop = false)
	{
		if ($stop === 'disabled')
		{
			$this->debugVars[2] = false;
			return;
		}
		if (Config::development === true)
		{
			if (isset($_SERVER['REQUEST_URI']))
			{
				echo '<div style="background-color:#fff;color:#000,border:1px solid #888;">'.$this->debugVars[1].'</div>';
			}
			else
			{
				echo $this->debugVars[1];
			}
		}
		else
		{
			trigger_error('Debug oublié : '.$this->debugVars[1], E_USER_WARNING);
		}
		$this->debugVars[0] = (bool) $stop;
		$this->debugVars[1] = '';
	}

	function __destruct()
	{
		if (is_resource($this->connection))
		{
			$this->connection->close();
		}
	}

}

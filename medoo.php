<?php
/*!
 * Medoo database framework
 * http://medoo.in
 * Version 0.9
 * 
 * Copyright 2013, Angel Lai
 * Released under the MIT license
 */
class medoo
{
	protected $database_type = 'mysql';

	// For MySQL, MSSQL, Sybase
	protected $server = 'localhost';
	
	protected $username = 'username';
	
	protected $password = 'password';

	// For SQLite
	protected $database_file = '';

	// Optional
	protected $port = 3306;

	protected $charset = 'utf8';

	protected $database_name = '';
	
	protected $option = array();
	
	public function __construct($options)
	{
		try {
			$type = strtolower($this->database_type);

			if (is_string($options))
			{
				if ($type == 'sqlite')
				{
					$this->database_file = $options;
				}
				else
				{
					$this->database_name = $options;
				}
			}
			else
			{
				foreach ($options as $option => $value)
				{
					$this->$option = $value;
				}
			}

			$type = strtolower($this->database_type);

			if (
				isset($this->port) &&
				is_int($this->port * 1)
			)
			{
				$port = 'port=' . $this->port . ';';
			}

			switch ($type)
			{
				case 'mysql':
				case 'pgsql':
					$this->pdo = new PDO(
						$type . ':host=' . $this->server . ';' . $port . 'dbname=' . $this->database_name, 
						$this->username,
						$this->password,
						$this->option
					);
					$this->pdo->exec('SET NAMES \'' . $this->charset . '\'');
					break;

				case 'mssql':
				case 'sybase':
					$this->pdo = new PDO(
						$type . ':host=' . $this->server . ';' . $port . 'dbname=' . $this->database_name . ',' .
						$this->username . ',' .
						$this->password,
						$this->option
					);
					$this->pdo->exec('SET NAMES \'' . $this->charset . '\'');
					break;

				case 'sqlite':
					$this->pdo = new PDO(
						$type . ':' . $this->database_file,
						null,
						null,
						$this->option
					);
					break;
			}
		}
		catch (PDOException $e) {
			throw new Exception($e->getMessage());
		}
	}
	
	public function query($query)
	{
		$this->queryString = $query;
		
		return $this->pdo->query($query);
	}

	public function exec($query)
	{
		$this->queryString = $query;

		return $this->pdo->exec($query);
	}

	public function quote($string)
	{
		return $this->pdo->quote($string);
	}

	protected function column_quote($string)
	{
		return '`' . str_replace(' AS ', '` AS `', str_replace('.', '`.`', $string)) . '`';
	}

	protected function array_quote($array)
	{
		$temp = array();

		foreach ($array as $value)
		{
			$temp[] = is_int($value) ? $value : $this->pdo->quote($value);
		}

		return implode($temp, ',');
	}
	
	protected function inner_conjunct($data, $conjunctor, $outer_conjunctor)
	{
		$haystack = array();

		foreach ($data as $value)
		{
			$haystack[] = '(' . $this->data_implode($value, $conjunctor) . ')';
		}

		return implode($outer_conjunctor . ' ', $haystack);
	}

	protected function data_implode($data, $conjunctor, $outer_conjunctor = null)
	{
		$wheres = array();

		foreach ($data as $key => $value)
		{
			if (
				($key == 'AND' || $key == 'OR') &&
				is_array($value)
			)
			{
				$wheres[] = 0 !== count(array_diff_key($value, array_keys(array_keys($value)))) ?
					'(' . $this->data_implode($value, ' ' . $key) . ')' :
					'(' . $this->inner_conjunct($value, ' ' . $key, $conjunctor) . ')';
			}
			else
			{
				preg_match('/([\w\.]+)(\[(\>|\>\=|\<|\<\=|\!|\<\>)\])?/i', $key, $match);
				if (isset($match[3]))
				{
					if ($match[3] == '')
					{
						$wheres[] = $this->column_quote($match[1]) . ' ' . $match[3] . '= ' . $this->quote($value);
					}
					elseif ($match[3] == '!')
					{
						$column = $this->column_quote($match[1]);
						
						switch (gettype($value))
						{
							case 'NULL':
								$wheres[] = $column . ' IS NOT NULL';
								break;

							case 'array':
								$wheres[] = $column . ' NOT IN (' . $this->array_quote($value) . ')';
								break;

							case 'integer':
								$wheres[] = $column . ' != ' . $value;
								break;

							case 'string':
								$wheres[] = $column . ' != ' . $this->quote($value);
								break;
						}
					}
					else
					{
						if ($match[3] == '<>')
						{
							if (is_array($value))
							{
								if (is_numeric($value[0]) && is_numeric($value[1]))
								{
									$wheres[] = $this->column_quote($match[1]) . ' BETWEEN ' . $value[0] . ' AND ' . $value[1];
								}
								else
								{
									$wheres[] = $this->column_quote($match[1]) . ' BETWEEN ' . $this->quote($value[0]) . ' AND ' . $this->quote($value[1]);
								}
							}
						}
						else
						{
							if (is_numeric($value))
							{
								$wheres[] = $this->column_quote($match[1]) . ' ' . $match[3] . ' ' . $value;
							}
							else
							{
								$datetime = strtotime($value);

								if ($datetime)
								{
									$wheres[] = $this->column_quote($match[1]) . ' ' . $match[3] . ' ' . $this->quote(date('Y-m-d H:i:s', $datetime));
								}
							}
						}
					}
				}
				else
				{
					if (is_int($key))
					{
						$wheres[] = $this->quote($value);
					}
					else
					{
						$column = $this->column_quote($match[1]);
						switch (gettype($value))
						{
							case 'NULL':
								$wheres[] = $column . ' IS NULL';
								break;

							case 'array':
								$wheres[] = $column . ' IN (' . $this->array_quote($value) . ')';
								break;

							case 'integer':
								$wheres[] = $column . ' = ' . $value;
								break;

							case 'string':
								$wheres[] = $column . ' = ' . $this->quote($value);
								break;
						}
					}
				}
			}
		}

		return implode($conjunctor . ' ', $wheres);
	}

	public function where_clause($where)
	{
		$where_clause = '';

		if (is_array($where))
		{
			$single_condition = array_diff_key($where, array_flip(
				explode(' ', 'AND OR GROUP ORDER HAVING LIMIT LIKE MATCH')
			));

			if ($single_condition != array())
			{
				$where_clause = ' WHERE ' . $this->data_implode($single_condition, '');
			}
			if (isset($where['AND']))
			{
				$where_clause = ' WHERE ' . $this->data_implode($where['AND'], ' AND');
			}
			if (isset($where['OR']))
			{
				$where_clause = ' WHERE ' . $this->data_implode($where['OR'], ' OR');
			}
			if (isset($where['LIKE']))
			{
				$like_query = $where['LIKE'];
				if (is_array($like_query))
				{
					$is_OR = isset($like_query['OR']);

					if ($is_OR || isset($like_query['AND']))
					{
						$connector = $is_OR ? 'OR' : 'AND';
						$like_query = $is_OR ? $like_query['OR'] : $like_query['AND'];
					}
					else
					{
						$connector = 'AND';
					}

					$clause_wrap = array();
					foreach ($like_query as $column => $keyword)
					{
						if (is_array($keyword))
						{
							foreach ($keyword as $key)
							{
								$clause_wrap[] = $this->column_quote($column) . ' LIKE ' . $this->quote('%' . $key . '%');
							}
						}
						else
						{
							$clause_wrap[] = $this->column_quote($column) . ' LIKE ' . $this->quote('%' . $keyword . '%');
						}
					}
					$where_clause .= ($where_clause != '' ? ' AND ' : ' WHERE ') . '(' . implode($clause_wrap, ' ' . $connector . ' ') . ')';
				}
			}
			if (isset($where['MATCH']))
			{
				$match_query = $where['MATCH'];
				if (is_array($match_query) && isset($match_query['columns']) && isset($match_query['keyword']))
				{
					$where_clause .= ($where_clause != '' ? ' AND ' : ' WHERE ') . ' MATCH (`' . str_replace('.', '`.`', implode($match_query['columns'], '`, `')) . '`) AGAINST (' . $this->quote($match_query['keyword']) . ')';
				}
			}
			if (isset($where['GROUP']))
			{
				$where_clause .= ' GROUP BY ' . $this->column_quote($where['GROUP']);
			}
			if (isset($where['ORDER']))
			{
				preg_match('/(^[a-zA-Z0-9_\-\.]*)(\s*(DESC|ASC))?/', $where['ORDER'], $order_match);

				$where_clause .= ' ORDER BY `' . str_replace('.', '`.`', $order_match[1]) . '` ' . (isset($order_match[3]) ? $order_match[3] : '');

				if (isset($where['HAVING']))
				{
					$where_clause .= ' HAVING ' . $this->data_implode($where['HAVING'], '');
				}
			}
			if (isset($where['LIMIT']))
			{
				if (is_numeric($where['LIMIT']))
				{
					$where_clause .= ' LIMIT ' . $where['LIMIT'];
				}
				if (
					is_array($where['LIMIT']) &&
					is_numeric($where['LIMIT'][0]) &&
					is_numeric($where['LIMIT'][1])
				)
				{
					$where_clause .= ' LIMIT ' . $where['LIMIT'][0] . ',' . $where['LIMIT'][1];
				}
			}
		}
		else
		{
			if ($where != null)
			{
				$where_clause .= ' ' . $where;
			}
		}

		return $where_clause;
	}
		
	public function select($table, $join, $columns = null, $where = null)
	{
		$table = '`' . $table . '`';

		if ($where !== null)
		{
			$table_join = array();

			$join_array = array(
				'>' => 'LEFT',
				'<' => 'RIGHT',
				'<>' => 'FULL',
				'><' => 'INNER'
			);

			foreach($join as $sub_table => $relation)
			{
				preg_match('/(\[(\<|\>|\>\<|\<\>)\])?([a-zA-Z0-9_\-]*)/', $sub_table, $match);

				if ($match[2] != '' && $match[3] != '')
				{
					if (is_string($relation))
					{
						$relation = 'USING (`' . $relation . '`)';
					}

					if (is_array($relation))
					{
						// For ['column1', 'column2']
						if (isset($relation[0]))
						{
							$relation = 'USING (`' . implode($relation, '`, `') . '`)';
						}
						// For ['column1' => 'column2']
						else
						{
							$relation = 'ON ' . $table . '.`' . key($relation) . '` = `' . $match[3] . '`.`' . current($relation) . '`';
						}
					}

					$table_join[] = $join_array[ $match[2] ] . ' JOIN `' . $match[3] . '` ' . $relation;
				}
			}

			$table .= ' ' . implode($table_join, ' ');
		}
		else
		{
			$where = $columns;
			$columns = $join;
		}

		$where_clause = $this->where_clause($where);

		if(is_array($columns)){
			$newColumns = array();
			foreach($columns as $column){
				$tmp = explode('[AS]', $column);
				$newColumn = (count($tmp) == 2 && $tmp[1] !== '') ? $tmp[0].' AS '.$tmp[1] : $tmp[0];
				$newColumns[] = $newColumn;
			}
			$columns = $newColumns;
		}

		$query =
			$this->query('SELECT ' .
				(
					is_array($columns) ? $this->column_quote( implode('`, `', $columns) ) :
					($columns == '*' ? '*' : '`' . $columns . '`')
				) .
				' FROM ' . $table . $where_clause
			);

		return $query ? $query->fetchAll(
			(is_string($columns) && $columns != '*') ? PDO::FETCH_COLUMN : PDO::FETCH_ASSOC
		) : false;
	}
		
	public function insert($table, $datas)
	{
		$lastId = array();

		// Check indexed or associative array
		if (!isset($datas[0]))
		{
			$datas = array($datas);
		}

		foreach ($datas as $data)
		{
			$keys = implode("`, `", array_keys($data));
			$values = array();

			foreach ($data as $key => $value)
			{
				switch (gettype($value))
				{
					case 'NULL':
						$values[] = 'NULL';
						break;

					case 'array':
						$values[] = $this->quote(serialize($value));
						break;

					case 'integer':
					case 'string':
						$values[] = $this->quote($value);
						break;
				}
			}

			$this->exec('INSERT INTO `' . $table . '` (`' . $keys . '`) VALUES (' . implode($values, ', ') . ')');

			$lastId[] = $this->pdo->lastInsertId();
		}
		
		return count($lastId)  > 1 ? $lastId : $lastId[ 0 ];
	}
	
	public function update($table, $data, $where = null)
	{
		$fields = array();

		foreach ($data as $key => $value)
		{
			$key = '`' . $key . '`';

			if (is_array($value))
			{
				$fields[] = $key . '=' . $this->quote(serialize($value));
			}
			else
			{
				preg_match('/([\w]+)(\[(\+|\-)\])?/i', $key, $match);
				if (isset($match[3]))
				{
					if (is_numeric($value))
					{
						$fields[] = $match[1] . ' = ' . $match[1] . ' ' . $match[3] . ' ' . $value;
					}
				}
				else
				{
					switch (gettype($value))
					{
						case 'NULL':
							$fields[] = $key . ' = NULL';
							break;

						case 'array':
							$fields[] = $key . ' = ' . $this->quote(serialize($value));
							break;

						case 'integer':
						case 'string':
							$fields[] = $key . ' = ' . $this->quote($value);
							break;
					}
				}
			}
		}
		
		return $this->exec('UPDATE `' . $table . '` SET ' . implode(', ', $fields) . $this->where_clause($where));
	}
	
	public function delete($table, $where)
	{
		return $this->exec('DELETE FROM `' . $table . '`' . $this->where_clause($where));
	}
	
	public function replace($table, $columns, $search = null, $replace = null, $where = null)
	{
		if (is_array($columns))
		{
			$replace_query = array();

			foreach ($columns as $column => $replacements)
			{
				foreach ($replacements as $replace_search => $replace_replacement)
				{
					$replace_query[] = $column . ' = REPLACE(`' . $column . '`, ' . $this->quote($replace_search) . ', ' . $this->quote($replace_replacement) . ')';
				}
			}
			$replace_query = implode(', ', $replace_query);
			$where = $search;
		}
		else
		{
			if (is_array($search))
			{
				$replace_query = array();

				foreach ($search as $replace_search => $replace_replacement)
				{
					$replace_query[] = $columns . ' = REPLACE(`' . $columns . '`, ' . $this->quote($replace_search) . ', ' . $this->quote($replace_replacement) . ')';
				}
				$replace_query = implode(', ', $replace_query);
				$where = $replace;
			}
			else
			{
				$replace_query = $columns . ' = REPLACE(`' . $columns . '`, ' . $this->quote($search) . ', ' . $this->quote($replace) . ')';
			}
		}

		return $this->exec('UPDATE `' . $table . '` SET ' . $replace_query . $this->where_clause($where));
	}

	public function get($table, $columns, $where = null)
	{
		if (!isset($where))
		{
			$where = array();
		}
		$where['LIMIT'] = 1;

		$data = $this->select($table, $columns, $where);

		return isset($data[0]) ? $data[0] : false;
	}

	public function has($table, $where)
	{
		return $this->query('SELECT EXISTS(SELECT 1 FROM `' . $table . '`' . $this->where_clause($where) . ')')->fetchColumn() === '1';
	}

	public function count($table, $where = null)
	{
		return 0 + ($this->query('SELECT COUNT(*) FROM `' . $table . '`' . $this->where_clause($where))->fetchColumn());
	}

	public function max($table, $column, $where = null)
	{
		return 0 + ($this->query('SELECT MAX(`' . $column . '`) FROM `' . $table . '`' . $this->where_clause($where))->fetchColumn());
	}

	public function min($table, $column, $where = null)
	{
		return 0 + ($this->query('SELECT MIN(`' . $column . '`) FROM `' . $table . '`' . $this->where_clause($where))->fetchColumn());
	}

	public function avg($table, $column, $where = null)
	{
		return 0 + ($this->query('SELECT AVG(`' . $column . '`) FROM `' . $table . '`' . $this->where_clause($where))->fetchColumn());
	}

	public function sum($table, $column, $where = null)
	{
		return 0 + ($this->query('SELECT SUM(`' . $column . '`) FROM `' . $table . '`' . $this->where_clause($where))->fetchColumn());
	}

	public function error()
	{
		return $this->pdo->errorInfo();
	}

	public function last_query()
	{
		return $this->queryString;
	}

	public function info()
	{
		return array(
			'server' => $this->pdo->getAttribute(PDO::ATTR_SERVER_INFO),
			'client' => $this->pdo->getAttribute(PDO::ATTR_CLIENT_VERSION),
			'driver' => $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
			'version' => $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
			'connection' => $this->pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS)
		);
	}
}

/**
 * @Baseclass of all QueryObjects
 * @author: seijikun
 */
abstract class Query {
	
	/** private instance of medoo for escaping / quoting */
	static $_medoo;	

	//statics
	protected static $_jointype = ['>' => 'LEFT', '<' => 'RIGHT', '<>' => 'FULL', '><' => 'INNER'];
	protected static $_conditionlinks = ['AND', 'OR'];
	protected static $_comparisionencoder = [	'default' => 'self::_encoder_default',
												'<>' => 'self::_encoder_between',
												'>' => 'self::_encoder_numeric',
												'<' => 'self::_encoder_numeric',
												'>=' => 'self::_encoder_numeric',
												'<=' => 'self::_encoder_numeric',
												'%' => 'self::_encoder_like'
											];
	
	/** produce query */
	abstract public function toString();
	
	
	// ESCAPER / QUOTER \\
	
	/** simple escaping */
	protected static function _escape($string){
		return self::$_medoo->quote($string);
	}
	/** column quoter */
	//supports ALIAS
	protected static function _columnescape($column){
		$tmp = explode('[AS]', $column);
		return str_replace('.', '`.`', (count($tmp) == 2 && $tmp[1] !== '') ? '`'.$tmp[0].'` AS `'.$tmp[1].'`' : '`'.$tmp[0].'`');
	}
	
	//sql assemblers\\
	
	/** assemble columns */
	protected static function _columns($columns){
		if(is_string($columns)){//all columns (*) or a single one
			return ($columns == '*') ? '*' : self::_columnescape($columns);
		}
		$_columns = [];
		foreach($columns as $table => $column){
			if(is_string($column)){
				$_columns[] = self::_columnescape($column);
			}elseif(is_array($column)){
				foreach($column as $sub_column){
					$_columns[] = self::_columnescape($table . '.' . $sub_column);
				}
			}
		}
		return implode(', ', $_columns);
	}
	
	/** assemble tablename */
	protected static function _table($table){
		return '`'.$table.'`';
	}
	
	protected static function _join($joins){
		$join_segment = "";
		foreach($joins as $jointable => $params){
			$_join = array(); $tmp_segment = "";
			preg_match('/^\[(>|<|<>|><){1}\]([a-zA-Z0-9_\-]+)$/', $jointable, $_join);
			if(count($_join) != 3) continue;
			
			$tmp_segment .= self::$_jointype[$_join[1]] . ' JOIN ' . self::_table($_join[2]);
			if(is_string($params)){//single USING column
				$tmp_segment .= ' USING(' . self::_columns($params) . ')';
			}elseif(is_array($params)){
				if(self::isAssoc($params)){//assoc array - ON CONDITION
					$_cond1 = array_keys($params)[0];
					$_cond2 = $params[$_cond1];
					$tmp_segment .= ' ON ' . self::_columns($_cond1) . ' = ' . self::_columns($_cond2);
				}else{//numeric array - USING
					$tmp_segment .= ' USING(' . self::_columns($params) . ')';
				}
			} else{continue;}
			$join_segment .= ' ' . $tmp_segment;
		}
		return $join_segment;
	}
	
	protected static function _group($group){
		return ' GROUP BY ' . self::_columns($group);
	}
	
	protected static function _order($orders){
		if(is_string($orders)){//all columns (*) or a single one
			if(preg_match('/^([a-zA-Z0-9_\-]+)(\.?[a-zA-Z0-9_\-]+) (ASC|DESC){1}$/', $orders) > 0){
				$tmp = explode(' ', $orders, 2);
				return ' ORDER BY ' . self::_columnescape($tmp[0]) . ' ' . $tmp[1];
			}
		}
		$order_statement = ' ORDER BY';
		$cnt = 0;
		foreach($orders as $order => $values){
			if(is_array($values) && count($values) > 1){//FIELD ORDER
				$tmp_statement = ($cnt === 0) ? ' FIELD(' : ', FIELD(';
				$tmp_statement .= self::_columnescape($order).',';
				for($i = 0; $i < count($values); $i++){
					$tmp_statement .= self::_escape($values[$i]).',';
				}
				$tmp_statement = rtrim($tmp_statement, ",");
				$tmp_statement .= ')';
				$order_statement .= $tmp_statement;
				$cnt++;
			}elseif(is_string($values)){//DEFAULT ORDER
				if(preg_match('/^([a-zA-Z0-9_\-]+)(\.?[a-zA-Z0-9_\-]+) (ASC|DESC){1}$/', $values) > 0){
					$tmp = explode(' ', $values, 2);
					$order_statement .= ($cnt === 0) ? ' ' : ', ';
					$order_statement .= self::_columnescape($tmp[0]) . ' ' . $tmp[1];
					$cnt++;
				}
			}
		}
		return $order_statement;
	}
	
	protected static function _conditional($conditions, $condlink = 'AND'){
		$statement_segments = array();
		foreach($conditions as $field => $value){
			if(is_array($value)){
				if(is_numeric($field) && count($value) == 1){//array with linking-condition in it ['OR' => [...]]
					$sublink = array_keys($value)[0];
					$statement_segments[] = self::_conditional($value[$sublink], $sublink);
				}
				elseif(in_array($field, self::$_conditionlinks)){//conditions of a linking-container [... , 'OR' => [...] , ...]
					$statement_segments[] = self::_conditional($value, $field);
				}else{//conditionparameter
					$statement_segments[] = self::_condition($field, $value);
				}
			}else{
				$statement_segments[] = self::_condition($field, $value);
			}
		}
		return '('.implode(' '.$condlink.' ', $statement_segments).')';
	}
	
	protected static function _condition($field, $value){
		$matches = array();
		if(preg_match('/^([a-zA-Z0-9_\-]+(?:\.[a-zA-Z0-9_\-]+)?)(?:\[(=|!=|%|>|<|<>|>=|<=)\])?$/', $field, $matches) > 0){
			if(count($matches) == 2){//default equals expression
				$matches[2] = '=';
			}
			if(count($matches) == 3){
				//assembly
				if(array_key_exists($matches[2], self::$_comparisionencoder)){//use special encoder
					return call_user_func(self::$_comparisionencoder[$matches[2]], $matches, $value);
				}else{//use default encoder
					return call_user_func(self::$_comparisionencoder['default'], $matches, $value);
				}
			}
		}
	}
	
	protected static function _limit($data){
		if(is_numeric($data) || is_string($data)){
			return ' LIMIT ' . floatval($data);
		}
		if(is_array($data) && count($data) == 2){
			return ' LIMIT ' . floatval($data[0]) . ', ' . floatval($data[1]);
		}
	}
	
	// COMPARISION ENCODERS \\
	
	protected static function _encoder_default($matches, $value){
		if($matches[2] == '') $matches[2] = '=';
		if(is_array($value)){//use IN expression
			$tmp_condition = self::_columnescape($matches[1]) . (($matches[2] == '!') ? 'NOT' : '') . ' IN (';
			$invals = [];
			foreach($value as $inval) {$invals[] = self::_escape($inval);}
			return $tmp_condition . implode(',', $invals) . ')';
		}else{
			return self::_columnescape($matches[1]) . $matches[2] . self::_escape($value);
		}
	}
	
	protected static function _encoder_numeric($matches, $value){
		if(is_array($value)){//use IN expression
			$tmp_condition = self::_columnescape($matches[1]) . (($matches[2] == '!') ? 'NOT' : '') . ' IN (';
			$invals = [];
			foreach($value as $inval) {$invals[] = floatval($inval);}
			return $tmp_condition . implode(',', $invals) . ')';
		}else{
			return self::_columnescape($matches[1]) . $matches[2] . floatval($value);
		}
	}
	
	protected static function _encoder_between($matches, $values){
		if(is_array($values) && count($values) == 2){
			$between_statement = self::_columnescape($matches[1]). ' BETWEEN ';
			if(is_numeric($values[0]) && is_numeric($values[1])){
				return $between_statement . floatval($values[0]) . ' AND ' . floatval($values[1]);
			}else{
				return $between_statement . self::_columnescape($values[0]) . ' AND ' . self::_columnescape($values[1]);
			}
		}
	}
	
	protected static function _encoder_like($matches, $value){
		return self::_columnescape($matches[1]) . ' LIKE ' . self::_escape($value);
	}
	
	// HELPER FUNCTIONS \\
	
	protected static function isAssoc($arr){
		return array_keys($arr) !== range(0, count($arr) - 1);
	}
	
}

/**
 * @SelectQuery object
 * @author: seijikun
 */
class SelectQuery extends Query{
	
	/** string / array / associative array
	 * @example[0]: '*'
	 * @result[0]: SELECT *
	 * 
	 * @example[1]: ["TABLE1.COLUMN1", "COLUMN2", "COLUMN3[AS]COLUMN1337"]
	 * @result[1]: SELECT `TABLE1.`,`COLUMN1`, `COLUMN2`, `COLUMN3` AS `COLUMN1337`
	 * 
	 * @example[2]: ["TABLE1" => ["COLUMN2", "COLUMN4"], "TABLE2" => ["COLUMN1", "COLUMN3[AS]COLUMN1337"]]
	 * @result[2]: SELECT `TABLE1`.`COLUMN2`, `TABLE1`.`COLUMN4`, `TABLE2`.`COLUMN1`, `TABLE2`.`COLUMN3` AS `COLUMN1337`
	 * */
	private $columns;
	/** string 
	 * */
	private $table;
	/** associative array
	 * @syntax: ["[JOINTYPE]JOINTABLE" => ["ON_TABLE1.ON_COLUMN1" => "ON_TABLE2.ON_COLUMN2"]]; ["[JOINTYPE]JOINTABLE" => "ON_TABLE1.ON_COLUMN1"]
	 * 
	 * @example[0]: ["[>]account" => ["author_id" => "user_id"], "[>]album" => "user_id", "[>]photo" => ["user_id", "avatar_id"]]
	 * @result[0]: LEFT JOIN `account` ON `post`.`author_id` = `account`.`user_id` LEFT JOIN `album` USING (`user_id`) LEFT JOIN `photo` USING (`user_id`, `avatar_id`)
	 * */
	private $join;
	/** array / associative array
	 * 
	 * @comment[0]: possible operators: ['>', '<', '!=', '<=', '>=', '%' => 'LIKE', '<>' => 'BETWEEN']
	 * @example[0]: ["AND" => ["user_age[>=]" => 18, "user_email[=]" => 'foo@bar.com']]
	 * @result[0]: WHERE (`user_age` >= '18' AND `email` = 'foo@bar.com'
	 * 
	 * @example[1]: ["OR" => ["email" => ["foo@bar.com","bar@foo.com"]]
	 * @result[1]: `email` IN('foo@bar.com','bar@foo.com')
	 * 
	 * @comment[2]: BETWEEN values have to be numeric, else they will be seen as other columns. (=> column1 BETWEEN column2 AND column3)
	 * @example[2]: [['OR' => ["user_age[>=]" => 18, "user_email[=]" => 'foo@bar.com']],['OR' => ["suspendet[!=]" => 1, "entry_age[<>]" => [200,500]]]]
	 * @result[2]: (`user_age`>=18 OR `user_email`='foo@bar.com') AND (`suspendet`!='1' OR `entry_age` BETWEEN 200 AND 500)
	 * 
	 * @note: if this is a normal array, all containing conditions will get linked by "AND"
	 * */
	private $where;
	/** string
	 * @example[0]: 'COLUMN1'; 'TABLE1.COLUMN2'
	 * @result[0]: GROUP BY `COLUMN1` ; GROUP BY `TABLE1`.`COLUMN2`
	 * */
	private $group;
	/** !SEE WHERE!
	 * */
	private $having;
	/** string / array
	 * 
	 * @example[0]: 'COLUMN1 DESC'
	 * @result[0]: ORDER BY `COLUMN1` DESC
	 * 
	 * @example[1]: ['TABLE1.COLUMN1 ASC', 'COLUMN2 DESC']
	 * @result[1]: ORDER BY `TABLE1`.`COLUMN1` ASC, `COLUMN2` DESC
	 * 
	 * @example[2]: ['COLUMN1' => [2, '3', 1], 'COLUMN2 ASC']
	 * @result[2]: ORDER BY FIELD(`COLUMN1`, '2','3','1'), `COLUMN2` ASC
	 * */
	private $order;
	/** numeric / array
	 * @example[0]: 20
	 * @result[0]: LIMIT 20
	 * 
	 * @example[1]: [20, 30]
	 * @result[1]: LIMIT 20,30
	 * */
	private $limit;
	
	/**
	 * @note: Every parameter that isn't needed should be set as null.
	 * */
	public function __construct(medoo &$medoo, $table = null, $columns = null, $join = null, $where = null, $group = null, $having = null, $order = null, $limit = null){
		self::$_medoo = $medoo;
		$this->table = $table;
		$this->columns = $columns;
		$this->join = $join;
		$this->where = $where;
		$this->group = $group;
		$this->having = $having;
		$this->order = $order;
		$this->limit = $limit;
	}
	
	public function toString(){
		if($this->columns === null || $this->table === null){
			throw new InvalidArgumentException('Table or Columns to query from missing!');
		}
		//SELECT - FROM - ...
		$output = 'SELECT ' . self::_columns($this->columns) . ' FROM ' . self::_table($this->table);
		if($this->join !== null){
			if(!is_array($this->join)) throw new InvalidArgumentException('Join paramter invalid!');
			$output .= self::_join($this->join);
		}
		if($this->where !== null){
			if(!is_array($this->where)) throw new InvalidArgumentException('Where paramter invalid!');
			$output .= ' WHERE ' . self::_conditional($this->where);
		}
		if($this->group !== null){
			if(!is_string($this->group) || $this->group == '*') throw new InvalidArgumentException('Grouping paramter invalid!');
			$output .= self::_group($this->group);
		}
		if($this->having !== null){
			if(!is_array($this->having)) throw new InvalidArgumentException('Having paramter invalid!');
			$output .= ' HAVING ' . self::_conditional($this->having);
		}
		if($this->order !== null){
			if(!is_string($this->order) && !is_array($this->order)) throw new InvalidArgumentException('Ordering paramter invalid!');
			$output .= self::_order($this->order);
		}
		if($this->limit !== null){
			if(!is_numeric($this->limit) && !is_string($this->limit) && !is_array($this->limit)) throw new InvalidArgumentException('Limit paramter invalid!');
			$output .= self::_limit($this->limit);
		}
		return $output;
	}
	
}

?>

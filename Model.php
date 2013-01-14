<?php
/**
 * Base Model.
 * Base model to use in models, since some common functionality will be in place.
 * - Basic CRUD
 * - Basic getters
 * - Autogeneration of table name
 * - Autocache saving when possible
 * - Autovalidation in model before saving
 * - Possibility to export validators to plug them in Zend_Form
 *
 * @author Francisco Garcia <fjgarlin@gmail.com>
 * @package Base Package
 */
abstract class Base_Model extends Zend_Db_Table_Abstract
{
	const DEFAULT_LIMIT = 15;
	const DEFAULT_OFFSET = 0;
	
	/**
	 * Validation rules to be applied before saving or updating
	 *
	 * Full example of a definition of validation array (not real, just possibities):
	 *  $_validationRules = array(
	 *		'id' => array('Zend_Validate_Int'),
	 *		'id' => 'Zend_Validate_Int',
	 *		'id' => array('Int'),
	 *		'id' => 'Int',
	 *		'id' => array('StringLength', false, array(3,10)),	//NOT POSSIBLE!
	 *		'id' => array(array('StringLength', false, array('min' => 3, 'max' => 10))),	//*POSSIBLE, though others preferred!
	 *		'name' => array(
	 *			'Zend_Validate_NotEmpty',
	 *			'Zend_Validate_Db_NoRecordExists',
	 *			'Alnum',
	 *			array('StringLength', false, array('min' => 3, 'max' => 10))	//*
	 *		),
	 *		...
	 *	);
	 *	
	 * @var array $_validationRules rules to apply to the model before saving (add or edit) any data.
	 */	 
	protected $_validationRules;
	
	/**
	 * Errors when validating an instance of the class
	 *
	 * @var array $_errors array of errors returned by the model when trying to save data.
	 */
	protected $_errors;
	
	/**
	 * Errors when fetching data (mostly datatables related)
	 *
	 * @var array $_fetchErrors array of errors returned by the model when querying data (mostly datatables)
	 */
	protected $_fetchErrors;
	
	/**
	 * To add datatables integration functionality
	 *
	 * @var array $_sortableColumns array of columns to be included in the sorting of the returned data for datatables.
	 */
	protected $_sortableColumns;
	
	/**
	 * Cache object of the application
	 *
	 * @var Zend_Cache $_cache caching mechanism for the application
	 */
	protected $_cache;
	
	/**
	 * Logger of important actions against the database
	 *
	 * @var Application_Model_Logs $_dbLogger logging mechanism for the application
	 */
	protected $_dbLogger;

	/**
	 * Default connection name Base
	 * 
	 * @var string connection name parametres for db
	 */
	protected $_connectionName = 'base';	

	/**
	 * Contruct. Extends default constructor calculating the name of the table.
	 *
	 * @param unknown $config OPTIONAL
	 */
	public function __construct()
	{
		if (! $this->_name)
			$this->_name = $this->_getTableName();
		
		parent::__construct(Zend_Registry::get('multidb')->getDb($this->_connectionName));
	}
	
	/**
	 * Inits extra stuff
	 */
	public function init()
	{
		if (! is_null($this->_cache) and Zend_Registry::isRegistered('cache'))
			$this->_cache = Zend_Registry::get('cache');		
		
		if ($this->_dbLogger !== false)
			$this->_dbLogger = new Application_Model_DbLogger();		
	}

	/**
	 * Guesses the name of the table according to the namespaced name of the class
	 *
	 * @return string containing the most possible name for the table
	 */
	private function _getTableName()
	{
		$namespacedName = get_class($this);
		$namespacedNameArray = explode('_', $namespacedName);

		$name = strtolower(array_pop($namespacedNameArray));
		return $name;
	}
		
	/**
	 * Mirrors the fetchAll functionality from Zend_Db_Table_Abstract. Adds to cache automatically if available.
	 * 
     * @param string|array|Zend_Db_Table_Select $where  OPTIONAL An SQL WHERE clause or Zend_Db_Table_Select object.
     * @param string|array                      $order  OPTIONAL An SQL ORDER clause.
     * @param int                               $count  OPTIONAL An SQL LIMIT count.
     * @param int                               $offset OPTIONAL An SQL LIMIT offset.
     * @return Zend_Db_Table_Rowset_Abstract The row results per the Zend_Db_Adapter fetch mode.
     */
	public function getAll($where = null, $order = null, $limit = null, $offset = null)
	{
		$cache = $this->_cache;
		if ($cache)
		{
			$extra = '';
			if (func_num_args() > 0)
			{
				$args = serialize(func_get_args());
				$extra = md5($args);	//unique key for that query
			}			
			$cacheKey = $this->_name . "_all_" . $extra;			

			if (! $all = $cache->load($cacheKey))
			{
				$all = $this->fetchAll($where, $order, $limit, $offset);
				$cache->save($all, $cacheKey);
			}
			return $all;
		}
		return $this->fetchAll($where, $order, $limit, $offset);
	}
	
	/**
	 * Get the count of results for a certain condition
	 *
	 * @param string|array $where OPTIONAL condition to apply to the query
	 * @return int number of rows matching the condition
	 */
	public function getCount($where = null)
    {
        $select = $this->select();
        $select->from($this, array('count(*) as amount'));
		
		//apply condiction
		if (! is_null($where))
		{
			if (is_string($where))
				$select->where($where);
			elseif (is_array($where) and count($where))
			{
				foreach ($where as $w)
					$select->where($w);
			}
		}
		
        $rows = $this->fetchAll($select);
        return ($rows[0]->amount);       
    }
	
	/**
	 * Clean cache of the application before any data saving or delete so that the results are recalculated in the next refresh	 *
	 */
	private function _cleanCache()
	{
		if ($this->_cache and count($this->_cache->getIds()))
		{
			if ($this->_cache->test($this->_name . '_all_'))
				$this->_cache->remove($this->_name . '_all_');			
			else											
				$this->_cache->clean(Zend_Cache::CLEANING_MODE_ALL);	
		}
	}
	
	/**
	 * Calls the db logger and save the action
	 *
	 * @param string $action action being executed
	 * @data array $data set of data for the action
	 */
	private function _logDb($action, $data)
	{
		if ($this->_dbLogger)
		{
			$this->_dbLogger->add(array(
				'db_table' => $this->_name,
				'action' => $action,
				'data' => Zend_Json::encode($data)
			));	//in dbLogger model will be added datestamps and other useful information, as user_id
		}
	}
	
	//CRUD FUNCTIONALITY
	
	/**
 	 * Add a new record to the table corresponding with the data set in the array.
	 *
	 * @param array $data containing the information to save to the database
	 * @return id of the new inserted data or null
	 */
	public function add(array $data)
	{
		if ($this->_validate($data))
		{
			$this->_logDb('add', $data);
			
			$this->_cleanCache();
			$row = $this->createRow($data);
			return $row->save();
		}
		return false;
	}
	
	/**
 	 * Edit a record with id $id setting the data passed as parameter
	 *
	 * @param int $id id of the row to edit
	 * @param array $data containing the information to save to the database
	 * @return id of the new inserted data or false
	 */
	public function edit($id, array $data)
	{
		$row = $this->getOneById($id);
		if ($row and $this->_validate($data, false))
		{
			$this->_logDb('edit', $data);
			
			$this->_cleanCache();
			$row->setFromArray($data);
			return $row->save();
		}
		return false;
	}
	
	/**
	 * Deletes a row in the table with id equals $id
	 *
	 * @param int $id id of the row to be deleted
	 * @return number of rows deleted (1 or 0)
	 */
	public function delete($id)
	{
		$this->_logDb('delete', array('id' => $id));
		
		$this->_cleanCache();
		$where = $this->getAdapter()->quoteInto('id = ?', $id);		
		return $this->_delete($where);		
	}
	
	/**
	 * Deletes according to a where clause. Internal function
	 * 
	 * @param string $where condition for the data to be deleted
	 * @return int number of rows deleted
	 */
	protected function _delete($where)
	{
		return parent::delete($where);
	}
	
	
	/**
	 * Validates an array of data applying the rules defined for the model.
	 *
	 * @param array $data set of data to be validated
	 * @param bool $checkNotEmpty whether we are checking the not empty rules or not (useful when edit)
	 * @return whether the data is valid or not
	 */	
	private function _validate($data, $checkNotEmpty = true)
	{
		//asume no errors by default and let's try to prove it wrong...
		$this->_errors = array();	
		$valid = true;
				
		if ($this->_validationRules)
		{
			foreach ($this->_validationRules as $field => $rules)
			{
				if ($rules instanceof Zend_Validate_Abstract or is_string($rules))	//'id' => new Zend_Validate_Int(), 'id' => 'Int'
				{
					if (is_string($rules))
					{
						$validatorName = (strstr($rules, '_')) ? $rules : "Zend_Validate_" . ucfirst($rules);
						$rules = new $validatorName();
					}

					if (!isset($data[$field]) and $rules instanceof Zend_Validate_NotEmpty and $checkNotEmpty)
						$data[$field] = '';		//force empty field and hence force error

					if (isset($data[$field]))	
						$valid = ($valid and $this->_proccessRule($rules, $data[$field], $field));
				}
				elseif (is_array($rules))											//'id' => array(rule1, rule2, rule3...),
				{
					foreach ($rules as $rule)
					{
						if (is_string($rule))
						{
							$validatorName = (strstr($rule, '_')) ? $rule : "Zend_Validate_" . ucfirst($rule);
							$rule = new $validatorName();
						}
						elseif (is_array($rule))
						{
							$validatorName = (strstr($rule[0], '_')) ? $rule[0] : "Zend_Validate_" . ucfirst($rule[0]);
							$rule = new $validatorName($rule[2]);
						}
						
						if (!isset($data[$field]) and $rule instanceof Zend_Validate_NotEmpty and $checkNotEmpty)
							$data[$field] = '';		//force empty field and hence force error

						if (isset($data[$field]))	
							$valid = ($valid and $this->_proccessRule($rule, $data[$field], $field));
					}//foreach rule
				}
			}//foreach rules
		}

		return $valid;
	}
	
	/**
	 * Process a validator with a given value and keeps error message in array of errors.
	 *
	 * @param Zend_Validate_Abstract $rule validator to run in the $value
	 * @param mixed $value value to check against the validator
	 * @param OPTIONAL string $field name of the field to set the error message in.
	 * @return bool true is value pass the validator, false otherwise
	 */
	private function _proccessRule(Zend_Validate_Abstract $rule, $value, $field = 'generic')
	{
		$valid = true;
		if (!$rule->isValid($value))
		{
			$valid = false;
			
			$field = Inflector::humanize($field);
			if (!isset($this->_errors[$field]))
				$this->_errors[$field] = array();

			array_push($this->_errors[$field], $rule->getMessages());
		}
		
		//if field is empty and validator is not NotEmpty let's just bypass the result of the test
		if ($value == '' and ! ($rule instanceof Zend_Validate_NotEmpty))
			$valid = true;
		
		return $valid;
	}
	
	/**
	 * Return the validation errors array.
	 *
	 * @return array containing the possible errors after validating a set of data
	 */
	public function getValidationErrors()
	{
		return $this->_errors;
	}
	
	/**
	 * Return the validation rules defined for the model.
	 *
	 * @return array containing of rules defined for each field. 
	 */
	public function getValidationRules()
	{
		return $this->_validationRules;
	}
	
	/**
	 * Generic getter of ONE ROW by property name
	 * 
	 * @param string $what name of the field (what do we want)
	 * @param string $value value of the field that we are looking for
	 * @return Row containing the result according to the search
	 */
	public function getOneBy($what, $value)
	{
		$what = strtolower($what);	//field names are lowercased
		$where = $this->getAdapter()->quoteInto("$what = ?", $value);		
		return $this->fetchRow($where);			
	}

	/**
	 * Generic getter of A ROWSET by property name
	 * 
	 * @param string $what name of the field (what do we want)
	 * @param string $value value of the field that we are looking for
	 * @return Rowset containing the result according to the search
	 */
	public function getAllBy($what, $value)
	{
		$what = strtolower($what);	//field names are lowercased
		$where = $this->getAdapter()->quoteInto("$what = ?", $value);		
		return $this->fetchAll($where);			
	}
	
	/**
	 * Generic delete by property name
	 * 
	 * @param string $what name of the field (what do we want)
	 * @param string $value value of the field that we are looking for
	 * @return number of rows deleted
	 */
	public function deleteAllBy($what, $value)
	{
		$what = strtolower($what);	//field names are lowercased
		$where = $this->getAdapter()->quoteInto("$what = ?", $value);		
		return $this->_delete($where);			
	}	
	
	/**
	 * Magic method used to create calls in getOneBy, getAllBy, deleteAllBy... methods
	 *
	 * @param string $name function name that is trying to be executed but doesn't exists
	 * @param array $values set of values for that function
	 * @return tries to generate a valid getter from the name of the function and return its result or throws exception
	 */
	public function __call($name, $values)
	{
		switch (substr($name, 0, 8))
		{
			case 'getOneBy':
				return $this->getOneBy(substr($name, 8), $values[0]);
				break;
			case 'getAllBy':
				return $this->getAllBy(substr($name, 8), $values[0]);
				break;
		}
		
		switch (substr($name, 0, 11))
		{
			case 'deleteAllBy':
				return $this->deleteAllBy(substr($name, 11), $values[0]);
				break;
		}
		
		throw new Exception("Invalid method name '$name'");
	}
	
	/**
	 * Returns the name of the table in the database
	 *
	 * @return string containing the name of the table assigned to this model in the database
	 */
	public function getTableName()
	{
		return $this->info('name');
	}
	
	/**
	 * Returns the field names for that table
	 *
	 * @return array containing the name of the fields for that table
	 */
	public function getFields()
	{
		return $this->info(Zend_Db_Table_Abstract::COLS);
	}
	
	/**
	 * Get fetching data possible errors
	 *
	 * @return array of errors due to invalid fetching data parameters
	 */
	public function getFetchErrors()
	{
		return $this->_fetchErrors;
	}
	
	/**
	 * Get datatables data. Uses query builder to get the data in json format receiving the standard input for datatables via AJAX
	 *
	 * @param array $request values sent by datatables to get the data paginated, sorted and filtered
	 * @param bool $alternative_format accepts alternative input and adapts output to a set format (datatables or api selection basically)
	 * @return array containing the result
	 */	
	public function getDatatables($request, $alternative_format = false)
	{
		if ($alternative_format)
		{
			$request = Base_Rest_Helper_Datatables::adaptRequest($request, $this->_sortableColumns);
		}
		
		if (! $this->_checkDatatablesRequired($request))
			return false;
		
		$infoTable = $this->info();
		
		$aColumns = $this->_sortableColumns;
		$sIndexColumn = $this->_name . "." . $infoTable['primary'][$this->_identity];
		
		if (!$aColumns)
		{
			$this->_fetchErrors[] = 'Need to define sortable columns beforehand';
			return false;
		}
		
		$select = $this->select();
        $select->from($this->_name);
		
		//add related data
		$this->_addJoins($select);
		
		$this->_applyLimit($select, $request);
		$this->_applyOrder($select, $aColumns, $request);
		$this->_applyGlobalWhere($select, $aColumns, $request);
		$this->_applyIndividualWhere($select, $aColumns, $request);
		$this->_extendQuery($select);

		$data = $this->_produceOutput($select, $aColumns, $sIndexColumn, $request, $alternative_format);
		
		if ($alternative_format)
		{
			$data = Base_Rest_Helper_Datatables::adaptResponse($data);
		}
				
		return $data;
	}
	
	/**
	 * Checks that basic required indexes for datatables script are present
	 *
	 * @param array $request params for datatables search
	 * @throws Exception when indexes are not present
	 */
	private function _checkDatatablesRequired($request)
	{
		$valid = true;
		
		$required_indexes = array('iDisplayLength', 'iDisplayStart', 'iSortingCols', 'sSearch', 'sEcho');
		foreach($required_indexes as $index)
		{
			if (! isset($request[$index]))
			{
				$this->_fetchErrors[] = $index . " is required";
				$valid = false;
			}
		}
		
		return $valid;
	}
	
	/**
	 * Apply joins if the sortable columns array contains any related column
	 *
 	 * @param Zend_Db_Select $select REFERENCE query object
 	 * @param bool $include_columns whether to include the columns in the select or not
	 */
	private function _addJoins(&$select, $include_columns = true)
	{
		$join_columns = null;
		foreach ($this->_sortableColumns as $column)
		{
			$bits = explode('.', $column);
			if (count($bits) > 1 and $bits[0] != $this->_name)	//table.field and table not current table
			{
				$join_columns[$bits[0]][$this->_getFieldName($column)] = $column;	//join_columns[users][users_username] = users.username
			}
		}
		
		if (! is_null($join_columns))
		{
			foreach ($join_columns as $table => $columns)
			{
				$cols = ($include_columns) ? $columns : array();
				$select->joinLeft($table, $table . '.id = ' . Inflector::singularize($table) . '_id', $cols);		//join(users, users.id = user_id, $columns)
			}
			$select->setIntegrityCheck(false);
		}
	}
	
	/**
	 * Set the sortable columns for a model.
	 *
	 * @param array $columns list of columns for the data to be sorted by. 
	 *
	 */
	public function setSortableColumns(array $columns)
	{
		if (! empty($columns))
		{
			foreach ($columns as $key => $column)
			{
				if (strpos($column, '.') === false)
				{
					$columns[$key] = $this->_name . "." . $column;	//prepend table prefix to field name
				}
			}
		}
		$this->_sortableColumns = $columns;
	}

	/**
	 * Apply the limit and offset to the select object according to the data
	 *
	 * @param Zend_Db_Select $select query object
	 * @param array $request parameters to apply the operations
	 */
	private function _applyLimit(&$select, $request)
	{
		//check values
		if ($request['iDisplayLength'] < '-1')
			$request['iDisplayLength'] = self::DEFAULT_LIMIT;
			
		if ($request['iDisplayStart'] < 0)
			$request['iDisplayStart'] = self::DEFAULT_OFFSET;
		
		//and apply them
		if ($request['iDisplayLength'] != '-1')
			$select->limit($request['iDisplayLength'], $request['iDisplayStart']);		
	}

	/**
	 * Apply the order to the select object according to the data
	 *
	 * @param Zend_Db_Select $select query object
	 * @param array $columns columns to apply the sorting
	 * @param array $request parameters to apply the operations
	 */
	private function _applyOrder(&$select, array $columns, array $request)
	{
		$orderClauses = array();
		for ($i = 0; $i < $request['iSortingCols']; $i++)
		{
			if (isset($columns[$request['iSortCol_'.$i]]) and isset($request['sSortDir_'.$i]))
				$orderClauses[] = $columns[$request['iSortCol_'.$i]] . " " . $request['sSortDir_'.$i];
		}
		
		if (count($orderClauses))
			$select->order($orderClauses);
	}

	/**
	 * Apply the global where to the select object according to the data
	 *
	 * @param Zend_Db_Select $select query object
	 * @param array $columns columns to apply the sorting
	 * @param array $request parameters to apply the operations
	 */
	private function _applyGlobalWhere(&$select, array $columns, array $request)
	{
		if ($request['sSearch'] != "" )
		{
			if (count($columns))
			{
				$db = $this->getAdapter();
				
				$where = '(';
				for ($i = 0; $i < count($columns); $i++)
				{
					$where .= $db->quoteInto($columns[$i] . ' LIKE ?', '%' . $request['sSearch'] . '%') . ' OR ';
				}
				$where = substr_replace($where, "", -3);	//last OR 
				$where .= ')';
				
				$select->where($where);
			}
		}		
	}

	/**
	 * Apply the individual wheres to the fields in the select object according to the data
	 *
	 * @param Zend_Db_Select $select query object
	 * @param array $columns columns to apply the sorting
	 * @param array $request parameters to apply the operations
	 */
	private function _applyIndividualWhere(&$select, array $columns, array $request)
	{
		for ($i = 0; $i < count($columns); $i++)
		{
			if ($request['bSearchable_'.$i] == "true" and $request['sSearch_'.$i] != '' )
			{
				$select->where($columns[$i]." LIKE ?", '%' . $request['sSearch_'.$i]. '%');
			}
		}		
	}

	/**
	 * Apply the limit and offset to the select object according to the data
	 *
	 * @param Zend_Db_Select $select query object
	 * @param array $columns columns to apply the sorting
	 * @param string $pk name of the id field
	 * @param array $request parameters to apply the operations
	 * @param bool $full_data whether to bring all fields for every record with string keys or not
	 * @return array with the formatted data for datatables
	 */
	private function _produceOutput(&$select, array $columns, $pk, array $request, $full_data = false)
	{
		//data to display
		$db = $this->getAdapter();
		$stmt = $db->query($select);
		$rResult = $stmt->fetchAll();

		//length of data after filtering (but without limits applied)
		$select->reset(Zend_Db_Select::LIMIT_COUNT);
		$select->reset(Zend_Db_Select::LIMIT_OFFSET);
		$select->reset(Zend_Db_Select::FROM);
		$select->reset(Zend_Db_Select::COLUMNS);	//for related data
		$select->from($this, array('COUNT(' . $pk . ') AS c'));
		
		$this->_addJoins($select, false);

		$stmt = $db->query($select);
		$rFilteredTotal = $stmt->fetchAll();
		$iFilteredTotal = $rFilteredTotal[0]['c'];
		
		//total length of the data
		$totalSelect = $this->select();
		$totalSelect->from($this, array('COUNT(' . $pk . ') AS c'));

		$stmt = $db->query($totalSelect);
		$rResultTotal = $stmt->fetchAll();
		$iTotal = $rResultTotal[0]['c'];
		
		//output array
		$output = array(
			"sEcho" => $request['sEcho'],
			"iTotalRecords" => $iTotal,
			"iTotalDisplayRecords" => $iFilteredTotal,
			"aaData" => array()
		);
		
		foreach ($rResult as $aRow)
		{
			if (! $full_data)
			{
				$row = array();
				for ($i = 0; $i < count($columns); $i++)
				{
					$row[] = $aRow[$this->_getFieldName($columns[$i])];		//we are creating aliases for the related data create attributes names equals to field names
				}
			}
			else
			{
				$row = $aRow;	
			}
			
			if (isset($request['module']) and isset($request['controller']))
			{
				$extra_data = array(
					'module' => $request['module'],
					'controller' => $request['controller'],
					'id' => $aRow['id']
				);
				$row['extra'] = $extra_data;
			}

			$output['aaData'][] = $row;
		}
		
		return $output;			
	}
	
	/**
	 * Gets the field name extracted from a string. If it is table.field return field or table_field
	 *
	 * @param string $string contains the full field name
	 * @param bool $just_field returns just field name without table_ prepended
	 * @return string portion of string containing just the fieldname
	 */
	private function _getFieldName($string, $just_field = false)
	{
		$fieldName = explode('.', $string);
		if (count($fieldName) > 1 and $fieldName[0] != $this->_name and !$just_field)	//field from other table in format table.field
			$fieldName = $fieldName[0] . "_" . $fieldName[1];
		elseif (count($fieldName) > 1 and $fieldName[0] == $this->_name)				//field from same table in format table.field
			$fieldName = $fieldName[1];
		else																			//field in format field
			$fieldName = $fieldName[0];

		return $fieldName;		
	}
	
	/**
	 * Extends the query object with additional stuff.
	 *
	 * @param Zend_Db_Select $select object with the current query
	 */
	protected function _extendQuery(&$select)
	{
		//to be extended in concrete models if wanted
	}
}
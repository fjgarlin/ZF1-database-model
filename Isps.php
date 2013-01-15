<?php
/**
 * Settings_Model_Isps. Isps model.
 *
 * @author Fran Garcia <fjgarlin@gmail.com>
 * @package Base Package
 */
class Settings_Model_Isps extends Base_Model
{
	/**
	 * @var array $_validationRules rules to apply to the model before saving any data
	 */
	protected $_validationRules = array(
		'ip_lo' => array('NotEmpty', 'Ip'),
		'ip_hi' => array('NotEmpty', 'Ip'),
		'ip_lo_long' => array('NotEmpty', 'Int'),
		'ip_hi_long' => array('NotEmpty', 'Int'),
		'description' => 'NotEmpty',
		'email' => 'EmailAddress'
	);


	/**
	 * Get all isps order by ip
	 *
	 * @return array of isps
	 */
	public function getAll()
	{
		return parent::getAll(null, 'ip_lo_long ASC'); //not caching version
	}

	/**
	 * Get one isp within a range. 
	 *
	 * @param unknown $ip_lo
	 * @param unknown $ip_hi
	 * @return Row object containing the isp or null
	 */
	public function getOneByRange($ip_lo, $ip_hi)
	{
		$ip_lo_long = sprintf('%u', ip2long($ip_lo));
		$ip_hi_long = sprintf('%u', ip2long($ip_hi));

		$where1 = $this->getAdapter()->quoteInto('ip_lo_long >= ?', $ip_lo_long);
		$where2 = $this->getAdapter()->quoteInto('ip_hi_long <= ?', $ip_hi_long);
		$where = "$where1 AND $where2";

		return $this->fetchRow($where);
	}

	/**
	 * Add new isp. Add long int representation of ip ranges. Needed for sorting. 
	 *
	 * @param array $data set of data to save. 
	 * @return int $id of the element added or false
	 */
	public function add(array $data)
	{
		if (isset($data['ip_lo']) and isset($data['ip_hi']) and ! $this->getOneByRange($data['ip_lo'], $data['ip_hi']))
		{
			$data['ip_lo_long'] = sprintf('%u', ip2long($data['ip_lo']));
			$data['ip_hi_long'] = sprintf('%u', ip2long($data['ip_hi']));
			return parent::add($data);
		}
		return false;
	}

	/**
	 * Edit an isp. Recreates long int representation of the ips
	 *
	 * @param int $id id of the isp to edit
	 * @param array $data set of data to be saved
	 * @return $id or false
	 */
	public function edit($id, array $data)
	{
		$isp = $this->getOneById($id);
		if (isset($data['ip_lo']) and isset($data['ip_hi']))
		{
			$ispRange = $this->getOneByRange($data['ip_lo'], $data['ip_hi']);
			if ($isp and (!$ispRange or $ispRange->id == $id))
			{
				//recalcute them
				$data['ip_lo_long'] = sprintf('%u', ip2long($data['ip_lo']));
				$data['ip_hi_long'] = sprintf('%u', ip2long($data['ip_hi']));
				
				return parent::edit($id, $data);
			}
		}
		return false;
	}

	/**
	 * Extends the query object with additional stuff.
	 *
	 * @param Zend_Db_Select $select REFERENCE object with the current query
	 */
	protected function _extendQuery(&$select)
	{
		$orderData = $select->getPart('order');
		if ($orderData)
		{
			switch ($orderData[0][0])
			{
				case 'ip_lo':
					$select->reset(Zend_Db_Select::ORDER);
					$select->order('ip_lo_long ' . $orderData[0][1]);
					break;
				case 'ip_hi':
					$select->reset(Zend_Db_Select::ORDER);
					$select->order('ip_hi_long ' . $orderData[0][1]);
					break;
			}
		}
	}

}
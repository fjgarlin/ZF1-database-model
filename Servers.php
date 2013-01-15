<?php
/**
 * Settings_Model_Servers. 
 *
 * @author Fran Garcia <fjgarlin@gmail.com>
 * @package Base Package
 */
class Settings_Model_Servers extends Base_Model
{
  	/**
	 * @var array $_validationRules rules to apply before saving any data
	 */      
	protected $_validationRules = array(
		'techcode' => 'NotEmpty',
		'host' => array('Ip', 'NotEmpty'),
		'port' => 'Base_Validate_Port',
	);    
}

<?php
/**
 * Settings_Model_Servers. 
 *
 * @author Fran Garcia <fjgarlin@gmail.com>
 * @package Base Package
 */
class Settings_Model_Servers extends FCL_Fingerprinting_Dao_Servers
{
  	/**
	 * @var array $_validationRules rules to apply before saving any data
	 */      
	protected $_validationRules = array(
		'techcode' => 'NotEmpty',
        'host' => array('Ip', 'NotEmpty'),
        'port' => 'Base_Validate_Port',
        //'in_email' => 'EmailAddress',
		//'refresh_port' => 'Base_Validate_Port',
        //'id_host' => array('Ip', 'NotEmpty'),
        //'id_port' => 'Base_Validate_Port',
		//'project_id' => 'Int'
	);    
}

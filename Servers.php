<?php
/**
 * Settings_Model_Servers. 
 *
 * @author Gurbinder Singh <gsingh@friendmts.co.uk>
 * @package Expose Package
 * @copyright Copyright (c) 2012 Friend Media Technology Systems Ltd.
 */
class Settings_Model_Servers extends FCL_Fingerprinting_Dao_Servers
{
  	/**
	 * @var array $_validationRules rules to apply before saving any data
	 */      
	protected $_validationRules = array(
		'techcode' => 'NotEmpty',
        'host' => array('Ip', 'NotEmpty'),
        'port' => 'Expose_Validate_Port',
        //'in_email' => 'EmailAddress',
		//'refresh_port' => 'Expose_Validate_Port',
        //'id_host' => array('Ip', 'NotEmpty'),
        //'id_port' => 'Expose_Validate_Port',
		//'project_id' => 'Int'
	);    
}

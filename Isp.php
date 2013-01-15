<?php
/**
 * Settings_Form_Isp. Form object for isp management. 
 *
 * @author Fran Garcia <fjgarlin@gmail.com>
 * @package Base Package
 */
class Settings_Form_Isp extends Base_Form
{
	/**
	 * Whether the data given is valid or not. Calls parent function after initial checking. 
	 *
	 * @param array $data set of data to check
	 * @return bool whether the data is valid or not according to the defined rules. 
	 */
	public function isValid($data)
	{
		//in init values are not set yet, so we do it here, overriding the isValid method
		$this->getElement('ip_hi')
			->addValidator(
				'GreaterThan',
				false,
				array(
					'min' => $data['ip_lo']
				)
			);

		return parent::isValid($data);
	}

	/**
	 * Initializes the form object.
	 */
	public function init()
	{
		$this->setMethod('post');
		$this->setName('isp');

		$id = new Zend_Form_Element_Hidden('id');

		$ip_lo = new Zend_Form_Element_Text('ip_lo');
		$ip_lo->setLabel('First IP')
			->addFilter('StringTrim')
			->setAttrib('class', 'validate[required]')
			->setRequired(true);

		$ip_hi = new Zend_Form_Element_Text('ip_hi');
		$ip_hi->setLabel('Last IP')
			->addFilter('StringTrim')
			->setAttrib('class', 'validate[required]')
			->setRequired(true);

		$email = new Zend_Form_Element_Text('email');
		$email->setLabel('Email')
			->setRequired(true)
			->setAttrib('class', 'validate[required,custom[email]]')
			->addFilter('StringTrim');

		$description = new Zend_Form_Element_Textarea('description');
		$description->setLabel('Description')
			->setRequired(true)
			->setAttrib('rows', '10')
			->setAttrib('cols', '35')
			->addFilter('StringTrim');

		$signature = new Zend_Form_Element_Select('signing_type');
		$signature->setLabel('Signature type')
			->setAttrib('class', 'regular');
		$this->_loadSignatures($signature);

		$submit = new Zend_Form_Element_Submit('submit');
		$submit->setName('Save')->setAttrib('class', 'btn green submit');

		$this->addElements(array($id, $ip_lo, $ip_hi, $email, $description, $signature, $submit));

		//add validators from the model
		$this->injectValidators();
	}

	/**
	 * Load the signatures types into the select object. 
	 *
	 * @param Zend_Form_Element_Select $select REFERENCE object to load the data in
	 */
	protected function _loadSignatures(Zend_Form_Element_Select &$select)
	{
		$types = Zend_Registry::get('Settings_Signature_Types');
		foreach ($types as $key => $type)
		{
			$select->addMultiOption($key, $type);
		}
	}

}
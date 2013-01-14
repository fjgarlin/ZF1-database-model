<?php
/**
 * Expose Form. Class that extends Zend_Form to add the functionality of adding the validators from a model
 *
 * @author Francisco Garcia <fgarcia@friendmts.co.uk>
 * @package Expose Package
 * @copyright Copyright (c) 2012 Friend Media Technology Systems Ltd.
 */
abstract class Expose_Form extends Zend_Form
{
	/**
	 * Inject the validators to the form object. 
	 *
	 * @param Expose_Model $model model from where we will take the validators
	 */
	public function injectValidators(Expose_Model $model = null)
	{
		if (is_null($model))
			$model = $this->_guessModel();
			
		$validators = $model->getValidationRules();
		if ($validators)
		{
			foreach ($validators as $field => $validatorsField)
			{
				if (!is_array($validatorsField))
					$validatorsField = array($validatorsField);
				
				//just in case it is being used Zend_Validate_XXXX or Expose_Validate_XXXX
				foreach($validatorsField as $key => &$value)
				{
					$value = (is_string($value) and strstr($value, '_')) ? new $value() : $value;
				}

				$element = $this->getElement($field);
				if ($element)
					$this->getElement($field)->addValidators($validatorsField);
			}
		}
	}
	
	/**
	 * Guess the associated model according to the class name of the form object
	 *
	 * @return instance of the guessed model or throws exception
	 */
	protected function _guessModel()
	{
		$className = get_class($this);
		$classNameArray = explode('_', $className);
		$model = null;
		try
		{
			$classNameArray[1] = 'Model';
			$classNameArray[2] = Expose_Inflector::pluralize($classNameArray[2]);
			$className = implode('_', $classNameArray);
			
			return new $className();
		}
		catch (Exception $e)
		{
			throw new Exception('Invalid model');
		}
	}
}
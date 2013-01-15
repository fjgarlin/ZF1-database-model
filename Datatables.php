<?php
/**
 * Base_Controller_Action_Helper_Datatables
 *
 * Calls the datatables method in the model and performs the required actions for an ajax action
 *
 * @author Fran Garcia <fjgarlin@gmail.com>
 * @package Base Package
 */
class Base_Controller_Action_Helper_Datatables extends Zend_Controller_Action_Helper_Abstract
{
	/**
	 * Perform helper when called as $this->_helper->Datatables() from an action controller
	 *
	 * @param array $sortable_columns needed columns in result data
	 * @param object $model model to get the data from
	 * @param bool $view_link        OPTIONAL render or not view link
	 * @param bool $edit_link        OPTIONAL render or not edit link
	 * @param bool $delete_link      OPTIONAL render or not delete link
	 * @param array $request		 OPTIONAL possible overriding of the datatables request
	 * @return string output produced
	 */
	public function direct($sortable_columns, $model, $view_link = true, $edit_link = true, $delete_link = true, $request = null)
	{
		return $this->datatables($sortable_columns, $model, $view_link, $edit_link, $delete_link, $request);
	}

	/**
	 * Disables view, calls datatables method and output in required format
	 *
	 * @param array $sortable_columns needed columns in result data
	 * @param object $model model to get the data from
	 * @param bool $view_link        OPTIONAL render or not view link
	 * @param bool $edit_link        OPTIONAL render or not edit link
	 * @param bool $delete_link      OPTIONAL render or not delete link
	 * @param array $request		 OPTIONAL possible overriding of the datatables request
	 * @return string output produced
	*/
	public function datatables($sortable_columns, $model, $view_link = true, $edit_link = true, $delete_link = true, $request = null)
	{
		//instanciate needed helpers and view
		$viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
		$layout = Zend_Controller_Action_HelperBroker::getStaticHelper('layout');
		$csv = Zend_Controller_Action_HelperBroker::getStaticHelper('Csv');

		$view = Zend_Layout::getMvcInstance()->getView();

		//set datatables stuff and get data
		if (is_null($request))
		{
			//disable layout
			$viewRenderer->setNoRender();
			$layout->disableLayout();			
			
			$request = $this->getRequest()->getParams();
		}
		
		$model->setSortableColumns($sortable_columns);
		$data = $model->getDatatables($request);

		if (isset($request['output']) and $request['output'] == 'csv')
		{
			//rmeove extra info to build links
			$data = $view->datatablesArrayLinks($data, false, false, false);
			$res = $csv->csv($data['aaData']);
		}
		else
		{
			//set links for datatables
			$data = $view->datatablesArrayLinks($data, $view_link, $edit_link, $delete_link);
			$res = Zend_Json::encode($data);
		}

		return $res;
	}

}
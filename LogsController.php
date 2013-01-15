<?php

class LogsController extends Zend_Controller_Action
{
	public function init()
	{
		if (! Base_User::isAdmin())
			$this->_redirect('/index/dash');
	}
	
	public function indexAction()
	{
		//data retrieved via AJAX
		$this->view->ajaxget = true;
	}
	
	
	public function asyncAction()
	{
		$usersModel = new Admin_Model_Users();
		$usersTable = $usersModel->getTableName();
		
		$model = new Application_Model_ActivityLogger();
		$sortable_columns = array('date_created', 'user_id', "$usersTable.username", 'uri', 'ip_address', 'browser');		

		$res = $this->_helper->Datatables($sortable_columns, $model, false, false, false);
		echo $res;		
	}
}
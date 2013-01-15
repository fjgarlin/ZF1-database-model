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
		$type = $this->_getParam('type');
		$this->view->type = $type;
	}
	
	public function databaseAction()
	{
		$usersModel = new Admin_Model_Users();
		$usersTable = $usersModel->getTableName();
		
		$model = new Application_Model_DbLogger();
		$sortable_columns = array('date_created', 'user_id', "$usersTable.username", 'db_table', 'action', 'data');		
		
		$res = $this->_helper->Datatables($sortable_columns, $model, false, false, false);
		echo $res;		
	}
	
	public function activityAction()
	{
		$usersModel = new Admin_Model_Users();
		$usersTable = $usersModel->getTableName();
		
		$model = new Application_Model_ActivityLogger();
		$sortable_columns = array('date_created', 'user_id', "$usersTable.username", 'uri', 'ip_address', 'browser');		

		$res = $this->_helper->Datatables($sortable_columns, $model, false, false, false);
		echo $res;		
		
	}
}
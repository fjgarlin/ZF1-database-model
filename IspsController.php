<?php

class Settings_IspsController extends Zend_Controller_Action
{
	protected $model;
	
	public function init()
	{
		$this->model = new Settings_Model_Isps();
		$this->view->signature_types = Zend_Registry::get('Settings_Signature_Types');
	}
	
	public function indexAction()
	{
		//everything done by AJAX (async action)
		$this->view->ajaxget = true;
	}
	
	public function viewAction()
	{
		$isp = $this->model->getOneById((int)$this->_getParam('id'));
		if (! $isp)
			$this->_redirect('/settings/isps');
			
		$this->view->isp = $isp;
	}
		
	public function addAction()
	{
		$form = new Settings_Form_Isp();
		if ($this->getRequest()->isPost())
		{
			$values = $this->_getAllParams();
			if ($form->isValid($values))
			{
				if ($this->model->add($values))
					$this->_redirect('/settings/isps');
				else
					$this->view->errors = $this->model->getValidationErrors();
			}
		}
		
		$this->view->form = $form;
	}
	
	public function editAction()
	{
		$isp = $this->model->getOneById((int)$this->_getParam('id'));
		if (! $isp)
			$this->_redirect('/settings/isps');
		
		$form = new Settings_Form_Isp();
		if ($this->getRequest()->isPost())
		{
			$values = $this->_getAllParams();
			if ($form->isValid($values))
			{
				if ($this->model->edit($isp->id, $values))
					$this->_redirect('/settings/isps');
				else
					$this->view->errors = $this->model->getValidationErrors();
			}
		}
		else
		{
			$form->populate($isp->toArray());
		}

		$this->view->isp = $isp;
		$this->view->form = $form;
	}
	
	public function deleteAction()
	{
		$isp = $this->model->getOneById((int)$this->_getParam('id'));
		if (! $isp)
			$this->_redirect('/settings/isps');
			
		if ($this->getRequest()->isPost())
		{
			if ($this->_getParam('del') == "yes")
			{
				$this->model->delete($isp->id);
			}
			$this->_redirect('/settings/isps');
		}

		$this->view->isp = $isp;	
	}
	
	public function asyncAction()
	{
		$sortable_columns = array('ip_lo', 'ip_hi', 'description', 'email');		
		$res = $this->_helper->Datatables($sortable_columns, $this->model);
		echo $res;
	}
}
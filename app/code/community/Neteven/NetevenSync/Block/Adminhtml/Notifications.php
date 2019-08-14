<?php
/**
 * Notifications for errors logging
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Block_Adminhtml_Notifications extends Mage_Adminhtml_Block_Template {
	
	/**
	 * Get array of errors
	 * 
	 * @return array $errors
	 */
	public function getErrors() {
		$errors = array();
		$errorCodes = Mage::getModel('netevensync/config')->getErrorLabels();
		$collection = Mage::getModel('netevensync/log')->getCollection()->addErrorFilter();
		
		foreach($collection as $logType) {
			$errors[$logType->getCode()] = $errorCodes[$logType->getCode()];
		}
		
		return $errors;
	}
	
	/**
	 * Get clean log url
	 * 
	 * @return string
	 */
	public function getCleanUrl() {
		return $this->getUrl('adminhtml/netevensync/cleanLog');
	}
	
	/**
	 * ACL validation before html generation
	 * 
	 * @return string
	 */
	protected function _toHtml() {
		if(Mage::getSingleton('admin/session')->isAllowed('catalog/netevensync')) {
			return parent::_toHtml();
		}
		return '';
	}
}
<?php
/**
 * Log model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Log extends Mage_Core_Model_Abstract {
	
	/**
	 * Initialize resource model
	 */
	protected function _construct() {
		$this->_init('netevensync/log');
	}
	
	/**
	 * Load log by process code
	 *
	 * @param string $code
	 */
	public function loadByCode($code) {
		$this->addData($this->getResource()->loadByCode($code));
		return $this;
	}
}
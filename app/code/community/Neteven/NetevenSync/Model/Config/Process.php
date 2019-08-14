<?php
/**
 * Process configuration model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Config_Process extends Mage_Core_Model_Abstract {
	
	/**
	 * Initialize resource model
	 */
	protected function _construct() {
		$this->_init('netevensync/config_process');
	}
	
	/**
	 * Load by process code
	 *
	 * @param string $processCode
	 * @return Neteven_NetevenSync_Model_Config_Process
	 */
	public function loadByProcessCode($processCode) {
		$this->addData($this->getResource()->loadByProcessCode($processCode));
		return $this;
	}
	
	/**
	 * Retrieve next sync as timestamp
	 * 
	 * @return int
	 */
	public function getNextSyncTimestamp() {
		return strtotime($this->getNextSync());
	}
}
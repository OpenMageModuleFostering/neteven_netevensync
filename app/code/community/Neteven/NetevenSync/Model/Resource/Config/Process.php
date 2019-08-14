<?php
/**
 * Process configuration resource model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Resource_Config_Process extends Mage_Core_Model_Resource_Db_Abstract {
	
	/**
	 * Initialize resource model
	 */
	protected function _construct() {
		$this->_init('netevensync/process', 'id');
		$this->_read = $this->_getReadAdapter();
	}
	
	/**
	 * Load by process code
	 *
	 * @param string $processCode
	 * @return array
	 */
	public function loadByProcessCode($processCode) {
		$select = $this->_read->select()
			->from($this->getMainTable())
			->where('process_code=:process_code');
	
		$result = $this->_read->fetchRow($select, array('process_code'=>$processCode));
	
		if (!$result) {
			return array();
		}
	
		return $result;
	}
}
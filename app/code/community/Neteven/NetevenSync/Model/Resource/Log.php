<?php
/**
 * Log resource model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Resource_Log extends Mage_Core_Model_Resource_Db_Abstract {
	
	/**
	 * Initialize resource model
	 */
	protected function _construct() {
		$this->_init('netevensync/log', 'id');
		$this->_read = $this->_getReadAdapter();
	}
	
	/**
	 * Load log by process code
	 * 
	 * @param string $code
	 * @return $array $result
	 */
	public function loadByCode($code) {
		$select = $this->_read->select()
		->from($this->getMainTable())
		->where('code=:code');
		
		$result = $this->_read->fetchRow($select, array('code'=>$code));
		
		if (!$result) {
			return array();
		}
		
		return $result;
	}
}
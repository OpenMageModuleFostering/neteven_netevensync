<?php
/**
 * Order temp resource model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Resource_Process_Order_Temp extends Mage_Core_Model_Resource_Db_Abstract {
	
	/**
	 * Initialize resource model
	 */
	protected function _construct() {
		$this->_init('netevensync/order_temp', 'id');
		$this->_read = $this->_getReadAdapter();
	}
	
	/**
	 * Load item from DB by neteven item id
	 *
	 * @param string $itemId
	 * @return array
	 */
	public function loadByNetevenItemId($itemId)
	{
		$select = $this->_read->select()
		->from($this->getMainTable())
		->where('neteven_item_id=:neteven_item_id');
	
		$result = $this->_read->fetchRow($select, array('neteven_item_id'=>$itemId));
	
		if (!$result) {
			return array();
		}
	
		return $result;
	}
}
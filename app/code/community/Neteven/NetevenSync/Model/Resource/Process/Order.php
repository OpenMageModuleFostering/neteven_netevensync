<?php
/**
 * Order resource model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Resource_Process_Order extends Mage_Core_Model_Resource_Db_Abstract {
	
	/**
	 * Initialize resource model
	 */
	protected function _construct() {
		$this->_init('netevensync/order', 'id');
		$this->_read = $this->_getReadAdapter();
	}
	
	/**
	 * Load by order id
	 *
	 * @param int $orderId
	 * @return array
	 */
	public function loadByOrderId($orderId) {
		$select = $this->_read->select()
			->from($this->getMainTable())
			->where('order_id=:order_id');
	
		$result = $this->_read->fetchRow($select, array('order_id'=>$orderId));
	
		if (!$result) {
			return array();
		}
	
		return $result;
	}
	
	/**
	 * Change id fieldname
	 *
	 * @param string $fieldName
	 * @return Neteven_NetevenSync_Model_Resource_Product
	 */
	public function setIdFieldName($fieldName) {
		$this->_idFieldName = $fieldName;
		return $this;
	}
}
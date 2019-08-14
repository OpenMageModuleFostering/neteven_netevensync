<?php
/**
 * Order link resource model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Resource_Process_Order_Link extends Mage_Core_Model_Resource_Db_Abstract {
	
	/**
	 * Initialize resource model
	 */
	protected function _construct() {
		$this->_init('netevensync/order_link', 'id');
		$this->_read = $this->_getReadAdapter();
	}
	
	/**
	 * Load from DB by neteven order id
	 *
	 * @param string $netevenOrderId
	 * @return array
	 */
	public function loadByNetevenOrderId($netevenOrderId)
	{
		$select = $this->_read->select()
			->from($this->getMainTable())
			->where('neteven_order_id=:neteven_order_id');
	
		$result = $this->_read->fetchRow($select, array('neteven_order_id'=>$netevenOrderId));
	
		if (!$result) {
			return array();
		}
	
		return $result;
	}
	
	/**
	 * Load from DB by magento order id
	 *
	 * @param string $magentoOrderId
	 * @return array
	 */
	public function loadByMagentoOrderId($magentoOrderId)
	{
		$select = $this->_read->select()
			->from($this->getMainTable())
			->where('magento_order_id=:magento_order_id');
	
		$result = $this->_read->fetchRow($select, array('magento_order_id'=>$magentoOrderId));
	
		if (!$result) {
			return array();
		}
	
		return $result;
	}
}
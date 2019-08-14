<?php
/**
 * Order link model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Process_Order_Link extends Mage_Core_Model_Abstract {
	
	/**
	 * Initialize resource model
	 */
	protected function _construct() {
		$this->_init('netevensync/process_order_link');
	}
	
	/**
	 * Load data from resource model by neteven order id
	 *
	 * @param int $netevenOrderId
	 */
	public function loadByNetevenOrderId($netevenOrderId)
	{
		$this->addData($this->getResource()->loadByNetevenOrderId($netevenOrderId));
		return $this;
	}
	
	/**
	 * Load data from resource model by magento order id
	 *
	 * @param int $netevenOrderId
	 */
	public function loadByMagentoOrderId($magentoOrderId)
	{
		$this->addData($this->getResource()->loadByMagentoOrderId($magentoOrderId));
		return $this;
	}
}
<?php
/**
 * Inventory resource model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Resource_Process_Inventory extends Mage_Core_Model_Resource_Db_Abstract {
	
	/**
	 * Initialize resource model
	 */
	protected function _construct() {
		$this->_init('netevensync/inventory', 'id');
		$this->_read = $this->_getReadAdapter();
	}
	
	/**
	 * Load by product id
	 *
	 * @param int $productId
	 * @return array
	 */
	public function loadByProductId($productId) {
        $select = $this->_read->select()
            ->from($this->getMainTable())
            ->where('product_id=:product_id');

        $result = $this->_read->fetchRow($select, array('product_id'=>$productId));

        if (!$result) {
            return array();
        }

        return $result;
	}
}
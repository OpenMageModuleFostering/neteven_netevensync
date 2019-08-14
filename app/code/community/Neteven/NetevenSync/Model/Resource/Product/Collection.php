<?php
/**
 * Product collection resource model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Resource_Product_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract {
	
	/**
	 * Configure collection
	 */
	protected function _construct() {
		parent::_construct();
		$this->_init('netevensync/product');
	}
	
	/**
	 * Retrieve product IDs
	 * 
	 * @return array $productIds
	 */
	public function getProductIds() {
		$this->getResource()->setIdFieldName('product_id');
		$this->getSelect()->group('product_id');  // This is a fix in case some items in Neteven Selection are present several times in module version < 1.0.0.1
		$productIds = $this->getAllIds();
		return $productIds;
	}
}
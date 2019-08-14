<?php
/**
 * Order Temp collection resource model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Resource_Process_Order_Temp_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract {
	
	/**
	 * Configure collection
	 */
	protected function _construct() {
		parent::_construct();
		$this->_init('netevensync/process_order'); // CAUTION: we use Neteven_NetevenSync_Model_Process_Order as model so we init netevensync/*order*
	}
}
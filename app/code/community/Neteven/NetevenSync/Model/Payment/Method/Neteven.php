<?php
/**
 * Fake payment model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Payment_Method_Neteven extends Mage_Payment_Model_Method_Abstract {
	
	protected $_code  = 'neteven';
	
	/**
	 * Check whether method is available
	 *
	 * @param Mage_Sales_Model_Quote|null $quote
	 * @return bool
	 */
	public function isAvailable($quote = null)
	{
		return false;
	}
}
<?php
/**
 * Start Hour source options
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Adminhtml_System_Config_Source_Carrier {
	
	/**
	 * Options getter
	 * 
	 * @return array $options
	 */
	public function toOptionArray() {
		$options[] = array('value' => '', 'label' => Mage::helper('adminhtml')->__('-- Please Select --'));
		$carriers = Mage::getSingleton('shipping/config')->getActiveCarriers();
		
		foreach ($carriers as $carrierCode => $carrierModel) {
			// Remove Google Checkout and Neteven "dynamic" as those are not real carriers
			if($carrierCode == 'googlecheckout' || $carrierCode == 'neteven') {
				continue;
			}
			$label = Mage::getStoreConfig('carriers/'.$carrierCode.'/title');
			$options[] = array('value' => $carrierCode, 'label' => $label);
		}
		
		return $options;
	}
}
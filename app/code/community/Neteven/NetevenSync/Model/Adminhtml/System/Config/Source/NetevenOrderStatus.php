<?php
/**
 * Orders state source options
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Adminhtml_System_Config_Source_NetevenOrderStatus {
	
	/**
	 * Options getter
	 * 
	 * @return array $options
	 */
	public function toOptionArray() {
		$srcOptions = Mage::getSingleton('netevensync/config')->getNetevenOrderStatuses();
		$options = array();
		foreach($srcOptions as $code => $label) {
			$options[] = array('value' => $code, 'label' => $label);
		}
		
		return $options;
	}
	
	/**
	 * Get options with empty
	 * 
	 * @return array $options
	 */
	public function toSelect() {
		$options[''] = Mage::helper('adminhtml')->__('--Please Select--');
		$optionsSrc = $this->toOptionArray();
		
		foreach($optionsSrc as $option) {
			$options[$option['value']] = $option['label'];
		}
		
		return $options;
	}	
}
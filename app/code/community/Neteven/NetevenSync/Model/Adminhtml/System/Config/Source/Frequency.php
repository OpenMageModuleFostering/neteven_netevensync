<?php
/**
 * Frequency source options
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Adminhtml_System_Config_Source_Frequency {
	
	/**
	 * Options getter
	 * 
	 * @return array $options
	 */
	public function toOptionArray() {
		$options = array(
					array('value' => '0.25', 'label' => Mage::helper('netevensync')->__('Every 15 minutes')),
					array('value' => '0.5', 'label' => Mage::helper('netevensync')->__('Every 30 minutes')),
				);
		
		$values = array(1, 2, 4, 12, 24);
		foreach($values as $value) {
			$options[] = array('value' => $value, 'label' => Mage::helper('netevensync')->__('Every %s hour(s)', $value));
		}
		
		return $options;
	}
	
	/**
	 * Get optins in "key-value" format
	 * 
	 * @return array $optionsArray
	 */
	public function toArray() {
		$options = array();
		$optionsSrc = $this->toOptionArray();
		
		foreach($optionsSrc as $option) {
			$options[$option['value']] = $option['label'];
		}
		
		return $options;
	}
}
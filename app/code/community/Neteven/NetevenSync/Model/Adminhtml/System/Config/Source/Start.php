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
class Neteven_NetevenSync_Model_Adminhtml_System_Config_Source_Start {
	
	/**
	 * Options getter
	 * 
	 * @return array $options
	 */
	public function toOptionArray() {
		$options = array();
		
		for($i = 0; $i < 24; $i++) {
			$options[] = array('value' => $i, 'label' => sprintf("%02s", $i) . 'h');
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
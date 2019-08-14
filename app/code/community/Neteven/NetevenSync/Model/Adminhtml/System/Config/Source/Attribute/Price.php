<?php
/**
 * Attribute source options for price attributes
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Adminhtml_System_Config_Source_Attribute_Price extends Neteven_NetevenSync_Model_Adminhtml_System_Config_Source_Attribute {
	
	/**
	 * Options getter
	 * 
	 * @return array $options
	 */
	public function toOptionArray() {
		$options = array();
		$disallowedAttributes = Mage::getSingleton('netevensync/config')->getDisallowedAttributes();
		$collection = Mage::getResourceModel('catalog/product_attribute_collection')
			->addVisibleFilter()
			->addFieldToFilter('frontend_input', 'price')
			->setOrder('frontend_label', 'ASC');
		
		foreach($collection as $attribute) {
			$options[] = array('value' => $attribute->getAttributeCode(), 'label' => $attribute->getFrontendLabel());
		}
		
		return $options;
	}
}
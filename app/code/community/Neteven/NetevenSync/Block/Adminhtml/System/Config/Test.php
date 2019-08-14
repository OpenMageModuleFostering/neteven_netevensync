<?php
/**
 * Test connection configuration button
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Block_Adminhtml_System_Config_Test extends Mage_Adminhtml_Block_System_Config_Form_Field {
	
	/**
	 * Generate html for button
	 * 
	 * @param Varien_Data_Form_Element_Abstract $element
	 * @return string $html
	 * @see Mage_Adminhtml_Block_System_Config_Form_Field::_getElementHtml()
	 */
	protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {
		$this->setElement($element);
		
		$url = $this->getUrl('adminhtml/netevensync/testConfiguration');
		
		$html = $this->getLayout()->createBlock('adminhtml/widget_button')
			->setType('button')
			->setLabel($this->__('Test connection setup'))
			->setOnClick("setLocation('$url')")
			->setId($element->getHtmlId())
			->toHtml();
		
		return $html;
	}
}
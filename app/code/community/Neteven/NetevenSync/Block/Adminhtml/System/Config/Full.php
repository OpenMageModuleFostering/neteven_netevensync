<?php
/**
 * Full synchronization button
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Block_Adminhtml_System_Config_Full extends Mage_Adminhtml_Block_System_Config_Form_Field {
	
	/**
	 * Generate field HTML
	 * 
	 * @param Varien_Data_Form_Element_Abstract $element
	 * @see Mage_Adminhtml_Block_System_Config_Form_Field::render()
	 */
	public function render(Varien_Data_Form_Element_Abstract $element) {
		$type = (string) $element->getFieldConfig()->export_type;
		
		$html[] = '<tr><td colspan="3" class="value"><div style="padding: 20px 20px 0 20px; border:1px solid #ddd; background: #faebe7; height: 85px;" id="' . $element->getHtmlId() . '">';
		
		// Manage label
		$html[] = '<div style="float: left; width: 200px;">' . Mage::helper('netevensync')->__('Caution: server load can be heavy. Full synchronization my take several minutes and slow down your Magento system') .'</div>';
		
		// Manage button
		$url = $this->getUrl('adminhtml/netevensync/runProcess', array(
				'mode' => Neteven_NetevenSync_Model_Config::NETEVENSYNC_EXPORT_FULL,
				'type' => $type,
				)
		);
		
		$buttonHtml = $this->getLayout()->createBlock('adminhtml/widget_button')
			->setType('button')
			->setLabel($this->__('Synchronize Now'))
			->setOnClick("window.open('$url')")
			->setClass('fail')
			->toHtml();
		
		$html[] = '<div style="float: right">' . $buttonHtml . '</div>';

		// Close tags and generate tr	
		$html[] = '</div></td></tr>';
		return implode('', $html);
	}
}

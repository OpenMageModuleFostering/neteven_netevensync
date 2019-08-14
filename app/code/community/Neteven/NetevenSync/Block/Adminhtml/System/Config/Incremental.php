<?php
/**
 * Incremental synchronization button
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Block_Adminhtml_System_Config_Incremental extends Mage_Adminhtml_Block_System_Config_Form_Field {
	
	/**
	 * Generate field HTML
	 * 
	 * @param Varien_Data_Form_Element_Abstract $element
	 * @see Mage_Adminhtml_Block_System_Config_Form_Field::render()
	 */
	public function render(Varien_Data_Form_Element_Abstract $element) {
		$type = (string) $element->getFieldConfig()->export_type;
		$updateCount = null;
		
		$html[] = '<tr><td colspan="3" class="value"><div style="padding: 20px 20px 0 20px; border:1px solid #ddd; background: #E7EFEF; height: 50px;" id="' . $element->getHtmlId() . '">';
		
		// Manage label
		if($type && $type == 'inventory') {
			$incrementalCollection = Mage::getModel('netevensync/process_inventory')->getCollection()->toArray(array('product_id'));
			$incrementalItems = array();
			foreach($incrementalCollection['items'] as $item) {
				$incrementalItems[] = $item['product_id'];
			}
			
			$selectionCollection = Mage::getModel('netevensync/product')->getCollection()
				->addFieldToFilter('product_id', array('in' => $incrementalItems));
			
			$updateCount = count($incrementalItems);
			$html[] = '<div style="float: left">' . Mage::helper('netevensync')->__('%s product(s) ready for synchronization<br/>(%s not in Neteven Selection)', $updateCount, count($incrementalItems) - $selectionCollection->count()) .'</div>';
		}
		
		// Manage button
		$url = $this->getUrl('adminhtml/netevensync/runProcess', array(
				'mode' => Neteven_NetevenSync_Model_Config::NETEVENSYNC_EXPORT_INCREMENTAL,
				'type' => $type,
				)
		);
		
		$button = $this->getLayout()->createBlock('adminhtml/widget_button')
			->setId($element->getId() . '_button')
			->setType('button')
			->setLabel($this->__('Synchronize Now'))
			->setOnClick("window.open('$url')");
		
		if($type && $type == 'inventory' && !is_null($updateCount) && $updateCount === 0) {
			$button->setClass('disabled');
		}
		
		$html[] = '<div style="float: right">' . $button->toHtml() . '</div>';
		
		// Add additional JS
		$html[] = $this->_getAdditionalJs($element);

		// Close tags and generate tr	
		$html[] = '</div></td></tr>';
		return implode('', $html);
	}
	
	protected function _getAdditionalJs($element) {
		return "<script type=\"text/javascript\">if($('" . $element->getId() . "_button').hasClassName('disabled')) { $('" . $element->getId() . "_button').disable(); }</script>";
	}
}

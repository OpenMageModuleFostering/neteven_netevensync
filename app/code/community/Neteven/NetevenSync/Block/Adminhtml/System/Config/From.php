<?php
/**
 * Orders synchronization from date
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Block_Adminhtml_System_Config_From extends Mage_Adminhtml_Block_System_Config_Form_Field {
	
	/**
	 * Generate field HTML
	 * 
	 * @param Varien_Data_Form_Element_Abstract $element
	 * @see Mage_Adminhtml_Block_System_Config_Form_Field::render()
	 */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {    	
    	if(!$element->getValue() && !$element->hasChildren() || !$element->getValue()) {
    		$element->setValue(0);
    	}
    	
    	$html[] = '<tr><td colspan="3" class="value"><div style="padding: 20px 20px 0 20px; border:1px solid #ddd; background: #E7EFEF; height: 90px;" id="' . $element->getHtmlId() . '">';
    	
    	// Manage label
    	$html[] = '<div style="float: left">' . Mage::helper('netevensync')->__('Get orders created and updated since') .'</div>';
    	
    	// Manage datepicker
    	$locale = Mage::app()->getLocale();
    	$date = new Varien_Data_Form_Element_Date;
    	$format = $locale->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT);
    	try {
    		$zendDate = new Zend_Date($element->getValue(), $format, $locale->getLocaleCode());
    	}
    	catch(Exception $e) {
    		// Update order from config if empty
    		$zendDate = $locale->storeDate();
    	}
    	
    	$data = array(
			'name'		=> $element->getName(),
			'html_id'	=> $element->getId() . '_date',
			'image'		=> $this->getSkinUrl('images/grid-cal.gif'),
    	);
    	
    	$date->setData($data);
    	$date->setValue($element->getValue(), $format);
    	$date->setFormat($format);
    	$date->setForm($element->getForm());

    	$dateHtml = $date->getElementHtml();
    	
    	$html[] = '<div style="float: right">' . $dateHtml . '</div>';
    	
    	// Manage button
		$url = $this->getUrl('adminhtml/netevensync/runProcess', array(
				'mode' => Neteven_NetevenSync_Model_Config::NETEVENSYNC_EXPORT_FULL,
				'type' => Neteven_NetevenSync_Model_Config::NETEVENSYNC_PROCESS_ORDER_CODE,
				'from' => $zendDate->getTimestamp(),
				)
		);
    	
    	$buttonHtml = $this->getLayout()->createBlock('adminhtml/widget_button')
	    	->setType('button')
	    	->setLabel($this->__('Synchronize Again'))
	    	->setOnClick("window.open('$url')")
	    	->setId($element->getId() . '_button')
	    	->toHtml();
    	
    	$html[] = '<div style="clear: both; float: right; margin-top: 20px; text-align: right;">' . $buttonHtml . '<p class="note" style="float: right; width: 100%; background: none;"><span>' . Mage::helper('netevensync')->__('Please save configuration before launching synchronization') . '</span></p></div>';
    	
    	// Close tags and generate tr
    	$html[] = '</div></td></tr>';
    	
    	// Add additional JS
    	$html[] = $this->_getAdditionalJs($element);
    	
		return implode('', $html);
    }
    
    protected function _getAdditionalJs($element) {
    	return "<script type=\"text/javascript\">
    		$('" . $element->getId() . "_date').observe('change', function() { $('" . $element->getId() . "_button').addClassName('disabled').disable(); });
    		if($('" . $element->getId() . "_date').getValue() == '') { $('" . $element->getId() . "_button').addClassName('disabled').disable(); }
    	</script>";
    }
}

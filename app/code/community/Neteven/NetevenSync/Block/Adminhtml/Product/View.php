<?php
/**
 * Main view for Neteven Selection management
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Block_Adminhtml_Product_View extends Mage_Adminhtml_Block_Widget_Form_Container {
	
	/**
	 * Initialize form
	 */
	public function __construct() {
		$this->_blockGroup = 'netevensync';
		$this->_controller = 'adminhtml_product';
		$this->_mode = 'view';
		
		parent::__construct();
		$this->_removeButton('back')
			->removeButton('save')
			->removeButton('reset')
			;
		
		// We must recreate reset button in order to make sure onclick leads to correct url
		$this->_addButton('reset', array(
				'label'     => Mage::helper('adminhtml')->__('Reset'),
				'onclick'   => 'setLocation(\'' . $this->getUrl('*/*/*') . '\')',
		));
	}
	
	/**
	 * Getter for form header text
	 */
	public function getHeaderText() {
		return Mage::helper('netevensync')->__('Neteven Selection');
	}
	
	/**
	 * Get header CSS class
	 *
	 * @return string
	 */
	public function getHeaderCssClass()
	{
		$headerCss = parent::getHeaderCssClass();
		return $headerCss . ' head-products'; // Add catalog products icon which is nice for our purpose
	}
}
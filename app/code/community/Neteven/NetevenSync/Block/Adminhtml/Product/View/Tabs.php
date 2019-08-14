<?php
/**
 * Tabs for Neteven Selection management
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Block_Adminhtml_Product_View_Tabs extends Mage_Adminhtml_Block_Widget_Tabs {
	
	/**
	 * Initialize form
	 */
	public function __construct() {
		parent::__construct();
		$this->setId('netevensync_product_view_tabs');
		$this->setDestElementId('edit_form');
	}
	
	/**
	 * Prepare layout to add tabs
	 */
	protected function _prepareLayout() {		
		$this->addTab('netevensync_product_view_exported', array(
				'label'		=> Mage::helper('netevensync')->__('Exported Products'),
				'url'		=> $this->getUrl('*/*/productExported', array('_current' => true)),
				'class'		=> 'ajax',
		));
		
		$this->addTab('netevensync_product_view_available', array(
				'label'		=> Mage::helper('netevensync')->__('Available Products'),
				'url'		=> $this->getUrl('*/*/productAvailable', array('_current' => true)),
				'class'		=> 'ajax',
		));
		
		return parent::_prepareLayout();
	}
}
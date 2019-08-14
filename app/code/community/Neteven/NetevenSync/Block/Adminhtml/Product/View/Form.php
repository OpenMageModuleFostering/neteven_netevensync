<?php
/**
 * Form for Neteven Selection management
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Block_Adminhtml_Product_View_Form extends Mage_Adminhtml_Block_Widget_Form {
	
	public function __construct() {
		parent::__construct();
		$this->setId('netevensync_product_view_form');
		$this->setTitle(Mage::helper('netevensync')->__('Neteven Selection'));
	}
	
	protected function _prepareForm() {
		$form = new Varien_Data_Form(array('id' => 'edit_form', 'action' => '', 'method' => 'post'));
		$form->setUseContainer(true);
		$this->setForm($form);
		return parent::_prepareForm();
	}
}
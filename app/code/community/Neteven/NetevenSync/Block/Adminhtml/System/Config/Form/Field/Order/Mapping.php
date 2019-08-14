<?php

/**
 * This file is part of Neteven_NetevenSync for Magento.
 *
 * @license All rights reserved
 * @author Jacques Bodin-Hullin <j.bodinhullin@monsieurbiz.com> <@jacquesbh>
 * @category Neteven
 * @package Neteven_NetevenSync
 * @copyright Copyright (c) 2015 Neteven (http://www.neteven.com/)
 */

/**
 * Adminhtml_System_Config_Form_Field_Order_Mapping Block
 * @package Neteven_NetevenSync
 */
class Neteven_NetevenSync_Block_Adminhtml_System_Config_Form_Field_Order_Mapping extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{

    public function __construct()
    {
        // Prepare Marketplace select
        $pdmSelect = Mage::app()->getLayout()->createBlock('netevensync/adminhtml_system_config_form_field_order_mapping_select');
        $pdmList = Mage::getSingleton('netevensync/config')->getMarketplacesAsOptionArray();
        $pdmSelect
            ->setOptions(array('empty' => array(
                'value' => '',
                'label' => Mage::helper('adminhtml')->__('--Please Select--'),
            )) + $pdmList)
            ->setClass('js-neteven-marketplace')
        ;

        // Add column wth the prepared select as renderer
        $this->addColumn('pdm', array(
            'label'    => Mage::helper('netevensync')->__('Marketplace'),
            'style'    => 'width:120px',
            'renderer' => $pdmSelect
        ));

        // Prepare countries select
        $countrySelect = Mage::app()->getLayout()->createBlock('netevensync/adminhtml_system_config_form_field_order_mapping_select');
        $countryList = Mage::getSingleton('netevensync/adminhtml_system_config_source_order_pdm')->toOptionArray();
        $countrySelect
            ->setOptions($countryList)
            ->setClass('js-neteven-country')
        ;

        // Add column wth the prepared select as renderer
        $this->addColumn('country', array(
            'label'    => Mage::helper('adminhtml')->__('Country'),
            'style'    => 'width:120px',
            'renderer' => $countrySelect,
        ));

        $this->_addAfter       = false;
        $this->_addButtonLabel = Mage::helper('adminhtml')->__('Add Marketplace');

        // Add JS
        $jsBlock = Mage::app()->getLayout()->getBlock('js');
        $js = <<<JS
Event.observe(document, 'dom:loaded', function () {
    // update selects
    $$('select.js-neteven-marketplace, select.js-neteven-country').each(function (elmt) {
        var val = elmt.readAttribute('data-template-value');
        elmt.value = val;
    });
});
JS;
        $jsBlock->append(Mage::app()->getLayout()->createBlock('core/text')->setText('<script>' . $js . '</script>'));

        parent::__construct();
    }

}

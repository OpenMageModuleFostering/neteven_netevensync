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
class Neteven_NetevenSync_Model_Adminhtml_System_Config_Source_Attribute_Skufamily extends Neteven_NetevenSync_Model_Adminhtml_System_Config_Source_Attribute {

    /**
     * Get options with empty and automatique
     *
     * @return array $options
     */
    public function toSelect() {
        $options[''] = Mage::helper('adminhtml')->__('--Please Select--');
        $options[Neteven_NetevenSync_Model_Config::INVENTORY_SKUFAMILY_AUTOMATIC_KEY] = Mage::helper('netevensync')->__('[ Automatic (parent configurable) ]');
        $optionsSrc = $this->toOptionArray();

        foreach($optionsSrc as $option) {
            $options[$option['value']] = $option['label'];
        }

        return $options;
    }
}
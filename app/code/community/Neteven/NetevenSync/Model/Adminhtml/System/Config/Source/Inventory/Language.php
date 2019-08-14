<?php
/**
 * Options for Neteven inventory languages
 *
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author Hervé Guétin <@herveguetin> <herve.guetin@agence-soon.fr>
 * @category Neteven
 * @package Neteven_NetevenSync
 * @copyright Copyright (c) 2013 Agence Soon (http://www.agence-soon.fr)
 */
class Neteven_NetevenSync_Model_Adminhtml_System_Config_Source_Inventory_Language {

    /**
     * Options getter
     *
     * @return array $options
     */
    public function toOptionArray() {
        $inventoryLanguages = Mage::getSingleton('netevensync/config')->getInventoryLanguages();
        $locales  = Mage::app()->getLocale()->getTranslatedOptionLocales();
        $options[] = array('value' => '', 'label' => Mage::helper('adminhtml')->__('-- Please Select --'));

        foreach($locales as $locale) {
            $valueArr = explode('_', $locale['value']);
            $value = $valueArr[0];
            if(in_array($value, $inventoryLanguages)) {
                $labelArr = explode(' (', $locale['label']);
                $label = $labelArr[0];
                $options[$value] = array('value' => $value, 'label' => $label);
            }
        }

        return $options;
    }
}
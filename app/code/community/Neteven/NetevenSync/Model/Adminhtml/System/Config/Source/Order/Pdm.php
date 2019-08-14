<?php
/**
 * Options for Neteven Countries <--> Magento Store ID mapping
 *
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author Hervé Guétin <@herveguetin> <herve.guetin@agence-soon.fr>
 * @category Neteven
 * @package Neteven_NetevenSync
 * @copyright Copyright (c) 2013 Agence Soon (http://www.agence-soon.fr)
 */

class Neteven_NetevenSync_Model_Adminhtml_System_Config_Source_Order_Pdm
{

    /**
     * Options getter
     *
     * @return array $options
     */
    public function toOptionArray()
    {
        $options[] = array(
            'value' => '',
            'label' => Mage::helper('adminhtml')->__('-- Please Select --'),
        );

        $marketplacesCountries = array_unique(Mage::getSingleton('netevensync/config')->getMarketplacesCountries());
        $countries             = Mage::getResourceModel('directory/country_collection')->loadData()->toOptionArray(false);

        foreach ($countries as $country) {
            if (in_array(strtolower($country['value']), $marketplacesCountries)) {
                $options[] = array('value' => strtolower($country['value']), 'label' => $country['label']);
            }
        }

        return $options;
    }

}

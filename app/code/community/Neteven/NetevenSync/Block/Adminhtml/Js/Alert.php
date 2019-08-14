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
 * Adminhtml_Js_Alert Block
 * @package Neteven_NetevenSync
 */
class Neteven_NetevenSync_Block_Adminhtml_Js_Alert extends Mage_Adminhtml_Block_Template
{

// Neteven Tag NEW_CONST

// Neteven Tag NEW_VAR

    /**
     * Is the store/marketplace/country mapping misconfigured?
     * @return bool
     */
    public function isMarketplaceMappingMisconfigured()
    {
        // Check config by store
        $stores = Mage::app()->getStores();
        $marketplaces = array();
        foreach ($stores as $store) {
            $config = Mage::getStoreConfig('netevensync/order/pdm_mapping', $store);
            if (null !== $config) {
                $mapping = unserialize($config);
                foreach ($mapping as $map) {
                    if (isset($marketplaces[$map['pdm']])) {
                        return true;
                    }
                    $marketplaces[$map['pdm']] = true;
                }
            }
        }

        return false; // Well configured
    }

// Neteven Tag NEW_METHOD

}
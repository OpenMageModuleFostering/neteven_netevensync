<?php

/**
 * Observer
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Observer
{

    /**
     * Register increment based on its process type
     *
     * @param Varien_Event_Observer $observer
     */
    public function registerIncrement(Varien_Event_Observer $observer)
    {
        $args   = Mage::helper('netevensync')->getObserverArgs($observer, get_class($this), __FUNCTION__);
        $object = $observer->getEvent()->getDataObject();

        /**
         * Manage events that lead to object deletion on Neteven platform
         */
        $deleteableEvents = array(
            'catalog_product_delete_after',
        );

        if (in_array($observer->getEvent()->getName(), $deleteableEvents)) {
            $object->setToDelete(true);
        }

        Mage::getModel('netevensync/process_' . $args->getProcessType())->registerIncrement($object, true);
    }

    /**
     * Register increment for product attribute mass update
     *
     * @param Varien_Event_Observer $observer
     */
    public function registerMultiIncrement(Varien_Event_Observer $observer)
    {
        $attributes = $observer->getAttributesData();
        $productIds = $observer->getProductIds();

        foreach ($productIds as $productId) {
            $object = new Varien_Object();
            $object->setId($productId);

            if (isset($attributes['status'])) {
                $object->setStatus($attributes['status']);
            }

            Mage::getModel('netevensync/process_inventory')->registerIncrement($object, true);
        }
    }

    /**
     * Add notice if export Neteven Selection has changed
     *
     * @param Varien_Event_Observer $observer
     */
    public function addNoticeConfigChange(Varien_Event_Observer $observer)
    {
        $config         = $observer->getObject()->getData();
        $flagForceStock = false;

        if (isset($config['section']) && $config['section'] == 'netevensync') {
            $changedConfig = array();
            $groupsToCheck = array(
                'inventory' => Mage::helper('netevensync')->__('Inventory Synchronization'),
                'stock'     => Mage::helper('netevensync')->__('Stocks Synchronization'),
            );

            foreach ($groupsToCheck as $code => $label) {
                $currentConfig = Mage::getStoreConfig('netevensync/' . $code . '/selected');
                if (!isset($config['groups'][$code]['fields']['selected']['value'])) {
                    continue;
                }
                $newConfig = $config['groups'][$code]['fields']['selected']['value'];
                if ($currentConfig != $newConfig) {
                    $changedConfig[$code] = $label;
                    if ($newConfig == 1) {
                        $flagForceStock = true;
                    }
                }
            }

            if (count($changedConfig) > 0) {
                Mage::getSingleton('adminhtml/session')->addNotice(Mage::helper('netevensync')->__('A full Neteven synchronization may be required for the following: %s.', implode(', ', $changedConfig)));
            }

            // Save config for forcing export of not-in-selection products as out-of-stock
            $path   = 'netevensync/force_stock';
            $config = Mage::getModel('core/config_data')->getCollection()
                    ->addFieldToFilter('path', $path)
                    ->getFirstItem();

            $data = array(
                'path'     => $path,
                'scope'    => 'default',
                'scope_id' => 0,
                'value'    => ($flagForceStock) ? 1 : 0,
            );

            $config->setData($data);
            $config->save();
        }
    }

    /**
     * Add Neteven specific info in payment info block
     *
     * @param Varien_Event_Observer $observer
     */
    public function enrichPaymentInfoBlock(Varien_Event_Observer $observer)
    {
        $paymentCode = $observer->getPayment()->getMethodInstance()->getCode();

        // If we're not dealing with neteven payment type, get out.
        if ($paymentCode != 'neteven') {
            return;
        }

        $netevenOrderLink = Mage::getModel('netevensync/process_order_link')
                ->loadByMagentoOrderId($observer->getPayment()->getParentId());

        $transport = $observer->getTransport();
        $transport->setData('Marketplace', $netevenOrderLink->getNetevenMarketPlaceName());
    }

}

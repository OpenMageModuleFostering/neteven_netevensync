<?php

/**
 * Stock process model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Process_Stock extends Neteven_NetevenSync_Model_Process_Abstract
{

    protected $_processType = Neteven_NetevenSync_Model_Config::NETEVENSYNC_PROCESS_STOCK_CODE;

    /**
     * Retrieve collection for export
     *
     * @param string $mode
     * @return Mage_Core_Model_Resource_Db_Collection_Abstract
     */
    public function getExportCollection($mode)
    {

        if (Mage::getStoreConfigFlag('netevensync/stock/selected')) {
            Mage::getModel('netevensync/process_inventory')->forceOutOfStockItems();
        }

        $collection = Mage::getModel('cataloginventory/stock')->getItemCollection();

        /**
         * Add product type filter
         */
        $collection->addFieldToFilter('type_id', array(array('in' => Mage::getSingleton('netevensync/config')->getAvailableProductTypes()), array('null' => 1)));

        /**
         * Filter by Neteven Selection depending on config
         */
        if (Mage::getStoreConfigFlag('netevensync/stock/selected')) {
            $collection->getSelect()->joinRight(
                    array('netevensync_product' => $collection->getTable('netevensync/product')), 'netevensync_product.product_id = main_table.product_id', array()
            );
        }

        /**
         * Add items to force as out of stock
         *
         * @TODO Optimize with union in order to avoid catalog collection load
         */
        if (Mage::getStoreConfigFlag('netevensync/stock/selected')) {
            $inventoryCollection = Mage::getModel('netevensync/process_inventory')->getCollection()->addFieldToFilter('to_delete', '1');

            if ($inventoryCollection->count()) {
                $inventoryCollection    = $inventoryCollection->toArray(array('product_id'));
                $inventoryCollectionIds = array();
                foreach ($inventoryCollection['items'] as $item) {
                    $inventoryCollectionIds[$item['product_id']] = $item['product_id'];
                }

                $stockCollection    = $collection->toArray(array('product_id'));
                $stockCollectionIds = array();
                foreach ($stockCollection['items'] as $item) {
                    $stockCollectionIds[$item['product_id']] = $item['product_id'];
                }
                $allIds = array_merge($inventoryCollectionIds, $stockCollectionIds);
                $allIds = array_unique($allIds);

                $collection = Mage::getModel('cataloginventory/stock')->getItemCollection()
                        ->addFieldToFilter('product_id', array('in' => $allIds));
            }
        }

        /**
         * Add SKU field
         */
        $collection->getSelect()->columns('sku', 'cp_table'); // 'cp_table' alias is already present in $collection->getSelect()

        /**
         * Add "real" 'available_qty' column with value depending on product config:
         * - if use config for min qty is checked => available_qty = product qty - config min qty
         * - if use config for min qty is *not* checked => available_qty = product qty - product min qty
         */
        $configMinValue = Mage::getStoreConfig('cataloginventory/item_options/min_qty');
        $collection->getSelect()->columns(array('available_qty' => new Zend_Db_Expr("IF(main_table.use_config_min_qty > 0, (main_table.qty) - {$configMinValue}, (main_table.qty) - (main_table.min_qty))")));

        return $collection;
    }

    /**
     * Prepare item for export
     *
     * @param mixed $item
     * @return mixed $preparedItem
     */
    public function prepareExportItem($item)
    {
        $preparedItem = false;

        // Do not export items with available_qty to 0
        if (!Mage::getStoreConfigFlag('netevensync/stock/stock') && $item->getData('available_qty') == 0 && !$item->getToDelete()) {
            return false;
        }

        if ($sku = Mage::helper('netevensync')->checkSku($item->getData('sku'), $this->_processType)) {

            if (Mage::getStoreConfigFlag('netevensync/stock/selected')) {
                $productId     = $item->getProductId();
                $inventoryItem = Mage::getModel('netevensync/process_inventory')->loadByProductId($productId);

                if ($inventoryItem->getToDelete()) {
                    $item->setToDelete(true);
                }
            }

            if ($item->getToDelete()) {
                $quantity = '0.0000';
            } else {
                $quantity = $item->getData('available_qty');
            }

            $preparedItem = array(
                'SKU'      => $sku,
                'Quantity' => $quantity,
            );

            // Add fields that are price related
            if (Mage::getStoreConfig('netevensync/stock/sync_prices')) {
                $priceSpecificFields = Mage::getSingleton('netevensync/config')->getInventoryPriceSpecificFields();
                if (is_array($priceSpecificFields) && count($priceSpecificFields) > 0) {
                    $product = Mage::getModel('catalog/product')->load($item->getProductId());
                    foreach ($priceSpecificFields as $netevenField => $attributeCode) {
                        $preparedItem = Mage::getSingleton('netevensync/process_inventory')->getItemData($product, $preparedItem, $netevenField, $attributeCode);
                    }
                }
            }
        }
        return $preparedItem;
    }

    /**
     * Process items for export
     *
     * @param array $items
     * @return array $result
     */
    public function processExportItems($items)
    {
        $soapClient = Mage::getModel('netevensync/soap'); // We must instantiate a new SOAP for each call because authentication must be renewed
        $result     = $soapClient->processPostItems($items, $this->_processType);
        return $result;
    }

    /**
     * Finish export
     *
     * @param string $mode
     * @return bool $success
     */
    public function finishExportProcess($mode)
    {
        return Mage::getModel('netevensync/process_inventory')->finishExportProcess($mode);
    }

}

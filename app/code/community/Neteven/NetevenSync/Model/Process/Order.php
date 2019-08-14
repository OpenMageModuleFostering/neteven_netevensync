<?php

/**
 * Order process model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Process_Order extends Neteven_NetevenSync_Model_Process_Abstract
{

    protected $_processType = Neteven_NetevenSync_Model_Config::NETEVENSYNC_PROCESS_ORDER_CODE;

    /**
     * Order convertor
     * @var Neteven_NetevenSync_Model_Process_Order_Convertor $convertor
     */
    protected $_convertor;

    /**
     * Retrieve collection for import
     *
     * @param string $mode
     * @return Mage_Core_Model_Resource_Db_Collection_Abstract
     */
    public function getImportCollection($mode)
    {

        if (!$this->checkConfig()) {
            return Mage::helper('netevensync')->__('Please map orders statuses and chose carrier. Process aborted.');
        }

        $collection = $this->getTempItems();
        if (!$collection->count()) {
            Mage::getModel('netevensync/soap')->requestOrders();
            $collection = $this->getTempItems();
        }

        return $collection;
    }

    /**
     * Retrieve collection for export
     *
     * @param string $mode
     * @return Mage_Core_Model_Resource_Db_Collection_Abstract
     */
    public function getExportCollection($mode)
    {

        if ($mode == Neteven_NetevenSync_Model_Config::NETEVENSYNC_EXPORT_INCREMENTAL) {
            $collection = Mage::getModel('sales/order')->getCollection()
                ->addFieldToFilter('entity_id', array('in' => $this->getIncrementalOrderIds()));

            // Add order link data
            $collection->getSelect()->joinLeft(
                array('link' => $collection->getTable('netevensync/order_link')),
                'link.magento_order_id = main_table.entity_id',
                array('neteven_order_id')
            );

            return $collection;
        }

        return parent::getExportCollection($mode);
    }

    /**
     * Add page limit to collection
     *
     * @param Mage_Core_Model_Resource_Db_Collection_Abstract
     * @param string $dir
     * @param bool $fromAjax
     * @return Mage_Core_Model_Resource_Db_Collection_Abstract
     */
    public function addChunk($collection, $fromAjax, $dir)
    {
        if ($fromAjax && $dir == Neteven_NetevenSync_Model_Config::NETEVENSYNC_DIR_IMPORT) {
            $collection->setPageSize(1); // If we are importing order items, we import them one by one
            return $collection;
        } else {
            return parent::addChunk($collection, $fromAjax, $dir);
        }
    }

    /**
     * Prepare item for import
     *
     * @param mixed $item
     * @return mixed $preparedItem
     */
    public function prepareImportItem($item)
    {
        $data = Zend_Json::decode($item->getNetevenItemData());
        $preparedItem = new Varien_Object();

        foreach ($data as $field => $value) {
            $convertedField = $this->_convertField($field, $value);
            $preparedItem->setData($convertedField['field'], $convertedField['value']);
        }

        $preparedItem = $this->_prepareFromMarketPlace($preparedItem);

        // Init checksum, based on SKU, price + currency
        $preparedItem->setChecksum(hash('sha1', implode('|', array(
            $preparedItem->getSku(),
            $preparedItem->getPrice()->getValue() / $preparedItem->getQuantity(),
            $preparedItem->getPrice()->getCurrencyId(),
        ))));

        return $preparedItem;
    }

    /**
     * Rework $preparedItem for specific Market Places
     *
     * @param Varien_Object $preparedItem
     * @return Varien_Object
     */
    public function _prepareFromMarketPlace($preparedItem)
    {
        // La Redoute (24)
        if ($preparedItem->getMarketPlaceId() == '24') {

            $processor = function ($address, $field) {
                $arr = explode(' ', $address->getData($field));
                $firstname = array_shift($arr);
                $lastname = implode(' ', $arr);
                $address->setFirstName($firstname);
                $address->setLastName($lastname);
                return $address;
            };

            $preparedItem->setBillingAddress($processor($preparedItem->getBillingAddress(), 'first_name'));
            $preparedItem->setShippingAddress($processor($preparedItem->getShippingAddress(), 'last_name'));
        }

        return $preparedItem;
    }

    /**
     * Prepare item for export
     *
     * @param mixed $item
     * @return mixed $preparedItem
     */
    public function prepareExportItem($item)
    {
        $order  = $item;
        $status = $this->getConfig()->getMappedOrderStatus($order->getStatus(), 'magento');

        $preparedItem = array(
            'OrderID' => $item->getNetevenOrderId(),
            'Status'  => $status,
        );

        if ($status == Neteven_NetevenSync_Model_Config::NETEVENSYNC_ORDER_STATUS_SHIPPED && (bool) $order->hasShipments()) {
            // We use only first shipment as Neteven only accepts one shipment per order
            $shipment     = $order->getShipmentsCollection()->getFirstItem();
            $dateShipping = date(DATE_ATOM, strtotime($shipment->getCreatedAt()));

            $preparedItem['DateShipping'] = $dateShipping;

            $tracks = $shipment->getAllTracks();
            if (count($tracks) > 0) {
                $track                          = reset($tracks); // We use only first track as Neteven only accepts one tracking per order
                $preparedItem['TrackingNumber'] = $track->getNumber();
            }
        }

        return $preparedItem;
    }

    /**
     * Process items for import
     *
     * @param Varien_Object $items
     * @return array $result
     */
    public function processImportItems($items)
    {
        /* @var $logger Neteven_NetevenSync_Helper_Logger */
        $logger = Mage::helper('netevensync/logger');
        $result = array('success' => true, 'success_items_count' => count($items));
        foreach ($items as $item) {

            // Init the logger
            $logger
                ->init(sprintf("Import order item [%s]", $item->getData('market_place_order_line_id')))
                ->step("Initial information (item)", $item->getData())
                ->part("Process")
            ;

            if (!$logger->condition("Can import?", $this->_canImport($item))) {
                $logger->end();
                continue;
            }

            $logger->info("Retrieve the process_order_link object using the order_id");
            $orderLink = Mage::getModel('netevensync/process_order_link')->loadByNetevenOrderId($item->getOrderId());
            $logger->step("The order link", $orderLink);

            // If order has already been imported and its status has changed, we update it
            if ($orderLink->getMagentoOrderId()) {
                $logger->info("Magento's order ID found");
                $logger->data(array(
                    "order link order status" => $orderLink->getOrderStatus(),
                    "item status"             => $item->getStatus(),
                ));
                if (!$logger->condition("Same order status between orderLink and item?", $orderLink->getOrderStatus() === $item->getStatus())) {
                    $logger
                        ->up()
                        ->step("Update the order status of the order link")
                        ->result($item->getStatus(), "Order status")
                    ;
                    $orderLink->setOrderStatus($item->getStatus());
                    $orderLink->save();
                    $logger->down();
                }
            }

            // If order has not been imported yet, we create or update quote
            else {
                $logger
                    ->step("Process quote importing")
                    ->up()
                ;
                $result = $this->processImportQuote($orderLink, $item);
                $logger->down();
            }

            // Can Invoice?
            $canInvoice = (int) in_array($orderLink->getOrderStatus(), Mage::getSingleton('netevensync/config')->getInvoiceStatus());
            $logger->step("can invoice?", $canInvoice);

            // Update the link
            $orderLink
                ->setCanInvoice($canInvoice)
                ->setHasBeenProcessed(1)
                ->save()
            ;

            $logger->end();
        }

        return $result;
    }

    /**
     * Make several tests to check if import can be imported
     *
     * @param array $item
     * @return bool
     */
    protected function _canImport($item)
    {
        $logger = Mage::helper('netevensync/logger');

        // Get address data
        // Check if data is empty or equal to a dash because Neteven may sends whether an empty values or a dash...
        $shippingAddress = $item->getShippingAddress();
        $address1 = $shippingAddress->getAddress1();
        if ($address1 == '-') {
            $logger->info("Set address1 to empty string");
            $address1 = '';
        }
        $address2 = $shippingAddress->getAddress2();
        if ($address2 == '-') {
            $logger->info("Set address2 to empty string");
            $address2 = '';
        }
        $pseudo = $item->getShippingAddress()->getPseudo();
        if ($pseudo == '-') {
            $logger->info("Set pseudo to empty string");
            $pseudo = '';
        }

        // Check if shipping address is valid
        if ($address1 == '' && $address2 == '') {
            $logger->info("Address1 and adress2 are empty");
            return false;
        }

        // Check if "pseudo" exists for MarketPlaceId 5
        if ($item->getMarketPlaceId() == '5' && $pseudo == '') {
            $logger->info("Marketplace is 5 and pseudo is empty");
            return false;
        }

        // Last check
        // Check if order status is allowed
        $allowedStatuses = $this->getConfig()->getAllowedNetevenOrderStatesForImport();
        $goodStatus = in_array($item->getStatus(), $allowedStatuses);
        $logger->step("Status allowed", $goodStatus);
        if (!$goodStatus) {
            return false;
        }

        return true;
    }

    /**
     * Process items for export
     *
     * @param array $items
     * @return array $result
     */
    public function processExportItems($items)
    {
        $soapClient = Mage::getModel(
            'netevensync/soap'
        ); // We must instantiate a new SOAP for each call because authentication must be renewed
        $result = $soapClient->processPostOrders($items, $this->_processType);
        return $result;
    }

    /**
     * Finish import
     *
     * @param string $mode
     * @return bool $success
     */
    public function finishImportProcess($mode)
    {
        /* @var $logger Neteven_NetevenSync_Helper_Logger */
        $logger = Mage::helper('netevensync/logger');
        $success = true;

        $this->flushTempItems();

        // Update already imported orders
        $ordersToUpdate = Mage::getModel('netevensync/process_order_link')
            ->getCollection()
            ->addFieldToFilter('magento_order_id', array('notnull' => true))
            ->addFieldToFilter('has_been_processed', 1)
        ;

        $logger->init("Update order [finish import process]");

        if ($ordersToUpdate->count()) {
            foreach ($ordersToUpdate as $orderLink) {
                $logger
                    ->part(sprintf("Update order %d", $orderLink->getMagentoOrderId()))
                ;
                $logger->data("link", $orderLink);
                $order = Mage::getModel('sales/order')->load($orderLink->getMagentoOrderId());
                $logger->step("Load order", $order->getData());
                $logger->up();
                $this->getConvertor()->updateOrder($order, $orderLink->getOrderStatus(), (bool) $orderLink->getCanInvoice());
            }
        }

        $logger->end();

        // Create not yet imported orders
        $ordersToCreate = Mage::getModel('netevensync/process_order_link')
            ->getCollection()
            ->addFieldToFilter('magento_order_id', array('null' => true))
            ->addFieldToFilter('has_been_processed', 1)
        ;

        $logger->init("Create orders");

        if ($logger->condition("How many orders to create?", $ordersToCreate->count())) {
            $logger->info("Loop on orders to create");
            foreach ($ordersToCreate as $orderLink) {
                $logger->part("Create order");
                $logger->data('order link', $orderLink);

                $quote = Mage::getModel('sales/quote');
                $store = Mage::app()->getStore($orderLink->getStoreId());
                $quote->setStore($store)->load($orderLink->getMagentoQuoteId());

                // Retrieve payment from DB and assign it to quote
                $quotePayment = $quote->getPayment();
                $quotePayment->setMethod($this->getConfig()->getMappedPaymentCode($orderLink->getPaymentMethod()));
                $quote->setPayment($quotePayment);

                // Retrieve order status from DB and assign to quote
                $quote->setOrderStatus($orderLink->getOrderStatus());

                // Retrieve Neteven order id from DB and assign to quote
                $quote->setNetevenOrderId($orderLink->getNetevenOrderId());

                // Retrieve Neteven market place order id from DB and assign to quote
                $quote->setNetevenMarketPlaceOrderId($orderLink->getNetevenMarketPlaceOrderId());

                // Retrieve Neteven market place name from DB and assign to quote
                $quote->setNetevenMarketPlaceName($orderLink->getNetevenMarketPlaceName());

                // Can invoice the order?
                $quote->setCanInvoiceOrder((bool) $orderLink->getCanInvoice());

                $order = $this->getConvertor()->createOrder($quote);

                if ($orderId = $order->getId()) {
                    $orderLink->setMagentoOrderId($orderId);
                    $orderLink->save();
                } else {
                    $success = false;
                }
            }
        }

        $logger->end();

        // Bulk update
        $links = Mage::getModel('netevensync/process_order_link')
            ->getCollection()
            ->addFieldToFilter('has_been_processed', 1)
        ;
        foreach ($links as $link) {
            $link
                ->setHasBeenProcessed(null)
                ->save()
            ;
        }

        return $success;
    }

    /**
     * Retrieve order manager singleton
     *
     * @return Neteven_NetevenSync_Model_Process_Order_Convertor
     */
    public function getConvertor()
    {
        if (is_null($this->_convertor)) {
            $this->_convertor = Mage::getSingleton('netevensync/process_order_convertor');
        }
        return $this->_convertor;
    }

    /**
     * Create or update quote
     *
     * @param Soon_NetevenSync_Model_Order_Link $orderLink
     * @param Varien_Object $item
     * @return array
     */
    public function processImportQuote($orderLink, $item)
    {
        /* @var $logger Neteven_NetevenSync_Helper_Logger */
        $logger = Mage::helper('netevensync/logger');

        $result = array('success' => true, 'success_items_count' => 1);

        $logger->info("Instanciate new quote");
        $quote = Mage::getModel('sales/quote');

        if ($magentoQuoteId = $orderLink->getMagentoQuoteId()) {
            $logger->info("Set the quote store in purpose to load the quote");

            $store = $orderLink->getStoreId() ? Mage::app()->getStore($orderLink->getStoreId()) : Mage::app()->getDefaultStoreView();

            $logger->logStore($store);
            Mage::helper('netevensync')->updateStoreConfiguration($store);

            $logger->info("Load the quote");
            $quote
                ->setStore($store)
                ->load($magentoQuoteId)
            ;
        }

        // If quote does not exist in Magento, we create it
        if ($logger->condition("Is the quote a new one?", !$quote->getId())) {
            try {
                $logger->info("Create the quote");
                $quote = $this->getConvertor()->createQuote($item);
            } catch (Exception $e) {
                $logger->exception($e);
                Mage::helper('netevensync')->log($e);
                $result = array('success' => false, 'success_items_count' => 0);
                return $result;
            }

            if (!$logger->condition("Is the quote a real quote?", $quote instanceof Mage_Sales_Model_Quote)) {
                $logger->up()->err("What?")->down();
                $result = array('success' => false, 'success_items_count' => 0);
            } else {
                $logger->startComparison("Update the order link", $orderLink->getData());

                $orderLink
                    ->setNetevenOrderId($item->getOrderId())
                    // Store Neteven marketplace order id in DB for use in finishImportProcess()
                    ->setNetevenMarketPlaceOrderId($item->getMarketPlaceOrderId())
                    // Store Neteven marketplace name in DB for use in finishImportProcess()
                    ->setNetevenMarketPlaceName($item->getMarketPlaceName())
                    ->setNetevenCustomerId($item->getCustomerId())
                    ->setMagentoQuoteId($quote->getId())
                    // Store Neteven payment method in DB for use in finishImportProcess()
                    ->setPaymentMethod($item->getPaymentMethod())
                    // Store Neteven order status in DB for use in finishImportProcess()
                    ->setOrderStatus($item->getStatus())
                    // Store ID Magento
                    ->setStoreId($quote->getStoreId())
                    ->save();

                $logger->endComparison($orderLink->getData());
            }
        } // Otherwise, we try to add new item to quote
        else {
            $logger->info("The quote exists, update it");
            $logger->step("The existing quote", $quote->getData());
            $logger->info("We try to add new item to existing quote");
            if ($logger->condition("Do the quote already have the item?", $this->_quoteHasItem($quote, $item))) {
                $logger->info("Update item qty");
                $this->getConvertor()->updateQuoteItem($item, $quote);
            } else {
                $logger->info("Add item to quote");
                $quote = $this->getConvertor()->addItemToQuote($item, $quote);
            }

            if (!$logger->condition("Is the quote a real one?", $quote instanceof Mage_Sales_Model_Quote)) {
                $logger->up()->err("What?")->down();
                $result = array('success' => false, 'success_items_count' => 0);
            } else {
                $logger->info("Collect quote totals and save");
                $logger->startComparison("Quote", $quote->getData());
                $quote->collectTotals();
                $quote->save();
                $logger->endComparison($quote->getData());
            }
        }

        return $result;
    }

    /**
     * Check if quote already has item
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param Varien_Object $netevenItem
     * @return bool
     */
    protected function _quoteHasItem($quote, $netevenItem)
    {
        /* @var $logger Neteven_NetevenSync_Helper_Logger */
        $logger = Mage::helper('netevensync/logger');

        $logger->up();
        $logger->data("Neteven item checksum", $netevenItem->getChecksum());

        $quoteItems = $quote->getAllItems();
        foreach ($quoteItems as $quoteItem) {
            $logger->info(sprintf("Compare to %s", $quoteItem->getNetevenChecksum()));
            if ($quoteItem->getNetevenChecksum() === $netevenItem->getChecksum()) {
                $logger->down();
                return true;
            }
        }

        $logger->down();
        return false;
    }

    /**
     * Convert field string to correct Varien_Object conventions
     *
     * This may need some improvements...
     *
     * @param $string
     * @return $string
     */
    protected function _convertField($field, $value)
    {
        if (ctype_upper($field)) {
            $field = strtolower($field);
        }
        if (ctype_upper(substr($field, -1, 1))) {
            $stringLength = strlen($field);
            $start = substr($field, 0, $stringLength - 1);
            $end = strtolower(substr($field, -1, 1));
            $field = $start . $end;
        }

        $field = lcfirst($field);

        if ($field === 'totalCostWithVAt') {
            $field = 'totalCostWithVat';
        }

        if (is_array($value)) {
            $newValue = array();
            foreach ($value as $k => $v) {
                $k = $this->_convertField($k, $v);
                if ($k['field'] == '_') {
                    $k['field'] = 'value';
                }
                $newValue[$k['field']] = $v;
            }
            $objValue = new Varien_Object();
            $objValue->setData($newValue);
            $value = $objValue;
        }

        $field = preg_replace_callback(
            '/[A-Z]/',
            create_function(
                '$args',
                'return \'_\' . strtolower($args[0]);'
            ),
            $field
        );

        return array('field' => $field, 'value' => $value);
    }

    /**
     * Check if mapping of order statuses is complete
     *
     * @return bool
     */
    public function checkConfig()
    {
        $success = true;

        $netevenStatuses = $this->getConfig()->getNetevenOrderStatuses();
        $magentoStatuses = Mage::getSingleton('sales/order_config')->getStatuses();

        foreach ($netevenStatuses as $code => $label) {
            $config = Mage::getStoreConfig('netevensync/order_mapping_neteven/' . $code);
            if (!$config || $config == '') {
                $success = false;
            }
        }

        foreach ($magentoStatuses as $code => $label) {
            $config = Mage::getStoreConfig('netevensync/order_mapping_magento/' . $code);
            if (!$config || $config == '') {
                $success = false;
            }
        }

        $carrier = Mage::getStoreConfig('netevensync/shipping/carrier');
        if (!$carrier || $carrier == '') {
            $success = false;
        }

        return $success;
    }

    /**
     * Save SOAP order page to DB
     *
     * @param array $data
     * @return $this
     */
    public function saveSoapOrdersPage($data)
    {

        // Switch to temp resource model
        $this->_init('netevensync/process_order_temp');

        if (!isset($data->GetOrdersResult)) {
            return $this;
        }

        $items = array();
        $srcItems = $data->GetOrdersResult->MarketPlaceOrder;
        if (!is_array($srcItems)) {
            $items[] = $srcItems;
        } else {
            $items = $srcItems;
        }

        foreach ($items as $item) {
            // Make sure there are addresses to not create Magento order with empty address
            if (
                isset($item->BillingAddress->FirstName)
                && isset($item->BillingAddress->LastName)
                && isset($item->ShippingAddress->FirstName)
                && isset($item->ShippingAddress->LastName)
                && $item->BillingAddress->FirstName != 'None'
                && $item->BillingAddress->LastName != 'None'
                && $item->ShippingAddress->FirstName != 'None'
                && $item->ShippingAddress->LastName != 'None'
            ) {
                $itemId = $item->ID;
                $this->setId(null);

                $this->addData($this->getResource()->loadByNetevenItemId($itemId))
                    ->setNetevenItemId($itemId)
                    ->setNetevenItemData(Zend_Json::encode($item))
                    ->save();
            }
        }

        // Switch to regular resource model
        $this->_init('netevensync/process_order');

        return $this;
    }

    /**
     * Retrieve temp order items
     *
     * @return Neteven_NetevenSync_Model_Resource_Order_Temp_Collection
     */
    public function getTempItems()
    {

        // Switch to temp resource model
        $this->_init('netevensync/process_order_temp');

        $tempItems = $this->getCollection();

        // Switch to regular resource model
        $this->_init('netevensync/process_order');

        return $tempItems;
    }

    /**
     * Flush temp order items
     *
     * @return Neteven_NetevenSync_Model_Process_Order
     */
    public function flushTempItems()
    {

        // Switch to temp resource model
        $this->_init('netevensync/process_order_temp');

        $resource = $this->getResource();
        $resource->getReadConnection()->truncateTable($resource->getMainTable());

        // Switch to regular resource model
        $this->_init('netevensync/process_order');

        return $this;
    }

    /**
     * Retrieve incremental Magento order ids
     *
     * @return array
     */
    public function getIncrementalOrderIds()
    {
        $this->getResource()->setIdFieldName('order_id');
        return $this->getCollection()->getAllIds();

    }

    /**
     * Register increment
     *
     * @param Mage_Sales_Model_Order $order
     */
    public function registerIncrement($order)
    {
        if (!$this->checkConfig()) {
            $message = Mage::helper('netevensync')->__(
                'Please map orders statuses and chose carrier. Order %s will not be included in next incremental order update.',
                $order->getIncrementId()
            );
            Mage::helper('netevensync')->log($message, $this->_processType);
            return $this;
        }

        $orderLink = Mage::getModel('netevensync/process_order_link')->loadByMagentoOrderId($order->getId());

        if ($orderLink->getMagentoOrderId() && !$order->getIsFromImport()
        ) { // @see Neteven_NetevenSync_Model_Process_Order_Convertor::createOrder()
            $this->loadByOrderId($order->getId());
            $this->setOrderId($order->getId());
            $this->save();

            $orderLink->setOrderStatus($this->getConfig()->getMappedOrderStatus($order->getStatus(), 'magento'));
            $orderLink->save();
        }
    }

    /**
     * Load by order id
     *
     * @param int $orderId
     * @return Neteven_NetevenSync_Model_Process_Order
     */
    public function loadByOrderId($orderId)
    {
        $this->addData($this->getResource()->loadByOrderId($orderId));
        return $this;
    }
}
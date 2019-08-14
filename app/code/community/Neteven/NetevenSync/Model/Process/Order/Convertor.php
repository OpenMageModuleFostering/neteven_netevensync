<?php

/**
 * Order / quote converter model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Process_Order_Convertor
{

    /**
     * Countries collection
     *
     * @var array
     */
    protected $_countryCollection;

    /**
     * NetevenSync config
     *
     * @var Neteven_NetevenSync_Model_Config
     */
    protected $_config;

    /**
     * Can order be invoiced?
     *
     * @var bool
     */
    protected $_canInvoice = false;

    /**
     * Can order be shipped?
     *
     * @var bool
     */
    protected $_canShip = false;

    /**
     * Can order be canceled?
     *
     * @var bool
     */
    protected $_canCancel = false;

    /**
     * Can order be refunded?
     *
     * @var bool
     */
    protected $_canRefund = false;

    /**
     * Does order have invoices?
     *
     * @var bool
     */
    protected $_hasInvoices = false;

    /**
     * Does order have shipments?
     *
     * @var bool
     */
    protected $_hasShipments = false;

    /**
     * Is order canceled?
     *
     * @var bool
     */
    protected $_isCanceled = false;

    /**
     * Is order refunded?
     *
     * @var bool
     */
    protected $_isRefunded = false;

    /**
     * Retrieve config singleton
     *
     * @return Neteven_NetevenSync_Model_Config
     */
    public function getConfig()
    {
        if (is_null($this->_config)) {
            $this->_config = Mage::getSingleton('netevensync/config');
        }
        return $this->_config;
    }

    /**
     * Create quote
     *
     * @param Varien_Object $netevenItem
     * @return Mage_Sales_Model_Quote
     */
    public function createQuote($netevenItem)
    {
        /* @var $logger Neteven_NetevenSync_Helper_Logger */
        $logger = Mage::helper('netevensync/logger');
        $logger->step("Create quote using the convertor")->up();

        $billingAddress  = $netevenItem->getBillingAddress();
        $shippingAddress = $netevenItem->getShippingAddress();
        $addresses       = array('billingAddress' => $billingAddress, 'shippingAddress' => $shippingAddress);

        // Find store for quote
        $storeId = $this->getConfig()->getStoreIdForMarketplace($netevenItem->getMarketPlaceId());
        $logger->data(array(
            "marketplace_id" => $netevenItem->getMarketPlaceId(),
            "store_id"       => $storeId,
        ));
        if ($logger->condition("Has store ID", (bool) $storeId)) {
            $store = Mage::getModel('core/store')->load($storeId);
        } else {
            $logger->info("Use default store view");
            $store = Mage::app()->getDefaultStoreView();
        }
        $logger->logStore($store);

        // Update store configuration
        Mage::helper('netevensync')->updateStoreConfiguration($store);

        // Create quote and add item
        $quote = Mage::getModel('sales/quote');
        $quote->setIsMultiShipping(false)
                ->setCheckoutMethod(Mage_Checkout_Model_Type_Onepage::METHOD_GUEST)
                ->setCustomerId(null)
                ->setCustomerEmail($billingAddress->getEmail())
                ->setCustomerIsGuest(true)
                ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID)
                ->setStore($store)
        ;

        $quote = $this->addItemToQuote($netevenItem, $quote);

        if (!$quote) {
            $logger->err("Quote not returned by the addItemToQuote call");
            $logger->up()->data("Returned instead", $quote)->down();
            return false;
        }

        // Retrieve Neteven address fields and concatenate for addresses objects
        $addressForm = Mage::getModel('customer/form');
        $addressForm->setFormCode('customer_address_edit')
                ->setEntityType('customer_address')
        ;

        $logger->info("Process the addresses");
        foreach ($addresses as $name => $address) {
            $logger
                ->step("Process $name address")
                ->up()
            ;
            foreach ($addressForm->getAttributes() as $attribute) {
                $mappedAttributeCode = $this->getConfig()->getMappedAddressAttributeCode($attribute->getAttributeCode());
                $value               = array();
                if (is_array($mappedAttributeCode)) {
                    foreach ($mappedAttributeCode as $attributeCode) {
                        if ($shippingAddress->getData($attributeCode)) {
                            $value[] = $address->getData($attributeCode);
                        }
                    }
                } else {
                    if ($shippingAddress->getData($mappedAttributeCode)) {
                        $value[] = $address->getData($mappedAttributeCode);
                    }
                }

                // Use Neteven's mobile field if telephone is empty
                if ($attribute->getAttributeCode() == 'telephone' && (!$address->getPhone() || $address->getPhone() == '')) {
                    $value[] = $address->getMobile();
                }

                // Manage country based on MarketPlaceId
                if (empty($value) && $attribute->getAttributeCode() == 'country_id') {
                    $value[] = $this->getConfig()->getAddressCountryForMarketPlaceId($netevenItem->getMarketPlaceId());
                }

                if (count($value) > 0) {

                    $value = ($attribute->getAttributeCode() == 'street') ? implode("\n", $value) : implode(' ', $value);

                    // Retrieve country code
                    if ($attribute->getAttributeCode() == 'country_id') {
                        $value = $this->_getCountryId($value);
                    }

                    $logger->data($attribute->getAttributeCode(), $value);
                    $method = 'get' . ucfirst($name);
                    $quote->$method()->setData($attribute->getAttributeCode(), $value);
                }
            }
            $logger->down();
        }

        // Force shipping price and method
        Mage::getSingleton('checkout/session')
                ->setNetevenShippingPrice($netevenItem->getOrderShippingCost()->getValue())
                ->setIsFromNeteven(true)
        ;

        $quote->getShippingAddress()
                ->setShippingMethod('neteven_dynamic')
                ->setCollectShippingRates(true)
                ->collectShippingRates();

        // Update quote with new data
        $logger->info("Collect quote totals and save");
        $quote->collectTotals();
        $quote->save();

        $logger
            ->info("Returns the quote")
            ->down()
        ;

        return $quote;
    }

    /**
     * Update item in quote
     * @param Varien_Object $netevenItem
     * @param Mage_Sales_Mode_Quote
     * @return Neteven_NetevenSync_Model_Process_Order_Convertor
     */
    public function updateQuoteItem($netevenItem, $quote)
    {
        /* @var $logger Neteven_NetevenSync_Helper_Logger */
        $logger = Mage::helper('netevensync/logger');
        $logger->step("Update quote item");

        // Find quote item
        $quoteItems = $quote->getAllItems();
        $found = false;
        foreach ($quoteItems as $quoteItem) {
            if ($quoteItem->getNetevenChecksum() == $netevenItem->getChecksum()) {
                $logger->info("Quote item found using checksum");
                $found = $quoteItem;
                break;
            }
        }

        // Item found
        if ($found !== false) {
            $logger->data("old quantity", $found->getQty());
            $found->setQty($found->getQty() + $netevenItem->getQuantity());
            $logger->data("new quantity", $found->getQty());
        } else {
            $logger->err("Quote item not found");
        }

        $logger->down();

        return $this;
    }

    /**
     * Add item to quote
     *
     * @param Varien_Object $netevenItem
     * @param Mage_Sales_Mode_Quote
     * @return Mage_Sales_Mode_Quote
     */
    public function addItemToQuote($netevenItem, $quote)
    {
        /* @var $logger Neteven_NetevenSync_Helper_Logger */
        $logger = Mage::helper('netevensync/logger');
        $logger->step("Add item to quote")->up();

        $quote->setIsSuperMode(true); // to avoid qty check

        $logger->info("Load the product");
        $logger->up()->data("SKU", $netevenItem->getSku())->down();
        $productModel = Mage::getModel('catalog/product');
        $product      = $productModel->load($productModel->getIdBySku($netevenItem->getSku()));

        // Check that product exists in catalog
        if (!$product->getId()) {
            $logger->err("Product not found?");
            $logger->step("Neteven item", $netevenItem->getData());
            $message = Mage::helper('netevensync')->__('Imported order item does not exist in catalog. Item ID: %s, Order ID: %s, Sku: %s', $netevenItem->getId(), $netevenItem->getOrderId(), $netevenItem->getSku());
            Mage::helper('netevensync')->log($message, Neteven_NetevenSync_Model_Config::NETEVENSYNC_PROCESS_ORDER_CODE);
            return false;
        }

        // Check that product can be added to quote based on its type
        $logger->data("Product type ID", $product->getTypeId());
        $logger->data("Available types in config", $this->getConfig()->getAvailableProductTypes());
        if (!$logger->condition("Is the product type available?", in_array($product->getTypeId(), $this->getConfig()->getAvailableProductTypes()))) {
            $logger->err("Type unavailable, see values above");
            $message = Mage::helper('netevensync')->__('Imported order item is of type "%s" which is not allowed for orders import. Item ID: %s, Order ID: %s, Sku: %s', $product->getTypeId(), $netevenItem->getId(), $netevenItem->getOrderId(), $netevenItem->getSku());
            Mage::helper('netevensync')->log($message, Neteven_NetevenSync_Model_Config::NETEVENSYNC_PROCESS_ORDER_CODE);
            return false;
        }

        // Create quote item
        $logger->info("Create the quote item");
        $quoteItem = Mage::getModel('sales/quote_item');

        // Force price to Neteven price (price incl VAT)
        $logger->info("Divide neteven price by neteven quantity");
        $price = $netevenItem->getPrice()->getValue() / $netevenItem->getQuantity();

        $logger->startComparison("Fill the quote item", $quoteItem->getData());
        $quoteItem
            ->setProduct($product)
            ->setCustomPrice($price)
            ->setOriginalCustomPrice($price)
            ->setQuote($quote)
            ->setQty($netevenItem->getQuantity())
            ->setNetevenChecksum($netevenItem->getChecksum())
        ;
        $logger->endComparison($quoteItem->getData());

        $logger->info("Call addItem on the quote");
        $quote->addItem($quoteItem);

        $logger
            ->info("Returns the quote")
            ->down()
        ;
        return $quote;
    }

    /**
     * Create order
     *
     * @param Mage_Sales_Model_Quote
     * @return Mage_Sales_Model_Order
     */
    public function createOrder($quote)
    {
        /* @var $logger Neteven_NetevenSync_Helper_Logger */
        $logger = Mage::helper('netevensync/logger');
        $logger->step("Create order using quote");
        $logger->up();

        try {
            // Convert quote to order...
            $items = $quote->getAllItems();
            $quote->reserveOrderId();
            $logger->result($quote->getReservedOrderId(), "Reserved order ID");

            $convertQuote = Mage::getSingleton('sales/convert_quote');

            /* @var $order Mage_Sales_Model_Order */
            $order        = $convertQuote->addressToOrder($quote->getShippingAddress());

            $order->setBillingAddress($convertQuote->addressToOrderAddress($quote->getBillingAddress()));
            $order->setShippingAddress($convertQuote->addressToOrderAddress($quote->getShippingAddress()));
            $order->setPayment($convertQuote->paymentToOrderPayment($quote->getPayment()));

            foreach ($items as $item) {
                $orderItem = $convertQuote->itemToOrderItem($item);
                if ($item->getParentItem()) {
                    $orderItem->setParentItem($order->getItemByQuoteItemId($item->getParentItem()->getId()));
                }
                $order->addItem($orderItem);
            }

            // ... and place order
            $order->place();

            // Update order state and status, add a comment to order history
            $status  = $this->getConfig()->getMappedOrderStatus($quote->getOrderStatus());
            $state   = $this->getConfig()->getMappedOrderState($quote->getOrderStatus());
            $logger->step("Statuses", array(
                'status' => $status,
                'state' => $state,
            ));
            $comment = Mage::helper('netevensync')->__('Order %s imported from Neteven', $quote->getNetevenMarketPlaceOrderId());

            if ($state == Mage_Sales_Model_Order::STATE_CLOSED) {
                // If imported new order is refunded / closed, we cancel it straight away
                $logger->info("State is closed, cancel the order");
                $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, $status, $comment);
            } else {
                
                // State protected
                if ($order->isStateProtected($state)) {
                    // State is complete, because other protected state (closed) is processed above
                    $logger->info(sprintf("State (%s) is protected", $state));

                    // Just comment
                    $history = $order->addStatusHistoryComment($comment, false); // no sense to set $status again
                    $history->setIsCustomerNotified(false); // for backwards compatibility
                } else {
                    $order->setState($state, $status, $comment);
                }
            }

            // Save order in order for save to be observed and order to be registered for incremental export
            $order
                ->setIsFromImport(true) // @see Neteven_NetevenSync_Model_Process_Order::registerIncrement()
                ->save();

            // Update catalog inventory based on ordered items
            $this->_updateCatalogInventory($order);

            // Create invoice, shipment, cancelation, creditmemo when needed
            $this->_runAdditionalOperations($order, $quote->getOrderStatus(), $quote->getCanInvoiceOrder());

            $logger->down();

            return $order;
        } catch (Exception $e) {
            $logger->exception($e);
            Mage::helper('netevensync')->log($e, Neteven_NetevenSync_Model_Config::NETEVENSYNC_PROCESS_ORDER_CODE);
            return false;
        }
    }

    /**
     * Update order
     *
     * @param Mage_Sales_Mode_Order $order
     * @param string $status
     * @param bool $isPaid
     * @return Neteven_NetevenSync_Model_Process_Order_Convertor
     */
    public function updateOrder($order, $status, $isPaid)
    {
        $logger = Mage::helper('netevensync/logger');
        $logger->info(sprintf("Update order %d with status %s and run additional operations", $order->getId(), $status));
        $logger->up();
        $ret = $this->_runAdditionalOperations($order, $status, $isPaid);
        $logger->down();
        return $ret;
    }

    /**
     * Update inventory on order create
     *
     * @param Mage_Sales_Mode_Order $order
     * @return Neteven_NetevenSync_Model_Process_Order_Convertor
     */
    protected function _updateCatalogInventory($order)
    {
        /* @var $logger Neteven_NetevenSync_Helper_Logger */
        $logger = Mage::helper('netevensync/logger');
        $logger->info("Update catalog inventory");
        $logger->up();

        $items = $order->getAllItems();
        foreach ($items as $item) {
            $product   = $item->getProduct();
            $stockItem = $product->getStockItem();

            $logger->step(sprintf("Product %s", $product->getSku()))->up();
            $logger->startComparison("Stock item", $stockItem->getData());

            $qty       = $stockItem->getQty() - $item->getQtyOrdered();
            if ($qty < 0) {
                $qty = 0;
            }
            $stockItem->setQty($qty)->save();

            $logger->endComparison($stockItem->getData());
            
            Mage::getModel('netevensync/process_inventory')->registerIncrement($product);

            $logger->down();
        }

        $logger->down();

        return $this;
    }

    /**
     * Create invoice, shipment, cancelation, creditmemo depending on neteven order status
     *
     * @param Mage_Sales_Mode_Order $order
     * @param string $status
     * @param bool|int $canInvoice
     * @return Neteven_NetevenSync_Model_Process_Order_Convertor
     */
    protected function _runAdditionalOperations($order, $status, $canInvoice)
    {
        /* @var $logger Neteven_NetevenSync_Helper_Logger */
        $logger = Mage::helper('netevensync/logger');

        $this->_hasInvoices  = (bool) $order->hasInvoices();
        $this->_hasShipments = (bool) $order->hasShipments();
        $this->_isCanceled   = (bool) $order->isCanceled();
        $this->_isRefunded   = (bool) $order->hasCreditmemos();

        $this->_canInvoice = (!$this->_hasInvoices && !$this->_isCanceled && !$this->_isRefunded && (bool) $canInvoice);
        $this->_canShip    = (!$this->_hasShipments && !$this->_isCanceled && !$this->_isRefunded);
        $this->_canCancel  = (!$this->_isCanceled && !$this->_hasInvoices);
        $this->_canRefund  = (!$this->_isRefunded && $this->_hasInvoices);

        $logger->step("Order flags", array(
            "can invoice" => $this->_canInvoice,
            "can ship"    => $this->_canShip,
            "can cancel"  => $this->_canCancel,
            "can refund"  => $this->_canRefund,
        ));

        $logger->data("order status [neteven]", $status);
        switch ($status) {
            case Neteven_NetevenSync_Model_Config::NETEVENSYNC_ORDER_STATUS_CONFIRMED:
                $logger->info("Process invoice");
                $this->invoice($order);
                break;
            case Neteven_NetevenSync_Model_Config::NETEVENSYNC_ORDER_STATUS_SHIPPED:
                $logger->info("Process ship");
                $this->ship($order);
                $this->invoice($order);
                break;
            case Neteven_NetevenSync_Model_Config::NETEVENSYNC_ORDER_STATUS_CANCELED:
                $logger->info("Process cancel");
                $this->invoice($order);
                $this->cancel($order);
                break;
            case Neteven_NetevenSync_Model_Config::NETEVENSYNC_ORDER_STATUS_REFUNDED:
                $logger->info("Process refund");
                $this->invoice($order);
                $this->refund($order);
                break;
        }

        $logger->info("Save the order");
        $order
            ->setIsFromImport(true) // @see Neteven_NetevenSync_Model_Process_Order::registerIncrement()
            ->save();

        return $this;
    }

    /**
     * Create invoice
     *
     * @param Mage_Sales_Model_Order
     * @return Neteven_NetevenSync_Model_Process_Order_Convertor
     */
    public function invoice($order)
    {
        /* @var $logger Neteven_NetevenSync_Helper_Logger */
        $logger = Mage::helper('netevensync/logger');

        if ($this->_canInvoice && $order->canInvoice()) {
            $logger->info("Prepare invoice");
            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
            if ($invoice) {
                $logger->up();
                $logger->info("Register invoice");
                $invoice->register();
                $invoice->getOrder()->setIsInProcess(true);
                $transactionSave = Mage::getModel('core/resource_transaction')
                        ->addObject($invoice)
                        ->addObject($invoice->getOrder());
                $transactionSave->save();

                $this->_hasInvoices = true;
                $logger->down();
            }
        }

        return $this;
    }

    /**
     * Ship order
     *
     * @param Mage_Sales_Model_Order
     * @return Neteven_NetevenSync_Model_Process_Order_Convertor
     */
    public function ship($order)
    {
        if ($this->_canShip && $order->canShip()) {
            $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment();
            if ($shipment) {
                $shipment->register();
                $shipment->getOrder()->setIsInProcess(true);
                $transactionSave = Mage::getModel('core/resource_transaction')
                        ->addObject($shipment)
                        ->addObject($shipment->getOrder());
                $transactionSave->save();

                $this->_hasShipments = true;
            }
        }

        $this->invoice($order);

        return $this;
    }

    /**
     * Cancel order
     *
     * @param Mage_Sales_Model_Order
     * @return Neteven_NetevenSync_Model_Process_Order_Convertor
     */
    public function cancel($order)
    {
        if ($this->_canCancel) {
            $order->cancel();
            $this->_isCanceled = true;
        }

        return $this;
    }

    /**
     * Refund order
     *
     * @param Mage_Sales_Model_Order
     * @return Neteven_NetevenSync_Model_Process_Order_Convertor
     */
    public function refund($order)
    {
        if ($this->_canRefund && $order->canCreditmemo()) {
            $invoiceId = $order->getInvoiceCollection()->getFirstItem()->getId();

            if (!$invoiceId) {
                return $this;
            }

            $invoice    = Mage::getModel('sales/order_invoice')->load($invoiceId)->setOrder($order);
            $service    = Mage::getModel('sales/service_order', $order);
            $creditmemo = $service->prepareInvoiceCreditmemo($invoice);

            $backToStock = array();
            foreach ($order->getAllItems() as $item) {
                $backToStock[$item->getId()] = true;
            }

            // Process back to stock flags
            foreach ($creditmemo->getAllItems() as $creditmemoItem) {
                if (Mage::helper('cataloginventory')->isAutoReturnEnabled()) {
                    $creditmemoItem->setBackToStock(true);
                } else {
                    $creditmemoItem->setBackToStock(false);
                }
            }

            $creditmemo->register();

            $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($creditmemo)
                    ->addObject($creditmemo->getOrder());
            if ($creditmemo->getInvoice()) {
                $transactionSave->addObject($creditmemo->getInvoice());
            }
            $transactionSave->save();

            $this->_isRefunded = true;
        }

        return $this;
    }

    /**
     * Retrieve country id based on country name
     *
     *  @param string $countryName
     *  @return string
     */
    protected function _getCountryId($countryName)
    {
        if (is_null($this->_countryCollection)) {
            $this->_countryCollection = Mage::getResourceModel('directory/country_collection')->toOptionArray();
        }
        foreach ($this->_countryCollection as $country) {
            if (strtolower($country['label']) == strtolower($countryName)) {
                return $country['value'];
            }
        }
        return $countryName;
    }

}

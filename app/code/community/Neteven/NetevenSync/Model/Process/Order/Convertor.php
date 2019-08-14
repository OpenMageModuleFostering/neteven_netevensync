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
class Neteven_NetevenSync_Model_Process_Order_Convertor {

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
    public function getConfig(){
        if(is_null($this->_config)) {
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
    public function createQuote($netevenItem) {

        $billingAddress = $netevenItem->getBillingAddress();
        $shippingAddress = $netevenItem->getShippingAddress();
        $addresses = array('billingAddress' => $billingAddress, 'shippingAddress' => $shippingAddress);

        // Find store for quote
        $storeId = $this->getConfig()->getStoreIdForMarketplace($netevenItem->getMarketPlaceId());
        if($storeId) {
            $store = Mage::getModel('core/store')->load($storeId);
        }
        else {
            $store = Mage::app()->getDefaultStoreView();
        }

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

        if(!$quote) {
            return false;
        }

        // Retrieve Neteven address fields and concatenate for addresses objects
        $addressForm = Mage::getModel('customer/form');
        $addressForm->setFormCode('customer_address_edit')
            ->setEntityType('customer_address')
        ;

        foreach($addresses as $name => $address) {
            foreach($addressForm->getAttributes() as $attribute) {
                $mappedAttributeCode = $this->getConfig()->getMappedAddressAttributeCode($attribute->getAttributeCode());
                $value = array();
                if(is_array($mappedAttributeCode)) {
                    foreach($mappedAttributeCode as $attributeCode) {
                        if($shippingAddress->getData($attributeCode)) {
                            $value[] = $address->getData($attributeCode);
                        }
                    }
                }
                else {
                    if($shippingAddress->getData($mappedAttributeCode)) {
                        $value[] = $address->getData($mappedAttributeCode);
                    }
                }

                // Use Neteven's mobile field if telephone is empty
                if ($attribute->getAttributeCode() == 'telephone' && (!$address->getPhone() || $address->getPhone() == '')) {
                    $value[] = $address->getMobile();
                }

                // Manage country based on MarketPlaceId
                if(empty($value) && $attribute->getAttributeCode() == 'country_id') {
                    $value[] = $this->getConfig()->getCountryIdForMarketPlaceId($netevenItem->getMarketPlaceId());
                }

                if(count($value) > 0) {
                    $value = implode(' ', $value);

                    // Retrieve country code
                    if($attribute->getAttributeCode() == 'country_id') {
                        $value = $this->_getCountryId($value);
                    }

                    $method = 'get' . ucfirst($name);
                    $quote->$method()->setData($attribute->getAttributeCode(), $value);
                }
            }
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
        $quote->collectTotals();
        $quote->save();

        return $quote;
    }

    /**
     * Add item to quote
     *
     * @param Varien_Object $netevenItem
     * @param Mage_Sales_Mode_Quote
     * @return Mage_Sales_Mode_Quote
     */
    public function addItemToQuote($netevenItem, $quote) {

        $quote->setIsSuperMode(true); // to avoid qty check

        $productModel = Mage::getModel('catalog/product');
        $product = $productModel->load($productModel->getIdBySku($netevenItem->getSku()));

        // Check that product exists in catalog
        if(!$product->getId()) {
            $message = Mage::helper('netevensync')->__('Imported order item does not exist in catalog. Item ID: %s, Order ID: %s, Sku: %s', $netevenItem->getId(), $netevenItem->getOrderId(), $netevenItem->getSku());
            Mage::helper('netevensync')->log($message, Neteven_NetevenSync_Model_Config::NETEVENSYNC_PROCESS_ORDER_CODE);
            return false;
        }

        // Check that product can be added to quote based on its type
        if(!in_array($product->getTypeId(), $this->getConfig()->getAvailableProductTypes())) {
            $message = Mage::helper('netevensync')->__('Imported order item is of type "%s" which is not allowed for orders import. Item ID: %s, Order ID: %s, Sku: %s', $product->getTypeId(), $netevenItem->getId(), $netevenItem->getOrderId(), $netevenItem->getSku());
            Mage::helper('netevensync')->log($message, Neteven_NetevenSync_Model_Config::NETEVENSYNC_PROCESS_ORDER_CODE);
            return false;
        }

        // Create quote item
        $quoteItem = Mage::getModel('sales/quote_item');

        // Force price to Neteven price (price incl VAT)
        $price = $netevenItem->getPrice()->getValue() / $netevenItem->getQuantity();

        $quoteItem
            ->setProduct($product)
            ->setCustomPrice($price)
            ->setOriginalCustomPrice($price)
            ->setQuote($quote)
            ->setQty($netevenItem->getQuantity())
        ;

        $quote->addItem($quoteItem);

        return $quote;
    }

    /**
     * Create order
     *
     * @param Mage_Sales_Model_Quote
     * @return Mage_Sales_Model_Order
     */
    public function createOrder($quote) {

        try {
            // Convert quote to order...
            $items = $quote->getAllItems();
            $quote->reserveOrderId();

            $convertQuote = Mage::getSingleton('sales/convert_quote');
            $order = $convertQuote->addressToOrder($quote->getShippingAddress());

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
            $status = $this->getConfig()->getMappedOrderStatus($quote->getOrderStatus());
            $state = $this->getConfig()->getMappedOrderState($quote->getOrderStatus());
            $comment = Mage::helper('netevensync')->__('Order %s imported from Neteven', $quote->getNetevenMarketPlaceOrderId());

            if($state == Mage_Sales_Model_Order::STATE_CLOSED) {
                // If imported new order is refunded / closed, we cancel it straight away
                $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, $status, $comment);
            }
            else {
                $order->setState($state, $status, $comment);
            }

            // Save order in order for save to be observed and order to be registered for incremental export
            $order
                ->setIsFromImport(true) // @see Neteven_NetevenSync_Model_Process_Order::registerIncrement()
                ->save();

            // Update catalog inventory based on ordered items
            $this->_updateCatalogInventory($order);

            // Create invoice, shipment, cancelation, creditmemo when needed
            $this->_runAdditionalOperations($order, $quote->getOrderStatus());

            return $order;

        } catch (Exception $e){
            Mage::helper('netevensync')->log($e->getMessage(), Neteven_NetevenSync_Model_Config::NETEVENSYNC_PROCESS_ORDER_CODE);
            return false;
        }
    }

    /**
     * Update order
     *
     * @param Mage_Sales_Mode_Order $order
     * @param string $status
     * @return Neteven_NetevenSync_Model_Process_Order_Convertor
     */
    public function updateOrder($order, $status) {
        return $this->_runAdditionalOperations($order, $status);
    }

    /**
     * Update inventory on order create
     *
     * @param Mage_Sales_Mode_Order $order
     * @return Neteven_NetevenSync_Model_Process_Order_Convertor
     */
    protected function _updateCatalogInventory($order) {
        $items = $order->getAllItems();
        foreach($items as $item) {
            $product = $item->getProduct();
            $stockItem = $product->getStockItem();
            $qty = $stockItem->getQty() - $item->getQtyOrdered();
            if($qty < 0) {
                $qty = 0;
            }
            $stockItem->setQty($qty)->save();
            Mage::getModel('netevensync/process_inventory')->registerIncrement($product);
        }

        return $this;
    }

    /**
     * Create invoice, shipment, cancelation, creditmemo depending on neteven order status
     *
     * @param Mage_Sales_Mode_Order $order
     * @param string $status
     * @return Neteven_NetevenSync_Model_Process_Order_Convertor
     */
    protected function _runAdditionalOperations($order, $status) {
        $this->_hasInvoices = (bool) $order->hasInvoices();
        $this->_hasShipments = (bool) $order->hasShipments();
        $this->_isCanceled = (bool) $order->isCanceled();
        $this->_isRefunded =  (bool) $order->hasCreditmemos();

        if(!$this->_hasInvoices && !$this->_isCanceled && !$this->_isRefunded) {
            $this->_canInvoice = true;
        }

        if(!$this->_hasShipments && !$this->_isCanceled && !$this->_isRefunded) {
            $this->_canShip = true;
        }

        if(!$this->_isCanceled && !$this->_hasInvoices) {
            $this->_canCancel = true;
        }

        if(!$this->_isRefunded && $this->_hasInvoices) {
            $this->_canRefund = true;
        }

        switch($status) {
            case Neteven_NetevenSync_Model_Config::NETEVENSYNC_ORDER_STATUS_CONFIRMED:
                $this->invoice($order);
                break;
            case Neteven_NetevenSync_Model_Config::NETEVENSYNC_ORDER_STATUS_SHIPPED:
                $this->ship($order);
                break;
            case Neteven_NetevenSync_Model_Config::NETEVENSYNC_ORDER_STATUS_CANCELED:
                $this->cancel($order);
                break;
            case Neteven_NetevenSync_Model_Config::NETEVENSYNC_ORDER_STATUS_REFUNDED:
                $this->refund($order);
                break;
        }

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
    public function invoice($order) {
        if($this->_canInvoice && $order->canInvoice()) {
            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
            if($invoice) {
                $invoice->register();
                $invoice->getOrder()->setIsInProcess(true);
                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder());
                $transactionSave->save();

                $this->_hasInvoices = true;
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
    public function ship($order) {
        if($this->_canShip && $order->canShip()) {
            $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment();
            if($shipment) {
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
    public function cancel($order) {
        if($this->_canCancel) {
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
    public function refund($order) {
        if($this->_canRefund && $order->canCreditmemo()) {
            $invoiceId = $order->getInvoiceCollection()->getFirstItem()->getId();

            if(!$invoiceId) {
                return $this;
            }

            $invoice = Mage::getModel('sales/order_invoice')->load($invoiceId)->setOrder($order);
            $service = Mage::getModel('sales/service_order', $order);
            $creditmemo = $service->prepareInvoiceCreditmemo($invoice);

            $backToStock = array();
            foreach($order->getAllItems() as $item) {
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
    protected function _getCountryId($countryName) {
        if(is_null($this->_countryCollection)) {
            $this->_countryCollection = Mage::getResourceModel('directory/country_collection')->toOptionArray();
        }
        foreach($this->_countryCollection as $country) {
            if(strtolower($country['label']) == strtolower($countryName)) {
                return $country['value'];
            }
        }
        return $countryName;
    }
}
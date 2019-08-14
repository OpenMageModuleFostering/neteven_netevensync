<?php

/**
 * Soap connector
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Soap extends Zend_Soap_Client
{

    protected $_successStatusResponse;
    protected $_successItems = array();
    protected $_postedItems  = array();

    /**
     * Constructor that defines WSDL and automatically connects to WS on model instanciation
     */
    public function __construct()
    {
        ini_set('default_socket_timeout', 120);
        parent::__construct(Mage::getStoreConfig('netevensync/soap/wsdl'));
        $this->_connect();
    }

    /**
     * Connect to Neteven WS
     *
     * @return Neteven_NetevenSync_Model_Soap
     */
    protected function _connect()
    {
        $login     = Mage::getStoreConfig('netevensync/general/email');
        $seed      = Mage::helper('core')->getRandomString(32);
        $stamp     = date('Y-m-d\TH:i:s', Mage::getModel('core/date')->timestamp(time()));
        $password  = Mage::getStoreConfig('netevensync/general/password');
        $signature = base64_encode(md5(implode("/", array($login, $stamp, $seed, $password)), true));

        $auth = array(
            'Method'    => '*',
            'Login'     => $login,
            'Seed'      => $seed,
            'Stamp'     => $stamp,
            'Signature' => $signature
        );

        $this->addSoapInputHeader(new SoapHeader('urn:NWS:examples', "AuthenticationHeader", $auth));

        return $this;
    }

    /**
     * Test connection
     *
     * @return bool || Exception
     */
    public function testWsConnection()
    {
        $this->TestConnection();
    }

    /**
     * Process Post Items
     *
     * @param array $items
     * @param string $processType
     * @return bool
     */
    public function processPostItems($items, $processType)
    {

        $success = true;

        Mage::helper('netevensync')->logDebug('processPostItems posted items data:');
        Mage::helper('netevensync')->logDebug($items);

        try {
            foreach ($items as $k => $item) {
                if (isset($item['ArrayOfSpecificFields'])) {
                    foreach ($item['ArrayOfSpecificFields'] as $j => $specificField) {
                        // If there is some language-related data, we must update <SpecificField> SOAP XML node
                        if (isset($specificField['lang'])) {
                            $items[$k]['ArrayOfSpecificFields'][$j] = new SoapVar('<ns1:SpecificField lang="' . $specificField['lang'] . '"><ns1:Name>' . $specificField['Name'] . '</ns1:Name><ns1:Value><![CDATA[' . $specificField['Value'] . ']]></ns1:Value></ns1:SpecificField>', XSD_ANYXML);
                        }
                    }
                }
            }

            $response = $this->PostItems(array('items' => $items));
        } catch (Exception $e) {
            Mage::helper('netevensync')->log($e);
            Mage::helper('netevensync')->logDebug($this->GetLastRequest());
            return array('success' => false, 'success_items_count' => count($this->_successItems));
        }

        Mage::helper('netevensync')->logDebug($this->GetLastRequest());
        Mage::helper('netevensync')->logDebug('processPostItems response:');
        Mage::helper('netevensync')->logDebug($response);
        Mage::helper('netevensync')->logDebug($this->GetLastResponse());

        $itemsStatus = $response->PostItemsResult->InventoryItemStatusResponse;

        $success = $this->_checkResponse($itemsStatus, $processType);

        return array('success' => $success, 'success_items_count' => count($this->_successItems));
    }

    /**
     * Get orders
     *
     * @param array $params
     * @return Neteven_NetevenSync_Model_Soap
     */
    public function requestOrders($params = array())
    {
        $date = false;

        $from = Mage::getSingleton('adminhtml/session')->getNetevenSyncFrom();
        if ($from && $from != '') {
            $date = date(DATE_ATOM, $from);
            Mage::getSingleton('adminhtml/session')->setNetevenSyncFrom(null);
        }

        if (!$date) {
            $lastSync = Mage::getModel('netevensync/config_process')
                    ->loadByProcessCode(Neteven_NetevenSync_Model_Config::NETEVENSYNC_PROCESS_ORDER_CODE)
                    ->getLastSync();
            $zendDate = new Zend_Date($lastSync, 'y-MM-d HH:mm:ss', Mage::app()->getLocale()->getLocaleCode());
            $date     = date(DATE_ATOM, $zendDate->getTimestamp());
        }

        $params['DateModificationFrom'] = $date;

        // Get orders from sandbox marketplace when in sandbox mode
        if (Mage::getStoreConfigFlag('netevensync/general/sandbox')) {
            $params['MarketPlaceId'] = Neteven_NetevenSync_Model_Config::SANDBOX_MARKETPLACE_ID;
        }

        $this->processOrdersRequest($params);

        return $this;
    }

    /**
     * Request orders to SOAP
     * 
     * @param array $params
     * @return Neteven_NetevenSync_Model_Soap
     */
    public function processOrdersRequest($params)
    {

        // Process first results page
        $response = $this->requestOrdersPage($params);
        $this->_processRequestedOrdersPage($response);

        // Process next result pages
        $pagesTotal = $response->PagesTotal;
        if ($pagesTotal > 1) {
            for ($page = 2; $page <= $pagesTotal; $page++) {
                $this->_processRequestedOrdersPage($this->requestOrdersPage($params, $page));
            }
        }

        return $this;
    }

    /**
     * Get orders by page
     *
     * @param array $params
     * @param int $page
     */
    public function requestOrdersPage($params, $page = 1)
    {
        $soapClient           = new self(); // We must re-instanciate self to re-auth
        $params['PageNumber'] = $page;

        Mage::helper('netevensync')->logDebug('requestOrdersPage posted params for page ' . $page);
        Mage::helper('netevensync')->logDebug($params);

        $response = $soapClient->GetOrders($params);

        Mage::helper('netevensync')->logDebug($this->GetLastRequest());
        Mage::helper('netevensync')->logDebug('requestOrdersPage response for page ' . $page);
        Mage::helper('netevensync')->logDebug($response);
        Mage::helper('netevensync')->logDebug($this->GetLastResponse());

        return $response;
    }

    /**
     * Prepare order response
     * 
     * @param object $response
     * @return Neteven_NetevenSync_Model_Soap
     */
    protected function _processRequestedOrdersPage($data)
    {
        Mage::getSingleton('netevensync/process_order')->saveSoapOrdersPage($data);
        return $this;
    }

    /**
     * Process Post Orders
     *
     * @param array $items
     * @param string $processType
     * @return bool
     */
    public function processPostOrders($items, $processType)
    {
        foreach ($items as $k => $data) {
            $this->_postedItems[$k] = reset($data);
        }

        Mage::helper('netevensync')->logDebug('processPostOrders posted items:');
        Mage::helper('netevensync')->logDebug($items);

        try {
            $response = $this->PostOrders(array('orders' => $items));
        } catch (Exception $e) {
            Mage::helper('netevensync')->log($e);
            Mage::helper('netevensync')->logDebug($this->GetLastRequest());
            return array('success' => false, 'success_items_count' => count($this->_successItems));
        }

        Mage::helper('netevensync')->logDebug($this->GetLastRequest());
        Mage::helper('netevensync')->logDebug('processPostOrders response:');
        Mage::helper('netevensync')->logDebug($response);
        Mage::helper('netevensync')->logDebug($this->GetLastResponse());

        $postOrdersResult = $response->PostOrdersResult;

        if (is_array($postOrdersResult)) {
            foreach ($postOrdersResult as $result) {
                $itemsStatus[] = $result->MarketPlaceOrderStatusResponse;
            }
        } else {
            $itemsStatus = $postOrdersResult->MarketPlaceOrderStatusResponse;
        }

        $success = $this->_checkResponse($itemsStatus, $processType);

        if (count($this->_successItems) > 0) {
            $collection = Mage::getModel('netevensync/process_order')->getCollection();

            // Add order link data
            $collection->getSelect()->joinLeft(
                    array('link' => $collection->getTable('netevensync/order_link')), 'link.magento_order_id = main_table.order_id', array('neteven_order_id')
            );

            $collection->addFieldToFilter('link.neteven_order_id', array('in' => $this->_successItems));
            $collection->walk('delete');
        }

        return array('success' => $success, 'success_items_count' => count($this->_successItems));
    }

    /**
     * Check SOAP response
     * 
     * @param object $items
     * @param string $processType
     * @return bool $success
     */
    protected function _checkResponse($items, $processType)
    {
        $success = true;

        if (is_array($items)) { // $itemStatus can be an array when several items are returned by WS
            foreach ($items as $index => $item) {
                $statusSuccess = $this->_checkStatusResponse($item, $processType, $index);
                if ($statusSuccess) {
                    $itemId                = (isset($this->_postedItems[$index])) ? $this->_postedItems[$index] : $item->ItemCode;
                    $this->_successItems[] = $itemId;
                } else {
                    $success = false;
                }
            }
        } else {
            $statusSuccess = $this->_checkStatusResponse($items, $processType);
            if ($statusSuccess) {
                $itemId                = (count($this->_postedItems) > 0) ? reset($this->_postedItems) : $items->ItemCode;
                $this->_successItems[] = $itemId;
            } else {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Check status response and log error when needed
     *
     * @param object $item
     * @param string $processType
     * @param int $index
     * @return bool
     */
    protected function _checkStatusResponse($item, $processType, $index = null)
    {

        if (!in_array($item->StatusResponse, $this->getSuccessStatusResponse())) {
            $itemId = isset($this->_postedItems[$index]) ? $this->_postedItems[$index] : (isset($item->ItemCode) ? $item->ItemCode : '0');
            Mage::helper('netevensync')->log($processType . ' ' . $itemId . ': ' . $item->StatusResponse . ' . ' . $item->StatusResponseDetail, $processType);
            return false;
        }
        return true;
    }

    /**
     * Retrieve StatusResponse labels that are considered as success
     *
     * @return array
     */
    public function getSuccessStatusResponse()
    {
        if (is_null($this->_successStatusResponse)) {
            $this->_successStatusResponse = Mage::getSingleton('netevensync/config')->getSuccessStatusResponse();
        }
        return $this->_successStatusResponse;
    }

}

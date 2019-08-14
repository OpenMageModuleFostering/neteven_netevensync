<?php

/**
 * Config model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Config
{

    /**
     * Process types codes
     */
    const NETEVENSYNC_PROCESS_INVENTORY_CODE = 'inventory';
    const NETEVENSYNC_PROCESS_ORDER_CODE     = 'order';
    const NETEVENSYNC_PROCESS_STOCK_CODE     = 'stock';

    /**
     * Export modes codes
     */
    const NETEVENSYNC_EXPORT_FULL        = 'full';
    const NETEVENSYNC_EXPORT_INCREMENTAL = 'incremental';

    /**
     * Process direction codes
     */
    const NETEVENSYNC_DIR_IMPORT = 'import';
    const NETEVENSYNC_DIR_EXPORT = 'export';

    /**
     * Page Size for chunks in Neteven API calls
     */
    const NETEVENSYNC_CHUNK_SIZE      = 150;
    const NETEVENSYNC_CHUNK_SIZE_AJAX = 20;

    /**
     * Neteven Order Statuses
     */
    const NETEVENSYNC_ORDER_STATUS_TOCONFIRM = 'toConfirm';
    const NETEVENSYNC_ORDER_STATUS_CONFIRMED = 'Confirmed';
    const NETEVENSYNC_ORDER_STATUS_SHIPPED   = 'Shipped';
    const NETEVENSYNC_ORDER_STATUS_CANCELED  = 'Canceled';
    const NETEVENSYNC_ORDER_STATUS_REFUNDED  = 'Refunded';

    /**
     * Neteven Sandbox marketplace ID for orders
     */
    const SANDBOX_MARKETPLACE_ID = '19';
    const INVENTORY_SKUFAMILY_CODE          = 'sku_family';
    const INVENTORY_SKUFAMILY_AUTOMATIC_KEY = '_automatic';

    /**
     * Store ID <--> Marketplace mapping
     *
     * @var array
     */
    protected $_storeIdsForMarketplaces = array();

    /**
     * Retrieve synchronization process codes
     *
     * @return array
     */
    public function getProcessCodes()
    {
        return array(
            self::NETEVENSYNC_PROCESS_INVENTORY_CODE,
            self::NETEVENSYNC_PROCESS_ORDER_CODE,
            self::NETEVENSYNC_PROCESS_STOCK_CODE,
        );
    }

    /**
     * Retrieve process directions
     *
     * @return array
     */
    public function getDirs()
    {
        return array(
            self::NETEVENSYNC_DIR_IMPORT,
            self::NETEVENSYNC_DIR_EXPORT,
        );
    }

    /**
     * Retrieve errors labels
     *
     * @return array || string $errors
     */
    public function getErrorLabels($errorCode = null)
    {
        $errorCodes = array(
            self::NETEVENSYNC_PROCESS_INVENTORY_CODE => Mage::helper('netevensync')->__('Inventory'),
            self::NETEVENSYNC_PROCESS_ORDER_CODE     => Mage::helper('netevensync')->__('Orders'),
            self::NETEVENSYNC_PROCESS_STOCK_CODE     => Mage::helper('netevensync')->__('Stocks'),
        );

        if ($errorCode && isset($errorCodes[$errorCode])) {
            return $errorCodes[$errorCode];
        }

        return $errorCodes;
    }

    /**
     * Retrieve attribute codes that are not available as specific attributes
     *
     * @return array
     */
    public function getDisallowedAttributes()
    {
        return array_keys(Mage::getConfig()->getNode('netevensync/disallowed_attributes')->asArray());
    }

    /**
     * Append dynamic fields to system config
     * @param Varien_Event_Observer $observer
     */
    public function appendConfigNodes(Varien_Event_Observer $observer)
    {

        $config = $observer->getConfig();

        // Specific Fields Mapping
        $specificFields = Mage::getConfig()->getNode('netevensync/specific_fields')->asArray();
        $xml            = array();
        $sortOrder      = 100;
        foreach ($specificFields as $code => $label) {

            switch ($code) {
                case 'ean':
                    $comment = '<comment>' . Mage::helper('netevensync')->__('EAN code is highly recommended') . '</comment>';
                    break;
                case 'isbn':
                    $comment = '<comment>' . Mage::helper('netevensync')->__('ISBN code is highly recommended for books') . '</comment>';
                    break;
                case 'asin':
                    $comment = '<comment>' . Mage::helper('netevensync')->__('ASIN code is highly recommended for sales on Amazon') . '</comment>';
                    break;
                default:
                    $comment = '';
            }

            $sourceModel = 'netevensync/adminhtml_system_config_source_attribute';

            if (strstr($code, 'price_')) { // If field is if type 'price'
                $sourceModel = 'netevensync/adminhtml_system_config_source_attribute_price';
            }

            if ($code == self::INVENTORY_SKUFAMILY_CODE) { // If field is 'sku_family'
                $sourceModel = 'netevensync/adminhtml_system_config_source_attribute_skufamily';
            }

            $xml[] = sprintf(
                    '<%s translate="label"><label>%s</label><frontend_type>select</frontend_type><source_model>%s::toSelect</source_model><sort_order>%s</sort_order><show_in_default>1</show_in_default><show_in_website>0</show_in_website><show_in_store>0</show_in_store>%s<depends><enable>1</enable></depends></%s>', $code, $label, $sourceModel, $sortOrder, $comment, $code
            );
            $sortOrder++;
        }
        foreach ($xml as $field) {
            $node = new Mage_Core_Model_Config_Element($field);
            $config->getNode('sections/netevensync/groups/inventory/fields')->appendChild($node);
        }


        // Neteven <=> Magento Order Statuses Mapping
        $netevenStatuses = $this->getNetevenOrderStatuses();
        $xml             = array();
        $sortOrder       = 100;
        foreach ($netevenStatuses as $code => $label) {
            $state  = $this->getMappedOrderState($code);
            $method = 'get' . ucfirst($state) . 'Statuses';
            $xml[]  = sprintf(
                    '<%s translate="label"><label>%s</label><frontend_type>select</frontend_type><source_model>netevensync/adminhtml_system_config_source_magentoOrderStatus::%s</source_model><sort_order>%s</sort_order><show_in_default>1</show_in_default><show_in_website>0</show_in_website><show_in_store>0</show_in_store></%s>', $code, $label, $method, $sortOrder, $code
            );
            $sortOrder++;
        }
        foreach ($xml as $field) {
            $node = new Mage_Core_Model_Config_Element($field);
            $config->getNode('sections/netevensync/groups/order_mapping_neteven/fields')->appendChild($node);
        }

        // Magento <=> Neteven Order Statuses Mapping
        $magentoStatuses = Mage::getSingleton('sales/order_config')->getStatuses();
        $xml             = array();
        $sortOrder       = 1000;
        foreach ($magentoStatuses as $code => $label) {
            $xml[] = sprintf(
                    '<%s translate="label"><label>%s</label><frontend_type>select</frontend_type><source_model>netevensync/adminhtml_system_config_source_netevenOrderStatus::toSelect</source_model><sort_order>%s</sort_order><show_in_default>1</show_in_default><show_in_website>0</show_in_website><show_in_store>0</show_in_store></%s>', $code, $label, $sortOrder, $code
            );
            $sortOrder++;
        }
        foreach ($xml as $field) {
            $node = new Mage_Core_Model_Config_Element($field);
            $config->getNode('sections/netevensync/groups/order_mapping_magento/fields')->appendChild($node);
        }
    }

    /**
     * Retrieve product types that may be used for Neteven Selection et synchronization
     *
     * @param bool $withLabel
     * @return array $availableProductTypes
     */
    public function getAvailableProductTypes($withLabel = false)
    {
        $availableProductTypes = array();
        $allProductTypes       = Mage::getSingleton('catalog/product_type')->getOptionArray();
        $allowedProductTypes   = array(
            Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
            Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL,
        );
        foreach ($allProductTypes as $code => $label) {
            if (in_array($code, $allowedProductTypes)) {
                $availableProductTypes[$code] = ($withLabel) ? $label : $code;
            }
        }
        return $availableProductTypes;
    }

    /**
     * Retrieve Neteven order statuses
     *
     * @return array
     */
    public function getNetevenOrderStatuses()
    {
        $confOptions = Mage::getConfig()->getNode('netevensync/order_statuses')->asArray();
        $options     = array();
        foreach ($confOptions as $code => $label) {
            $options[$code] = Mage::helper("netevensync")->__($label);
        }
        return $options;
    }

    /**
     * Retrieve StatusResponse labels that are considered as success
     *
     * @return array
     */
    public function getSuccessStatusResponse()
    {
        return array(
            'Accepted',
            'Inserted',
            'Updated',
            'Deleted',
        );
    }

    /**
     * Map Magento address attribute codes with Neteven ones
     *
     * @param string $attributeCode
     * @return mixed
     */
    public function getMappedAddressAttributeCode($attributeCode)
    {
        $attributeCodes = array(
            'prefix'     => 'na',
            'firstname'  => 'first_name',
            'middlename' => 'na',
            'lastname'   => 'last_name',
            'suffix'     => 'na',
            'company'    => 'company',
            'street'     => array('address1', 'address2'),
            'city'       => 'city_name',
            'country_id' => 'country_code',
            'region'     => 'na',
            'region_id'  => 'na',
            'postcode'   => 'postal_code',
            'telephone'  => 'phone',
            'fax'        => 'fax',
            'vat_id'     => 'na',
        );
        return $attributeCodes[$attributeCode];
    }

    /**
     * Retrieve country id for a MarketPlaceId
     *
     * @param int $marketPlaceId
     * @return string
     */
    public function getCountryIdForMarketPlaceId($marketPlaceId)
    {
        $mapping = $this->getMarketplacesCountries();

        if (isset($mapping[$marketPlaceId])) {
            return strtoupper($mapping[$marketPlaceId]);
        }

        return 'FR';
    }

    /**
     * Retrieve Magento payment method based on Neteven payment code
     *
     * @param string $paymentCode
     * @return array
     */
    public function getMappedPaymentCode($paymentCode)
    {
        $confOptions = Mage::getConfig()->getNode('netevensync/payment_methods')->asArray();
        if (isset($confOptions[$paymentCode])) {
            return $confOptions[$paymentCode];
        } else
            return 'neteven';
    }

    /**
     * Retrieve Magento order status method based on Neteven status code
     * or
     * Neteven order status method based on Magento status code
     *
     * @param string $statusCode
     * @param string $source
     * @return string
     */
    public function getMappedOrderStatus($statusCode, $source = 'neteven')
    {
        return Mage::getStoreConfig('netevensync/order_mapping_' . $source . '/' . $statusCode);
    }

    /**
     * Retrieve Magento order state method based on Neteven status code
     *
     * @param string $statusCode
     * @return string
     */
    public function getMappedOrderState($statusCode)
    {
        $states = array(
            self::NETEVENSYNC_ORDER_STATUS_TOCONFIRM => Mage_Sales_Model_Order::STATE_NEW,
            self::NETEVENSYNC_ORDER_STATUS_CONFIRMED => Mage_Sales_Model_Order::STATE_PROCESSING,
            self::NETEVENSYNC_ORDER_STATUS_SHIPPED   => Mage_Sales_Model_Order::STATE_COMPLETE,
            self::NETEVENSYNC_ORDER_STATUS_CANCELED  => Mage_Sales_Model_Order::STATE_CANCELED,
            self::NETEVENSYNC_ORDER_STATUS_REFUNDED  => Mage_Sales_Model_Order::STATE_CLOSED,
        );

        if (isset($states[$statusCode])) {
            return $states[$statusCode];
        }

        return Mage_Sales_Model_Order::STATE_NEW;
    }

    /**
     * Retrieve Neteven order states that can be imported
     *
     * @return array
     */
    public function getAllowedNetevenOrderStatesForImport()
    {
        return array(
            self::NETEVENSYNC_ORDER_STATUS_CONFIRMED,
            self::NETEVENSYNC_ORDER_STATUS_SHIPPED,
            self::NETEVENSYNC_ORDER_STATUS_REFUNDED,
        );
    }

    /**
     * Retrieve inventory mapped fields that are price
     *
     * @return array
     */
    public function getInventoryPriceSpecificFields()
    {
        $priceSpecificFields = array();
        $specificFields      = Mage::getConfig()->getNode('netevensync/specific_fields')->asArray();

        foreach ($specificFields as $code => $label) {
            if (strstr($code, 'price_')) { // If field is if type 'price'
                $attributeCode = Mage::getStoreConfig('netevensync/inventory/' . $code);
                if ($attributeCode && $attributeCode != '') {
                    $priceSpecificFields[$label] = $attributeCode;
                }
            }
        }

        return $priceSpecificFields;
    }

    /**
     * Retrieve languages available for inventory export from config
     *
     * @return array
     */
    public function getInventoryLanguages()
    {
        $inventoryLanguages = array();
        $languages          = explode(',', Mage::getConfig()->getNode('netevensync/inventory_languages'));

        foreach ($languages as $language) {
            $inventoryLanguages[trim($language)] = trim($language);
        }

        return $inventoryLanguages;
    }

    /**
     * Retrieve language mapping that is configured
     *
     * @return mixed
     */
    public function getConfiguredInventoryLanguages()
    {
        $configCollection = Mage::getResourceModel('core/config_data_collection')
                ->addFieldToFilter('path', 'netevensync/inventory/language');

        $configuredInventoryLanguages = array();
        foreach ($configCollection as $configItem) {
            if (!is_null($configItem->getValue())) {
                $configuredInventoryLanguages[$configItem->getScopeId()] = $configItem->getValue();
            }
        }

        return $configuredInventoryLanguages;
    }

    /**
     * Map pdm_config.xml to array
     *
     * @return array
     */
    public function getMarketplacesCountries()
    {
        /* @var $logger Neteven_NetevenSync_Helper_Logger */
        $logger = Mage::helper('netevensync/logger');
        $logger->info("Read marketplaces / countries mapping from CSV");

        // Mapping
        $mappingCsv = Mage::getModuleDir('etc', 'Neteven_NetevenSync') . DS . 'marketplace_country.csv';
        $io         = new Varien_File_Csv();
        $mapping    = $io->getDataPairs($mappingCsv, 0, 2);

        $marketplacesCountries = array_map('strtolower', $mapping);
        return $marketplacesCountries;
    }

    /**
     * Retrieve marketplaces as option array
     * @return array
     */
    public function getMarketplacesAsOptionArray()
    {
        // CSV header:
        // ID, Marketplace name, Country code
        $mappingCsv = Mage::getModuleDir('etc', 'Neteven_NetevenSync') . DS . 'marketplace_country.csv';
        $csv        = new Varien_File_Csv;
        $lines      = $csv->getData($mappingCsv);
        $options    = array();

        foreach ($lines as $line) {
            $options[] = array(
                'value' => $line[0],
                'label' => sprintf("%s (%s / #%s)", $line[1], $line[2], $line[0]),
            );
        }

        return $options;
    }

    /**
     * Retrieve the country selected with the marketplace in configuration
     * @param int $marketplaceId
     * @return string
     */
    public function getAddressCountryForMarketPlaceId($marketplaceId)
    {
        /* @var $logger Neteven_NetevenSync_Helper_Logger */
        $logger = Mage::helper('netevensync/logger');
        $logger->info("Get the country selected in configuration for the marketplace.");

        // Loop stores
        $stores = Mage::app()->getStores(false);
        foreach ($stores as $store) {
            $config = Mage::getStoreConfig('netevensync/order/pdm_mapping', $store);
            if (null !== $config) {
                $mapping = unserialize($config);
                // Loop mapping
                foreach ($mapping as $map) {
                    if ((int) $map['pdm'] === (int) $marketplaceId) {
                        return strtoupper($map['country']);
                    }
                }
            }
        }

        return $this->getCountryIdForMarketPlaceId($marketplaceId);
    }

    /**
     * Retrieve configured store ID for a marketplace ID
     *
     * @param $marketplace
     * @return bool|int
     */
    public function getStoreIdForMarketplace($marketplace)
    {
        /* @var $logger Neteven_NetevenSync_Helper_Logger */
        $logger = Mage::helper('netevensync/logger');
        $logger->step("Find store ID according to marketplace ID")->up();
        $logger->data("marketplace_id", $marketplace);

        if (!$logger->condition("Store ID already found for this marketplace?", isset($this->_storeIdsForMarketplaces[$marketplace]))) {
            $countryForMarketplace = false;
            $marketplaceCountries  = $this->getMarketplacesCountries();

            foreach ($marketplaceCountries as $configMarketplace => $countryCode) {
                if ($marketplace == $configMarketplace) {
                    $logger->result($countryCode, "Country code");
                    $countryForMarketplace = $countryCode;
                    break;
                }
            }

            if ($countryForMarketplace) {
                $logger->info("Loop configuration to get the mapping store/marketplace");

                // Loop stores
                $stores = Mage::app()->getStores(false);
                foreach ($stores as $store) {
                    $config = Mage::getStoreConfig('netevensync/order/pdm_mapping', $store);
                    if (null !== $config) {
                        $mapping = unserialize($config);
                        // Loop mapping
                        foreach ($mapping as $map) {
                            $this->_storeIdsForMarketplaces[$map['pdm']] = (int) $store->getId();
                        }
                    }
                }
            } else {
                $logger->err("Country code not found");
            }
        }

        $storeId = (isset($this->_storeIdsForMarketplaces[$marketplace])) ? $this->_storeIdsForMarketplaces[$marketplace] : false;
        $logger->result($storeId, "Store ID")->down();
        return $storeId;
    }

    /**
     * Retrieve the status when we can invoice the order
     * @return array
     */
    public function getInvoiceStatus()
    {
        return explode(',', Mage::getStoreConfig('netevensync/order/invoice_status'));
    }

}

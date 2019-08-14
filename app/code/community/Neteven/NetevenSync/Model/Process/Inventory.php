<?php

/**
 * Inventory process model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Process_Inventory extends Neteven_NetevenSync_Model_Process_Abstract
{

    /**
     * Process type for this export
     *
     * @var string
     */
    protected $_processType = Neteven_NetevenSync_Model_Config::NETEVENSYNC_PROCESS_INVENTORY_CODE;
    /**
     * Attributes used for export
     *
     * @var array
     */
    protected $_attributes = array();
    /**
     * Category names of item
     *
     * @var array
     */
    protected $_categoryNames = array();
    /**
     * Current exported product
     *
     * @var Mage_Catalog_Model_Product
     */
    protected $_product;
    /**
     * Parent product of $_product
     * if $_product is simple with parent composite
     *
     * @var Mage_Catalog_Model_Product
     */
    protected $_parentProduct;
    /**
     * Mage_Catalog_Model_Product objects for
     * each mapped Neteven Language <--> Magento Store ID
     *
     * @var array
     */
    protected $_languagesProducts = array();
    /**
     * Specific fields needed to populate
     * ArrayOfSpecificFields in XML SOAP node
     *
     * @var array
     */
    protected $_specificFields = array();

    /**
     * Retrieve collection for export
     *
     * @param string $mode
     * @return Mage_Core_Model_Resource_Db_Collection_Abstract
     */
    public function getExportCollection($mode)
    {

        if (Mage::getStoreConfigFlag('netevensync/inventory/selected')) {
            $this->forceOutOfStockItems();
        }

        $collection = Mage::getModel('catalog/product')->getCollection();

        /**
         * Add product type filter
         */
        $collection->addFieldToFilter(
            'type_id',
            array(
                array('in' => Mage::getSingleton('netevensync/config')->getAvailableProductTypes()),
                array('null' => 1)
            )
        );

        /**
         * Filter by Neteven Selection depending on config
         */
        if (Mage::getStoreConfigFlag('netevensync/inventory/selected')) {
            $collection->getSelect()->joinRight(
                array('netevensync_product' => $collection->getTable('netevensync/product')),
                'netevensync_product.product_id = e.entity_id',
                array()
            );
        }

        /**
         * Add incremental filter if needed... + items to force as out of stock for incremental
         */
        if ($mode == Neteven_NetevenSync_Model_Config::NETEVENSYNC_EXPORT_INCREMENTAL) {
            $collection->getSelect()->joinRight(
                array('netevensync_inventory' => $collection->getTable('netevensync/inventory')),
                'netevensync_inventory.product_id = e.entity_id',
                array('product_id', 'sku', 'to_delete')
            );

            //$collection->getSelect()->where('e.entity_id IS NOT NULL AND netevensync_inventory.to_delete != 1 OR e.entity_id IS NULL AND netevensync_inventory.to_delete = 1');
        }

        /**
         * Add items to force as out of stock for full export
         * @TODO Optimize with union in order to avoid catalog collection load
         */
        if ($mode == Neteven_NetevenSync_Model_Config::NETEVENSYNC_EXPORT_FULL && Mage::getStoreConfigFlag(
                'netevensync/inventory/selected'
            )
        ) {
            $inventoryCollection = $this->getCollection()->addFieldToFilter('to_delete', '1');

            if ($inventoryCollection->count()) {
                $inventoryCollection = $inventoryCollection->toArray(array('product_id'));
                $inventoryCollectionIds = array();
                foreach ($inventoryCollection['items'] as $item) {
                    $inventoryCollectionIds[$item['product_id']] = $item['product_id'];
                }

                $collectionIds = $collection->getAllIds();
                $allIds = array_merge($inventoryCollectionIds, $collectionIds);
                $allIds = array_unique($allIds);

                $collection = Mage::getModel('catalog/product')->getCollection()
                    ->addFieldToFilter('entity_id', array('in' => $allIds));
            }
        }

        /**
         * Add stock information
         */
        $collection->getSelect()->joinLeft(
            array('stock' => $collection->getTable('cataloginventory/stock_item')),
            'stock.product_id = e.entity_id',
            array('qty', 'use_config_min_qty', 'min_qty')
        );

        /**
         * Add "real" 'available_qty' column with value depending on product config:
         * - if use config for min qty is checked => available_qty = product qty - config min qty
         * - if use config for min qty is *not* checked => available_qty = product qty - product min qty
         */
        $configMinValue = Mage::getStoreConfig('cataloginventory/item_options/min_qty');
        $collection->getSelect()->columns(
            array(
                'available_qty' => new Zend_Db_Expr(
                    "IF(use_config_min_qty > 0, (qty) - {$configMinValue}, (qty) - (min_qty))"
                )
            )
        );

        return $collection;
    }

    /**
     * Force items that are not in Neteven Selection to be deleted on Neteven platform
     *
     * @return Neteven_NetevenSync_Model_Process_Inventory
     */
    public function forceOutOfStockItems()
    {
        $config = Mage::getModel('core/config_data')->getCollection()
            ->addFieldToFilter('path', 'netevensync/force_stock')
            ->getFirstItem();

        if ($config && $config->getValue() == 1) {
            $selectionCollection = Mage::getModel('netevensync/product')->getCollection()->toArray(array('product_id'));
            $selectionIds = array();

            foreach ($selectionCollection['items'] as $item) {
                $selectionIds[$item['product_id']] = $item['product_id'];
            }

            $itemsToForce = Mage::getModel('catalog/product')->getCollection()
                ->addFieldToFilter(
                    'type_id',
                    array(
                        array('in' => Mage::getSingleton('netevensync/config')->getAvailableProductTypes()),
                        array('null' => 1)
                    )
                );

            if (count($selectionIds) > 0) {
                $itemsToForce->addFieldToFilter('entity_id', array('nin' => $selectionIds));
            }

            foreach ($itemsToForce as $product) {
                $this->setId(null);
                $this->loadByProductId($product->getEntityId());
                $this->setProductId($product->getEntityId())
                    ->setSku($product->getSku())
                    ->setToDelete(true)
                    ->save();
            }

            $config->setValue(0)->save();
        }

        return $this;
    }

    /**
     * Load by product id
     *
     * @param int $productId
     * @return Neteven_NetevenSync_Model_Process_Inventory
     */
    public function loadByProductId($productId)
    {
        $this->addData($this->getResource()->loadByProductId($productId));
        return $this;
    }

    /**
     * Prepare item for export
     *
     * @param mixed $item
     * @return mixed $preparedItem
     */
    public function prepareExportItem($item)
    {

        // Do not export items with invalid SKU
        $sku = Mage::helper('netevensync')->checkSku($item->getData('sku'), $this->_processType);
        if (!$sku) {
            return false;
        }

        // Do not export items with available_qty to 0
        if (!Mage::getStoreConfigFlag('netevensync/inventory/stock') && $item->getData(
                'available_qty'
            ) == 0 && !$item->getToDelete()
        ) {
            return false;
        }

        if (Mage::getStoreConfigFlag('netevensync/inventory/selected')) {
            if ($item->getEntityId()) {
                $productId = $item->getEntityId();
            } else {
                $productId = $item->getProductId();
            }
            $this->setId(null);
            $this->loadByProductId($productId);

            if ($this->getToDelete()) {
                $item->setToDelete(true);
            }
        }

        if ($item->getToDelete()) {
            $quantity = '0.0000';
        } else {
            $quantity = ($item->getData('available_qty') > 0) ? $item->getData('available_qty') : '0.0000';
        }

        $preparedItem = array(
            'SKU' => $sku,
            'Quantity' => $quantity,
        );

        if (!$item->getToDelete()) {
            $product = Mage::getModel('catalog/product')->load($item->getEntityId());
            $this->_product = $product;

            // If product is not visible and has parent configurable product(s), we set data to $_parentProduct variable
            if ($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_SIMPLE
                && $product->getVisibility() == Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE
            ) {
                $parentIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild(
                    $product->getId()
                );
                if (is_array($parentIds) && isset($parentIds[0])) {
                    $this->_parentProduct = Mage::getModel('catalog/product')->load($parentIds[0]);
                }
            }

            // Load other store views data for $_product and $_parentProduct
            $this->_populateLanguagesProducts();

            // Inject data into item
            $preparedItem = $this->_getItemData($preparedItem, 'title', 'name');
            $preparedItem = $this->_getItemData($preparedItem, 'sub_title', 'short_description');
            $preparedItem = $this->_getItemData($preparedItem, 'description', 'description', null);
            $preparedItem = $this->_getItemData($preparedItem, 'comment', 'model');
            $preparedItem['Cost'] = round($this->_product->getCost(), 2);
            $preparedItem['Classification'] = $this->getCategoryPath();
            $preparedItem = $this->_getItemData($preparedItem, 'weight', 'weight', 'float');
            $preparedItem['Tva'] = $this->getTaxRate();
            $preparedItem['Ecotaxe'] = $this->getWeee();
            // @deprecated
            //$preparedItem['PriceFixed'] = $this->getFinalPrice();
            $preparedItem = $this->_getImages($preparedItem);
            $preparedItem = $this->_getMappedFieldsData($preparedItem);
            $preparedItem = $this->_getSpecificFieldsData($preparedItem);
        }

        return $preparedItem;
    }

    /**
     * Store locale data for products
     *
     * @return $this
     */
    protected function _populateLanguagesProducts()
    {
        $languages = $this->_getLanguages();

        foreach ($languages as $storeId => $language) {
            if ($language) {
                $product = Mage::getModel('catalog/product')
                    ->setStoreId($storeId)
                    ->load($this->_product->getId());

                $this->_languagesProducts[$storeId][$product->getId()] = $product;

                if (!is_null($this->_parentProduct)) {
                    $parentProduct = Mage::getModel('catalog/product')
                        ->setStoreId($storeId)
                        ->load($this->_parentProduct->getId());

                    $this->_languagesProducts[$storeId][$parentProduct->getId()] = $parentProduct;
                }
            }
        }

        return $this;
    }

    /**
     * Retrieve store ID <--> language associations
     *
     * @return array
     */
    protected function _getLanguages()
    {
        $languages = Mage::getSingleton('netevensync/config')->getConfiguredInventoryLanguages();
        if (count($languages) == 0) {
            return array(false);
        }
        return $languages;
    }

    /**
     * Retrieve item data and add language data when necessary
     *
     * @param $preparedItem
     * @param $netevenCode
     * @param $attributeCode
     * @param string $type
     * @return mixed
     */
    protected function _getItemData($preparedItem, $netevenCode, $attributeCode, $type = 'string')
    {
        // If we are working on SKUfamily with _automatic value, use parent sku
        $useParent = false;
        if (
            $netevenCode == Neteven_NetevenSync_Model_Config::INVENTORY_SKUFAMILY_CODE
            && $attributeCode == Neteven_NetevenSync_Model_Config::INVENTORY_SKUFAMILY_AUTOMATIC_KEY
        ) {
            $useParent = true;
            $attributeCode = 'sku';
        }

        // Patch - Neteven needs to receive "SKUFamily" case sensitive instead of "SkuFamily" value in WS
        $usedNetevenCode = uc_words($netevenCode, '');
        if ($netevenCode != 'sku_family') {
            $usedNetevenCode = 'SKUFamily';
        }

        $preparedItem[$usedNetevenCode] = $this->getFormattedValue($type, $attributeCode, false, $useParent);

        /**
         * If export is configured for several languages (store IDs),
         * each localized data must be sent in the ArrayOfSpecificFields node to Neteven WS
         * We then add a specific field for each language / store ID
         */
        $languages = $this->_getLanguages();
        foreach ($languages as $storeId => $language) {
            if ($language && strpos($netevenCode, 'price') === false) { // If dealing with price attribute, do nothing
                $value = $this->getFormattedValue($type, $attributeCode, $storeId, $useParent);
                $this->_addSpecificField($netevenCode, $value, $language);
            }
        }

        return $preparedItem;
    }

    /**
     * Retrieve formatted value from attribute code
     *
     * @param $type
     * @param $attributeCode
     * @param int|bool $storeId
     * @param bool $useParent
     * @return float|string
     */
    public function getFormattedValue($type, $attributeCode, $storeId = false, $useParent = false)
    {
        switch ($type) {
            case 'string':
                $value = substr(
                    Mage::helper('core')->stripTags(
                        $this->_getAttributeValue($attributeCode, false, $storeId, $useParent)
                    ),
                    0,
                    255
                );
                break;
            case 'float':
                $value = round($this->_getAttributeValue($attributeCode, false, $storeId, $useParent), 2);
                break;
            default:
                $value = $this->_getAttributeValue($attributeCode, false, $storeId, $useParent);
                break;
        }

        return $value;
    }

    /**
     * Retrieve attribute value
     *
     * @param bool $withLabel
     * @param string $attributeCode
     * @param int|bool $storeId
     * @param bool $useParent
     * @return string
     */
    protected function _getAttributeValue($attributeCode, $withLabel = false, $storeId = false, $useParent = false)
    {
        $value = $this->_product->getData($attributeCode);
        $usedProduct = $this->_product;

        // If there is no data for this product on this attribute, or if we force to $useParent, try with $_parentProduct
        if (
            !$value && $this->_parentProduct && Mage::getStoreConfigFlag('netevensync/inventory/parent_data')
            || $useParent && $this->_parentProduct
        ) {
            $value = $this->_parentProduct->getData($attributeCode);
            $usedProduct = $this->_parentProduct;
        }

        // But if there are several languages, we must be sure to use the right localized data
        if ($storeId !== false && count($this->_languagesProducts) > 0) {
            $usedProduct = $this->_languagesProducts[$storeId][$usedProduct->getId()];
            $value = $usedProduct->getData($attributeCode);
        }

        // No value? No value!
        if (!$value) {
            return '';
        }

        /**
         * Once we have a correct product to use
         * and we are sure we have data for current attribute,
         * spit properly formatted data for attribute
         */
        $attribute = $this->getAttribute($attributeCode, $usedProduct);

        if (!$attribute) {
            return '';
        }

        if ($storeId) {
            $attribute->setStoreId($storeId);
        }

        $isArray = false;
        $setValue = $value;
        $label = false;

        if ($withLabel) {
            $label = $attribute->getFrontend()->getLabel();
        }

        if ($attribute->getFrontendInput() == 'multiselect') {
            $value = explode(trim(Mage_Catalog_Model_Convert_Parser_Product::MULTI_DELIMITER), $value);
            $isArray = true;
            $setValue = array();
        }

        if ($attribute->usesSource()) {
            $options = $attribute->getSource()->getAllOptions(false);
            if ($isArray) {
                foreach ($options as $item) {
                    if (in_array($item['value'], $value)) {
                        $setValue[] = $item['label'];
                    }
                }
                $setValue = implode(trim(Mage_Catalog_Model_Convert_Parser_Product::MULTI_DELIMITER), $setValue);
            } else {
                $setValue = false;
                foreach ($options as $item) {
                    if (is_array($item['value'])) {
                        foreach ($item['value'] as $subValue) {
                            if (isset($subValue['value']) && $subValue['value'] == $value) {
                                $setValue = $item['label'];
                            }
                        }
                    } else {
                        if ($item['value'] == $value) {
                            $setValue = $item['label'];
                        }
                    }
                }
            }
        }

        if ($withLabel) {
            $attributeData = new Varien_Object;
            $attributeData->setLabel($label);
            $attributeData->setValue($setValue);
            return $attributeData;
        }

        return $setValue;
    }

    /**
     * Retrieve eav entity attribute model
     *
     * @param string $code
     * @param Mage_Catalog_Model $usedProduct
     * @return Mage_Eav_Model_Entity_Attribute
     */
    public function getAttribute($code, $usedProduct = null)
    {
        $product = $this->_product;

        if ($usedProduct) {
            $product = $usedProduct;
        }

        if (!isset($this->_attributes[$code])) {
            $this->_attributes[$code] = $product->getResource()->getAttribute($code);
        }

        if ($this->_attributes[$code] instanceof Mage_Catalog_Model_Resource_Eav_Attribute) {
            $applyTo = $this->_attributes[$code]->getApplyTo();
            if ($applyTo && !in_array($product->getTypeId(), $applyTo)) {
                return false;
            }
        }
        return $this->_attributes[$code];
    }

    /**
     * Add specific field
     *
     * @param string $netevenCode
     * @param mixed $value
     * @param string $lang
     * @return $this
     */
    protected function _addSpecificField($netevenCode, $value, $lang)
    {
        $this->_specificFields[] = array('Name' => $netevenCode, 'Value' => $value, 'lang' => $lang);
        return $this;
    }

    /**
     * Retrieve category path as breadcrumb
     *
     * @return string $categoryPath
     */
    public function getCategoryPath()
    {

        $categoryIds = $this->_product->getCategoryIds();

        if ($this->_parentProduct && count($categoryIds) < 1 && Mage::getStoreConfigFlag(
                'netevensync/inventory/parent_data'
            )
        ) {
            $categoryIds = $this->_parentProduct->getCategoryIds();
        }

        if (count($categoryIds) < 1) {
            return '';
        }

        // Loop thru categories and use the one with the deepest path
        $deepestPath = 0;
        $pathArr = array();
        foreach ($categoryIds as $categoryId) {
            $path = Mage::getModel('catalog/category')->load($categoryId)->getPath();
            $arr = explode('/', $path);
            if (count($arr) > $deepestPath) {
                $deepestPath = count($arr);
                $pathArr = $arr;
            }
        }

        // Yes, double array shift to remove category #1 + root category
        array_shift($pathArr);
        array_shift($pathArr);

        $bcArr = array();

        foreach ($pathArr as $categoryId) {
            $bcArr[] = $this->getCategoryName($categoryId);
        }

        $categoryPath = implode('/', $bcArr);
        return $categoryPath;
    }

    /**
     * Retrieve category name
     *
     * @param int $categoryId
     * @return string $categoryName
     */
    public function getCategoryName($categoryId)
    {
        if (!isset($this->_categoryNames[$categoryId])) {
            $this->_categoryNames[$categoryId] = Mage::getModel('catalog/category')->load($categoryId)->getName();
        }
        return $this->_categoryNames[$categoryId];
    }

    /**
     * Retrieve Product Tax Rate
     *
     * @return float $taxRate
     */
    public function getTaxRate()
    {
        $request = Mage::getSingleton('tax/calculation')->getRateOriginRequest();
        $percent = Mage::getSingleton('tax/calculation')->getRate(
            $request->setProductClassId($this->_product->getTaxClassId())
        );

        if ((!$percent || $percent == 0) && $this->_parentProduct && Mage::getStoreConfigFlag(
                'netevensync/inventory/parent_data'
            )
        ) {
            $percent = Mage::getSingleton('tax/calculation')->getRate(
                $request->setProductClassId($this->_parentProduct->getTaxClassId())
            );
        }

        return $percent;
    }

    /**
     * Retrieve Product Weee amount
     *
     * @return float $weee
     */
    public function getWeee()
    {
        $weee = Mage::getModel('weee/tax')->getWeeeAmount($this->_product, null, null, null, false, true);

        if ((!$weee || $weee == 0) && $this->_parentProduct && Mage::getStoreConfigFlag(
                'netevensync/inventory/parent_data'
            )
        ) {
            $weee = Mage::getModel('weee/tax')->getWeeeAmount($this->_parentProduct, null, null, null, false, true);
        }
        return $weee;
    }

    /**
     * Retrieve Product Final Price including Catalog Price Rules
     *
     * @deprecated
     * @see Mage_CatalogRule_Model_Observer::processAdminFinalPrice for rule_data registry
     * @return float
     */
    public function getFinalPrice()
    {
        /*
         * Commented because unused by Neteven which needs only full price
         *
        if(!Mage::registry('rule_data')) {
            $ruleData = new Varien_Object;
            $ruleData->setWebsiteId(Mage::app()->getDefaultStoreView()->getWebsiteId());
            $ruleData->setCustomerGroupId(0); // 0 for NOT_LOGGED_IN
            Mage::register('rule_data', $ruleData);
        }

        return Mage::helper('tax')->getPrice($this->_product, $this->_product->getFinalPrice(), true);
        */

        $price = Mage::helper('tax')->getPrice($this->_product, $this->_product->getPrice(), true);

        if ((!$price || $price == 0) && $this->_parentProduct && Mage::getStoreConfigFlag(
                'netevensync/inventory/parent_data'
            )
        ) {
            $price = Mage::helper('tax')->getPrice($this->_parentProduct, $this->_parentProduct->getPrice(), true);
        }

        return $price;
    }

    /**
     * Populate images
     *
     * @param array $preparedItem
     * @return array $preparedItem
     */
    protected function _getImages($preparedItem)
    {
        $maxAdditionalImageCount = 5;
        $additionalImageCountIndex = 1;
        $imageIndex = 2;

        $product = $this->_product;
        if ((!$product->getImage() || $product->getImage() == '' || $product->getImage(
                ) === 'no_selection') && $this->_parentProduct && Mage::getStoreConfigFlag(
                'netevensync/inventory/parent_data'
            )
        ) {
            $product = $this->_parentProduct;
        }

        $gallery = $product->getMediaGallery();
        $preparedItem['images'][1] = ($product->getImage() === 'no_selection') ? '' : $product->getImage();

        foreach ($gallery['images'] as $image) {
            if ($image['file'] == $preparedItem['images'][1] || $additionalImageCountIndex > $maxAdditionalImageCount) {
                continue;
            }
            $preparedItem['images'][$imageIndex] = $image['file'];
            $additionalImageCountIndex++;
            $imageIndex++;
        }

        foreach ($preparedItem['images'] as $index => $file) {
            $preparedItem['Image' . $index] = Mage::getSingleton('catalog/product_media_config')->getMediaUrl($file);
        }

        unset($preparedItem['images']);

        return $preparedItem;
    }

    /**
     * Retrieve mapped fields data
     *
     * @param array $preparedItem
     * @return array $preparedItem
     */
    protected function _getMappedFieldsData($preparedItem)
    {
        $specificFields = Mage::getConfig()->getNode('netevensync/specific_fields')->asArray();

        foreach ($specificFields as $code => $label) {
            if ($attributeCode = Mage::getStoreConfig('netevensync/inventory/' . $code)) {
                $preparedItem = $this->_getItemData($preparedItem, $code, $attributeCode);
            }
        }

        return $preparedItem;
    }

    /**
     * Retrieve specific fields data
     *
     * @param array $preparedItem
     * @return array $preparedItem
     */
    protected function _getSpecificFieldsData($preparedItem)
    {

        // First, populate array with specific fields added earlier
        // @see _getItemData()
        $specificFields = $this->_getSpecificFields();
        if (count($specificFields) > 0) {
            $preparedItem['ArrayOfSpecificFields'] = $specificFields;
        }

        // Find attributes that are mapped as specific fields in system config
        // and inject there data into ArrayOfSpecificFields
        $attributes = Mage::getStoreConfig('netevensync/inventory/attribute');
        if (!$attributes) {
            return $preparedItem;
        }
        $attributesArr = explode(',', $attributes);

        foreach ($attributesArr as $attributeCode) {

            /**
             * Loop thru languages array anyway.
             * If there is no Neteven Language <--> Magento Store ID mapping
             * the fist $languages item is "false"
             */
            $languages = $this->_getLanguages();
            foreach ($languages as $storeId => $language) {
                $storeId = ($language) ? $storeId : false;
                $data = $this->_getAttributeValue($attributeCode, true, $storeId);
                if ($data != '') {
                    $value = $data->getValue();
                    if (is_array($value)) {
                        if (isset($value[0]['value'])) {
                            $value = $value[0]['value'];
                        } else {
                            continue;
                        }
                    }
                    if (!$language) {
                        $specificFields[] = array('Name' => $data->getLabel(), 'Value' => $value);
                    } else {
                        $specificFields[] = array('Name' => $data->getLabel(), 'Value' => $value, 'lang' => $language);
                    }
                }
            }
        }

        if (count($specificFields) > 0) {
            $preparedItem['ArrayOfSpecificFields'] = $specificFields;
        }

        return $preparedItem;
    }

    /**
     * Retrieve specific fields
     *
     * @return mixed
     */
    protected function _getSpecificFields()
    {
        return $this->_specificFields;
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
        $result = $soapClient->processPostItems($items, $this->_processType);
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
        $this->getCollection()
            ->walk('delete');
        return true;
    }

    /**
     * Register increment
     *
     * @param Mage_Catalog_Model_Product || int $product
     * @param bool $fromProductSave
     */
    public function registerIncrement($product, $fromProductSave = false)
    {

        if (!$product instanceof Mage_Catalog_Model_Product) {
            if (!is_numeric($product)) {
                $productId = $product->getId();
            } else {
                $productId = $product;
            }
            $product = Mage::getModel('catalog/product')->load($productId);
        }

        $this->setId(null);
        $this->loadByProductId($product->getId());

        if (!$product->getSku()) {
            $model = Mage::getModel('catalog/product')->load(
                $product->getId()
            ); // This is needed for increments called by Neteven_NetevenSync_Model_Observer::registerMultiIncrement
            $product->setSku($model->getSku());
        }

        // If product is configurable and export of configurable parent data is enabled, register all children products
        if (Mage::getStoreConfigFlag('netevensync/inventory/parent_data') && $product->getTypeId(
            ) == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE
        ) {
            $simpleProductIds = Mage::getModel('catalog/product_type_configurable')->getChildrenIds($product->getId());
            foreach ($simpleProductIds[0] as $simpleProductId) {
                $this->registerIncrement((int)$simpleProductId, $fromProductSave);
            }
        }

        if (!in_array($product->getTypeId(), $this->getConfig()->getAvailableProductTypes())) {
            return;
        }

        $this->setSku($product->getSku());
        $this->setProductId($product->getId());

        if ($fromProductSave && Mage::getStoreConfigFlag('netevensync/inventory/selected') && $this->getToDelete()) {
            $this->setToDelete(true);
        } else {
            if ($product->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_ENABLED) {
                $this->setToDelete(false);
            }

            if ($product->getToDelete() || $product->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_DISABLED
            ) {
                $this->setToDelete(true);
            }
        }

        $this->save();
    }
}
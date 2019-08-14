<?php
/**
 * Abstract process model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
abstract class Neteven_NetevenSync_Model_Process_Abstract extends Mage_Core_Model_Abstract
{

    protected $_processType;
    protected $_config;
    protected $_itemsProcessed = 0;

    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init('netevensync/process_' . $this->_processType);
    }

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
     * Run process
     *
     * @param string $mode
     * @param mixed $processedPage
     * @param mixed $fromAjax
     * @return bool $success
     */
    public function runProcess($mode, $processedPage = null, $fromAjax = false, $dir)
    {

        $success    = true;
        $collection = $this->getProcessCollection($mode, $fromAjax, true, $dir);

        if ($collection && $collection->count()) {
            for ($page = 1; $page <= $collection->getLastPageNumber(); $page++) {
                if ($processedPage && $page == $processedPage || !$processedPage) {
                    $success = $this->processPage($page, $collection, $dir, $mode);
                }
            }
        }

        if ($fromAjax) {
            return array('success' => $success, 'items_processed' => $this->_itemsProcessed);
        }

        // If we're not in AJAX context, we finish the process
        $success = $this->finishProcess($mode, $dir);

        if ($success) {
            Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('netevensync')->__('Process "%s" has been successfully executed.', $this->_processType));
        } else {
            Mage::getSingleton('adminhtml/session')->addNotice(Mage::helper('netevensync')->__('Not all items have been processed for "%s" process.', $this->_processType));
        }

        return $success;
    }

    /**
     * Run process by page
     * 
     * @param int $page
     * @param Mage_Core_Model_Resource_Db_Collection_Abstract
     * @param string $dir
     * @param string $mode
     * @return bool $success
     */
    public function processPage($page, $collection, $dir, $mode)
    {
        $success = true;

        $collection->clear();
        $collection->setCurPage($page);

        if ($collection->count()) {
            $itemsToProcess = array();
            foreach ($collection as $item) {
                if ($preparedItem = $this->prepareItem($item, $dir)) {
                    $itemsToProcess[] = $preparedItem;
                }
            }
            if (count($itemsToProcess) > 0) {
                try {
                    $result                = $this->processItems($itemsToProcess, $dir);
                    $success               = $result['success'];
                    $this->_itemsProcessed = $this->_itemsProcessed + $result['success_items_count'];
                } catch (Exception $e) {
                    $success = false;
                    Mage::helper('netevensync')->log($e, $this->_processType);
                }
            }
        }

        return $success;
    }

    /**
     * Finish process
     *
     * @param string $mode
     * @param string $dir
     * @param bool $fromAjax
     * @return bool
     */
    public function finishProcess($mode, $dir, $fromAjax = false)
    {
        $success = true;

        switch ($dir) {
            case Neteven_NetevenSync_Model_Config::NETEVENSYNC_DIR_IMPORT:
                $success = $this->finishImportProcess($mode);
                break;

            case Neteven_NetevenSync_Model_Config::NETEVENSYNC_DIR_EXPORT:
                $success = $this->finishExportProcess($mode);
                break;
        }

        // Update last sync date
        $lastSync = date('Y-m-d H:i:s', Mage::getModel('core/date')->timestamp(time()));
        Mage::getModel('netevensync/config_process')->loadByProcessCode($this->_processType)
                ->setLastSync($lastSync)
                ->save();

        return $success;
    }

    /**
     * Retrieve process collection
     *
     * @param string $mode
     * @param bool $addChunk
     * @param bool $fromAjax
     * @param string $dir
     * @return Mage_Core_Model_Resource_Db_Collection_Abstract
     */
    public function getProcessCollection($mode, $fromAjax, $addChunk = true, $dir)
    {

        switch ($dir) {
            case Neteven_NetevenSync_Model_Config::NETEVENSYNC_DIR_IMPORT:
                $collection = $this->getImportCollection($mode);
                break;

            case Neteven_NetevenSync_Model_Config::NETEVENSYNC_DIR_EXPORT:
                $collection = $this->getExportCollection($mode);
                break;
        }

        if (!$collection instanceof Varien_Data_Collection && !$fromAjax) {
            // In this case, $collection is a string
            Mage::throwException($collection);
        }

        if ($collection && $addChunk) {
            $collection = $this->addChunk($collection, $fromAjax, $dir);
        }

        return $collection;
    }

    /**
     * Retrieve collection for import
     *
     * @param string $mode
     * @return Varien_Data_Collection
     */
    public function getImportCollection($mode)
    {
        return new Varien_Data_Collection();
    }

    /**
     * Retrieve collection for export
     *
     * @param string $mode
     * @return Varien_Data_Collection
     */
    public function getExportCollection($mode)
    {
        return new Varien_Data_Collection();
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
        if ($fromAjax) {
            $collection->setPageSize(Neteven_NetevenSync_Model_Config::NETEVENSYNC_CHUNK_SIZE_AJAX);
        } else {
            $collection->setPageSize(Neteven_NetevenSync_Model_Config::NETEVENSYNC_CHUNK_SIZE);
        }
        return $collection;
    }

    /**
     * Prepare item for process
     * 
     * @param mixed $item
     * @param string $dir
     * @return mixed $preparedItem
     */
    public function prepareItem($item, $dir)
    {
        $preparedItem = false;

        switch ($dir) {
            case Neteven_NetevenSync_Model_Config::NETEVENSYNC_DIR_IMPORT:
                $preparedItem = $this->prepareImportItem($item);
                break;

            case Neteven_NetevenSync_Model_Config::NETEVENSYNC_DIR_EXPORT:
                $preparedItem = $this->prepareExportItem($item);
                break;
        }

        return $preparedItem;
    }

    /**
     * Prepare item for import
     *
     * @param mixed $item
     * @return mixed $preparedItem
     */
    public function prepareImportItem($item)
    {
        $preparedItem = false;
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
        $preparedItem = false;
        return $preparedItem;
    }

    /**
     * Process items
     *
     * @param array $items
     * @return array $result
     */
    public function processItems($items, $dir)
    {
        $result = array('success' => true, 'success_items_count' => count($items));

        switch ($dir) {
            case Neteven_NetevenSync_Model_Config::NETEVENSYNC_DIR_IMPORT:
                $result = $this->processImportItems($items);
                break;

            case Neteven_NetevenSync_Model_Config::NETEVENSYNC_DIR_EXPORT:
                $result = $this->processExportItems($items);
                break;
        }

        return $result;
    }

    /**
     * Process items for import
     *
     * @param array $items
     * @return array $result
     */
    public function processImportItems($items)
    {
        $result = array('success' => true, 'success_items_count' => count($items));
        return $result;
    }

    /**
     * Process items for import
     *
     * @param array $items
     * @return array $result
     */
    public function processExportItems($items)
    {
        $result = array('success' => true, 'success_items_count' => count($items));
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
        $success = true;
        return $success;
    }

    /**
     * Finish export
     *
     * @param string $mode
     * @return bool $success
     */
    public function finishExportProcess($mode)
    {
        $success = true;
        return $success;
    }

}

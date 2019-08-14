<?php
/**
 * Admin controller
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Adminhtml_NetevensyncController extends Mage_Adminhtml_Controller_Action {
	
	//////////////
	////////////// System Config
	//////////////
	
	/**
	 * Test general configuration
	 */
	public function testConfigurationAction() {
		
		$soapClient = Mage::getSingleton('netevensync/soap');
		
		try {
			$soapClient->testWsConnection();
			Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('netevensync')->__('Setup has been successfully validated.'));
		}
		catch(Exception $e) {
			Mage::getSingleton('adminhtml/session')->addError(Mage::helper('netevensync')->__('Setup has failed. Neteven WS sent the following message: "%s".', $e->getMessage()));
		}
		
		$this->_redirect('*/system_config/edit/section/netevensync');
	}
	
	//////////////
	////////////// Logging
	//////////////
	
	/**
	 * Clean errors log
	 */
	public function cleanLogAction() {
		$collection = Mage::getModel('netevensync/log')->getCollection()->addErrorFilter();
		foreach($collection as $logType) {
			$logType->setHasError(false);
			$logType->save();
		}
		$this->_redirectReferer();
	}
	
	//////////////
	////////////// Neteven Console
	//////////////
	
	/**
	 * Display console in an iframe
	 */
	public function consoleAction() {
		$this->_title(Mage::helper('netevensync')->__('Neteven Console'))->_title($this->__('Catalog'));
		$this->loadLayout();
	
		$html = '<iframe src="' . Mage::getStoreConfig('netevensync/console/url') . '" frameborder="0" style="display: block; width: 100%; height: 1000px; margin: auto auto"></iframe>';
		$this->_addContent(
				$this->getLayout()->createBlock('core/text', 'neteven_console', array('text' => $html))
		);
	
		$this->renderLayout();
	}
	
	//////////////
	////////////// Processes
	//////////////
	
	protected $_type;
	protected $_mode;
	protected $_processCollections = array();
	
	/**
	 * Run process from Admin
	 */
	public function runProcessAction() {
		$this->loadLayout();
		$exportBlock = $this->getLayout()->getBlock('netevensync.process.run');
		$exportBlock->setType($this->getRequest()->getParam('type'));
		$exportBlock->setMode($this->getRequest()->getParam('mode'));
		
		$from = $this->getRequest()->getParam('from');
		if($from) {
			Mage::getSingleton('adminhtml/session')->setNetevenSyncFrom($from);
		}
		
		$this->renderLayout();
	}
	
	/**
	 * Launch AJAX processes and count items and pages for each process type
	 */
	public function launchProcessesAction() {
		$response = array();
		
		// Check credentials
		$soapClient = Mage::getModel('netevensync/soap');
		try {
			$soapClient->testWsConnection();
		}
		catch(Exception $e) {
			$response['error'] = Mage::helper('netevensync')->__('Unable to connect to Neteven WS. Please check your credentials.');
			$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
			return;
		}
	
		$this->_type = $this->getRequest()->getParam('type');
		$this->_mode = $this->getRequest()->getParam('mode');
	
		$importItemCount = $this->getItemsCount(Neteven_NetevenSync_Model_Config::NETEVENSYNC_DIR_IMPORT);
		$exportItemCount = $this->getItemsCount(Neteven_NetevenSync_Model_Config::NETEVENSYNC_DIR_EXPORT);
		$importPageCount = $this->getLastPageNumber(Neteven_NetevenSync_Model_Config::NETEVENSYNC_DIR_IMPORT);
		$exportPageCount = $this->getLastPageNumber(Neteven_NetevenSync_Model_Config::NETEVENSYNC_DIR_EXPORT);
	
		if(
				!is_int($importItemCount)
				|| !is_int($exportItemCount)
		) {
			if(is_string($importItemCount)) {
				$response['error'] = $importItemCount;
			}
			else {
				$response['error'] = Mage::helper('netevensync')->__('Error while getting collection.');
			}
		}
	
		else {
			$response = array(
					'importItemCount'	=> $importItemCount,
					'exportItemCount'	=> $exportItemCount,
					'importPageCount'	=> $importPageCount,
					'exportPageCount'	=> $exportPageCount,
					'message'			=> Mage::helper('netevensync')->__('%s item(s) to process...', $importItemCount+$exportItemCount),
			);
		}
	
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
	}
	
	/**
	 * Run single page from AJAX
	 */
	public function runProcessPageAction() {
		$mode = $this->getRequest()->getParam('mode');
		$type = $this->getRequest()->getParam('type');
		$page = $this->getRequest()->getParam('page');
		$dir = $this->getRequest()->getParam('dir');
	
		$error = '';

        $model = Mage::getModel('netevensync/process_' . strtolower($type));
		$result = $model->runProcess($mode, $page, true, $dir);
	
		if(!$result['success']) {
			$error = Mage::helper('netevensync')->__('Errors while processing page %s. Please see var/log/neteven.log for details.', $page);
		}
	
		$result = array(
				'savedRows' => $result['items_processed'],
				'error'    => $error,
		);
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
	}
	
	/**
	 * Finish AJAX processes
	 */
	public function finishProcessesAction() {
		$response = array();
	
		$this->_type = $this->getRequest()->getParam('type');
		$this->_mode = $this->getRequest()->getParam('mode');
		$this->_dir = $this->getRequest()->getParam('dir');
	
		$success = $this->getProcessClass()->finishProcess($this->_mode, $this->_dir, true);
	
		if(!$success) {
			$response['error'] = Mage::helper('netevensync')->__('Errors while processing last operations. Please see var/log/neteven.log for details.');
		}
	
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
	}
	
	/**
	 * Retrieve process class model
	 *
	 * @return object
	 */
	public function getProcessClass() {
		return Mage::getModel('Neteven_NetevenSync_Model_Process_' . ucfirst($this->_type));
	}
	
	/**
	 * Retrieve process collection
	 *
	 * @param string dir
	 * @return object
	 */
	public function getProcessCollection($dir) {
		if(!isset($this->_processCollections[$dir])) {
			$this->_processCollections[$dir] = $this->getProcessClass()->getProcessCollection($this->_mode, true, false, $dir);
		}
		return $this->_processCollections[$dir];
	}
	
	/**
	 * Retrieve items to process count
	 *
	 * @param string $dir
	 * @return int
	 */
	public function getItemsCount($dir) {
		$collection = $this->getProcessCollection($dir);
	
		if(is_object($collection)) {
			return (int) $collection->count();
		}
		if(is_string($collection)) {
			return $collection;
		}
		return Mage::helper('netevensync')->__('Error while getting collection.');
	}
	
	/**
	 * Retrieve total pages number
	 *
	 * @param string $dir
	 * @return int
	 */
	public function getLastPageNumber($dir) {
		$lastPageNumber = 0;
	
		if($this->getItemsCount($dir)) {
			$collection = $this->getProcessCollection($dir);
			
			if(!is_object($collection)) {
				return $collection;
			}
				
			if($this->_type == Neteven_NetevenSync_Model_Config::NETEVENSYNC_PROCESS_ORDER_CODE
					&& $dir == Neteven_NetevenSync_Model_Config::NETEVENSYNC_DIR_IMPORT)
			{
				$lastPageNumber = $collection->count();
			}
				
			else {
				$collection->setPageSize(Neteven_NetevenSync_Model_Config::NETEVENSYNC_CHUNK_SIZE_AJAX);
				$lastPageNumber = ceil($collection->count() / Neteven_NetevenSync_Model_Config::NETEVENSYNC_CHUNK_SIZE_AJAX);
			}
		}
		
		return $lastPageNumber;
	}
	
	//////////////
	////////////// Neteven Selection
	//////////////
	
	/**
	 * Product selection main page
	 */
	public function productAction() {
		$this->_title(Mage::helper('netevensync')->__('Neteven Selection'))->_title($this->__('Catalog'));
		$this->loadLayout()
			->_setActiveMenu('catalog/netevensync/product')
			->_addBreadcrumb(Mage::helper('netevensync')->__('Neteven Selection'), Mage::helper('netevensync')->__('Neteven Selection'))
		;
		$this->renderLayout();
	}
	
	/**
	 * Product selection Exported Products AJAX init
	 */
	public function productExportedAction() {
		$this->loadLayout();
		$this->getLayout()
			->getBlock('netevensync.product.view.exported')
			->setCheckedProducts($this->getRequest()->getPost('netevensync_exported', null));
		$this->renderLayout();
	}
	
	/**
	 * Product selection Exported Products AJAX grid
	 */
	public function productExportedGridAction() {
		$this->loadLayout();
		$this->getLayout()
			->getBlock('netevensync.product.view.exported')
			->setCheckedProducts($this->getRequest()->getPost('netevensync_exported', null));
		$this->renderLayout();
	}
	
	/**
	 * Product selection Available Products AJAX init
	 */
	public function productAvailableAction() {
		$this->loadLayout();
		$this->getLayout()
			->getBlock('netevensync.product.view.available')
			->setCheckedProducts($this->getRequest()->getPost('netevensync_available', null));
		$this->renderLayout();
	}
	
	/**
	 * Product selection Available Products AJAX grid
	 */
	public function productAvailableGridAction() {
		$this->loadLayout();
		$this->getLayout()
			->getBlock('netevensync.product.view.available')
			->setCheckedProducts($this->getRequest()->getPost('netevensync_available', null));
		$this->renderLayout();
	}
	
	/**
	 * Remove products from selection
	 */
	public function removeProductsAction() {
		$post = $this->getRequest()->getPost('netevensync');
		$productIds = Mage::helper('adminhtml/js')->decodeGridSerializedInput($post['exported']);
		
		try {
			foreach($productIds as $productId) {
				$product = Mage::getModel('catalog/product')->load($productId);
				$product->setToDelete(true);
				Mage::getModel('netevensync/process_inventory')->registerIncrement($product);
			}
			
			Mage::getModel('netevensync/product')->getCollection()
				->addFieldToFilter('product_id', array('in' => $productIds))
				->walk('delete');
			
			Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('netevensync')->__('Products have been removed from Neteven selection.'));
		}
		catch(Exception $e) {
			Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
		}
		
		$this->_redirect('*/*/product');
	}
	
	/**
	 * Add products to selection
	 */
	public function addProductsAction() {
		$post = $this->getRequest()->getPost('netevensync');
		$productIds = Mage::helper('adminhtml/js')->decodeGridSerializedInput($post['available']);
		
		try {
			foreach($productIds as $productId) {
				Mage::getModel('netevensync/product')
					->setProductId($productId)
					->save();
				
				Mage::getModel('netevensync/process_inventory')->registerIncrement($productId);
			}
				
			Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('netevensync')->__('Products have been added to Neteven selection.'));
		}
		catch(Exception $e) {
			Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
		}
		
		$this->_redirect('*/*/product');
	}
}
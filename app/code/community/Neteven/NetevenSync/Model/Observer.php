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
class Neteven_NetevenSync_Model_Observer {
	
	/**
	 * Register increment based on its process type
	 * 
	 * @param Varien_Event_Observer $observer
	 */
	public function registerIncrement(Varien_Event_Observer $observer) {
		$args = Mage::helper('netevensync')->getObserverArgs($observer, get_class($this), __FUNCTION__);
		$object = $observer->getEvent()->getDataObject();
		
		/**
		 * Manage events that lead to object deletion on Neteven platform
		 */
		$deleteableEvents = array(
				'catalog_product_delete_after',
		);
		
		if(in_array($observer->getEvent()->getName(), $deleteableEvents)) {
			$object->setToDelete(true);
		}
		
		Mage::getModel('netevensync/process_' . $args->getProcessType())->registerIncrement($object, true);
	}
	
	/**
	 * Register increment for product attribute mass update
	 * 
	 * @param Varien_Event_Observer $observer
	 */
	public function registerMultiIncrement(Varien_Event_Observer $observer) {
		$attributes = $observer->getAttributesData();
		$productIds = $observer->getProductIds();
		
		foreach($productIds as $productId) {
			$object = new Varien_Object();
			$object->setId($productId);
			
			if(isset($attributes['status'])) {
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
	public function addNoticeConfigChange(Varien_Event_Observer $observer) {
		$config = $observer->getObject()->getData();
		$flagForceStock = false;
		
		if(isset($config['section']) && $config['section'] == 'netevensync') {
			$changedConfig = array();
			$groupsToCheck = array(
					'inventory' 	=> Mage::helper('netevensync')->__('Inventory Synchronization'),
					'stock' 		=> Mage::helper('netevensync')->__('Stocks Synchronization'),
			);
			
			foreach($groupsToCheck as $code => $label) {
				$currentConfig = Mage::getStoreConfig('netevensync/' . $code . '/selected');
				if(!isset($config['groups'][$code]['fields']['selected']['value'])) {
					continue;
				}
				$newConfig = $config['groups'][$code]['fields']['selected']['value'];
				if($currentConfig != $newConfig) {
					$changedConfig[$code] = $label;
					if($newConfig == 1) {
						$flagForceStock = true;
					}
				}
			}
			
			if(count($changedConfig) > 0) {
				Mage::getSingleton('adminhtml/session')->addNotice(Mage::helper('netevensync')->__('A full Neteven synchronization may be required for the following: %s.', implode(', ', $changedConfig)));
			}
			
			// Save config for forcing export of not-in-selection products as out-of-stock
			$path = 'netevensync/force_stock';
			$config = Mage::getModel('core/config_data')->getCollection()
				->addFieldToFilter('path', $path)
				->getFirstItem();
			
			$data = array(
					'path'		=> $path,
					'scope'		=> 'default',
					'scope_id'	=> 0,
					'value'		=> ($flagForceStock) ? 1 : 0, 
				);
			
			$config->setData($data);
			$config->save();
		}
	}
	
	
	/**
	 * Update next sync from each process
	 * 
	 * @param Varien_Event_Observer $observer
	 */
	public function updateNextSync(Varien_Event_Observer $observer) {
		$config = $observer->getObject()->getData();
		
		if(isset($config['section']) && $config['section'] == 'netevensync') {
			$processTypes = Mage::getModel('netevensync/config_process')->getCollection();
			foreach($processTypes as $processType) {
				$startTime = Mage::getStoreConfig('netevensync/' . $processType->getProcessCode() . '/start'); // In hours
				$todayStartTime = Mage::getModel('core/date')->timestamp(date('Y-m-d')) + $startTime * 3600;
				$now = Mage::getModel('core/date')->timestamp(time());
				$nextSyncTimestamp = ($todayStartTime <= $now) ? $todayStartTime + 86400 : $todayStartTime; // If today's start time has passed, next sync will be tomorrow at the same time
				
				$nextSync = date('Y-m-d H:i:s', $nextSyncTimestamp);
				
				$processType->setNextSync($nextSync)->save();
			}
		}
	}
}
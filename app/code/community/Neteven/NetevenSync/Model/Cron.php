<?php
/**
 * Cron / Mass run model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Cron {
	
	protected $_config;
	
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
	 * Run all processes
	 * 
	 * @return bool
	 */
	public function runAllProcesses() {
		$processTypes = $this->getConfig()->getProcessCodes();
		$dirs = $this->getConfig()->getDirs();
		$mode = Neteven_NetevenSync_Model_Config::NETEVENSYNC_EXPORT_INCREMENTAL;
		
		$success = true;
		
		foreach($processTypes as $processType) {

			if($this->canRunProcess($processType)) {
				$process = Mage::getModel('netevensync/config_process')->loadByProcessCode($processType);
				$process->setIsRunning(true)->save();				
				
				foreach($dirs as $dir) {
					$model = Mage::getModel('netevensync/process_' . $processType);
					$processSuccess = $model->runProcess($mode, null, false, $dir);					
					if(!$processSuccess) {
						$success = false;
					}
				}
				$this->_finishProcess($process);
			}
		}
		
		return $success;
	}
	
	/**
	 * Check if process can be launched
	 * 
	 * @param string $processType
	 * @return bool
	 */
	public function canRunProcess($processType) {
		if(!Mage::getStoreConfigFlag('netevensync/' . $processType . '/enable')) {
			return false;
		}
		$process = Mage::getModel('netevensync/config_process')->loadByProcessCode($processType);
		$nextSyncTimestamp = $process->getNextSyncTimestamp();
		$now = Mage::getModel('core/date')->timestamp(time());
		$isRunning = (bool) $process->getIsRunning();
		
		if($now >= $nextSyncTimestamp && !$isRunning) {
			return true;
		}
		
		return false;
	}
	
	/**
	 * Run last operations when process is done
	 * 
	 * @param Neteven_NetevenSync_Model_Config_Process 
	 */
	protected function _finishProcess($process) {
		$frequency = Mage::getStoreConfig('netevensync/' . $process->getProcessCode() . '/frequency'); // In hours
		$nextSyncTimestamp = Mage::getModel('core/date')->timestamp(time()) + $frequency * 3600;
		$nextSync = date('Y-m-d H:i:s', $nextSyncTimestamp);
		$lastSync = date('Y-m-d H:i:s', Mage::getModel('core/date')->timestamp(time()));
		
		$process->setIsRunning(false)
			->setNextSync($nextSync)
			->setLastSync($lastSync)
			->save();
	} 
}
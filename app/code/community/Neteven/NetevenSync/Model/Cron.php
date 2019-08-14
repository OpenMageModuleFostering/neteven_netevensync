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
    public function canRunProcess($processType)
    {
        if (!Mage::getStoreConfigFlag('netevensync/' . $processType . '/enable')) {
            return false;
        }

        // The process
        $process = Mage::getModel('netevensync/config_process')->loadByProcessCode($processType);

        // Is running?
        if ($process->getIsRunning()) {
            return false;
        }

        /*
         * Test dates in UTC only
         */
        $now               = time(); // UTC because Magento forces this timezone
        $startDatetime     = strtotime(Mage::getStoreConfig('netevensync/' . $processType . '/start_datetime')); // UTC
        $nextSyncTimestamp = $process->getNextSyncTimestamp(); // UTC

        if ($now < $startDatetime || $now < $nextSyncTimestamp) {
            return false;
        }

        /*
         * Test CRON schedule
         */
        $frequency = Mage::getStoreConfig('netevensync/' . $processType . '/frequency');
        switch ($frequency) {
            case '0.25':
                $expr = '*/15 * * * *';
                break;
            case '0.5':
                $expr = '*/30 * * * *';
                break;
            case '1':
                $expr = '0 * * * *';
                break;
            case '24':
                $expr = '0 0 * * *';
                break;
            default: /* 2, 4, 12 */
                $expr = sprintf('0 */%d * * *', $frequency);
                break;
        }
        $cron = Mage::getModel('cron/schedule');
        $cron->setCronExpr($expr);

        return $cron->trySchedule(time());
    }

    /**
     * Run last operations when process is done
     *
     * @param Neteven_NetevenSync_Model_Config_Process
     */
    protected function _finishProcess($process)
    {
        $process
            ->setIsRunning(false)
            ->setLastSync(Mage::getSingleton('core/date')->gmtDate())
            ->save()
        ;
    }

}
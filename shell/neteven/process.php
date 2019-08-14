<?php
/**
 * This file is part of Neteven_NetevenSync for Magento.
 *
 * @license All rights reserved
 * @author Jacques Bodin-Hullin <j.bodinhullin@monsieurbiz.com> <@jacquesbh>
 * @category Neteven
 * @package Neteven_NetevenSync
 * @copyright Copyright (c) 2015 Neteven (http://www.neteven.com/)
 */

require_once __DIR__ . '/../abstract.php';

/**
 * Shell script to run processes
 * @package Neteven_NetevenSync
 */
class Neteven_NetevenSync_Shell_Process extends Mage_Shell_Abstract
{
    /**
     * Code to run all processes
     * @const string
     */
    const PROCESS_ALL_CODE = "all";

    /**
     * Run Neteven processes
     */
    public function run()
    {
        try {
            // Get codes
            $codes = $this->_getProcessCodes();

            foreach ($codes as $code) {
                $this->log("Start… (process code: %s)", $code);

                // Get process
                $process = Mage::getModel('netevensync/config_process')->loadByProcessCode($code);
                $this->log("Set process as running");
                $process
                    ->setIsRunning(true)
                    ->save();

                // Configuration
                $config = Mage::getSingleton('netevensync/config');

                // Loop on directions
                foreach ($config->getDirs() as $dir) {
                    $this->log("Run process (%s direction)", $dir);

                    /* @var $model Neteven_NetevenSync_Model_Process_Abstract */
                    $model = Mage::getModel('netevensync/process_' . $code);

                    // Run the process
                    try {
                        $processSuccess = $model->runProcess(
                            Neteven_NetevenSync_Model_Config::NETEVENSYNC_EXPORT_INCREMENTAL,
                            null,
                            false,
                            $dir
                        );

                        if ($processSuccess) {
                            $this->log("Done");
                        } else {
                            $this->err("The process seems unfinished without error.");
                        }

                    } catch (Exception $e) {
                        $this->err($e->getMessage());
                    }
                }

                // Finish process
                $this->log("Set process as not running");
                $process
                    ->setIsRunning(false)
                    ->save()
                ;
            }
        } catch (Exception $e) {
            $this->err($e->getMessage());
        }
    }

    /**
     * Get code argument as array
     * @return array
     * @throws Exception If no code specified
     */
    protected function _getProcessCodes()
    {
        switch ($code = $this->getArg('code')) {
            case Neteven_NetevenSync_Model_Config::NETEVENSYNC_PROCESS_ORDER_CODE:
            case Neteven_NetevenSync_Model_Config::NETEVENSYNC_PROCESS_INVENTORY_CODE:
            case Neteven_NetevenSync_Model_Config::NETEVENSYNC_PROCESS_STOCK_CODE:
                return array($code);
            case self::PROCESS_ALL_CODE:
                return array(
                    Neteven_NetevenSync_Model_Config::NETEVENSYNC_PROCESS_ORDER_CODE,
                    Neteven_NetevenSync_Model_Config::NETEVENSYNC_PROCESS_INVENTORY_CODE,
                    Neteven_NetevenSync_Model_Config::NETEVENSYNC_PROCESS_STOCK_CODE,
                );
            default:
                throw new Exception("Please specify a process code.");
        }
    }

    /**
     * Retrieve Usage Help Message
     */
    public function usageHelp()
    {
        $scriptName = basename(__FILE__);
        return <<<USAGE
This script runs the Neteven Stock import/export processes.

Usage:  php -f $scriptName -- [options]

  help              This help
  --code [code]     Process code: order, stock, inventory, all.

USAGE;
    }

    /**
     * Log
     * @see sprintf
     */
    public function log()
    {
        fwrite(STDOUT, sprintf("[%s] LOG ", date('c')) . call_user_func_array("sprintf", func_get_args()) . "\n");
    }

    /**
     * Log Error
     * @see sprintf
     */
    public function err()
    {
        fwrite(STDERR, sprintf("[%s] ERR ", date('c')) . call_user_func_array("sprintf", func_get_args()) . "\n");
    }

}

$cron = new Neteven_NetevenSync_Shell_Process();
$cron->run();

<?php
/**
 * Main Helper class
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */

class Neteven_NetevenSync_Helper_Data extends Mage_Core_Helper_Abstract
{

    /**
     * Retrieve version number
     * @return string
     */
    public function getVersion()
    {
        $neteven = Mage::getConfig()->getModuleConfig("Neteven_NetevenSync");
        if ($neteven->version_name) {
            return $neteven->version_name;
        }
        return $neteven->version;
    }

    /**
     * Log message
     * 
     * @param string $message
     * @param bool|string $error Neteven log to mark as error in database
     * @param int $level See Zend_Log constants (if $message is an Exception we force it on CRITICAL)
     * @param string $logFilename
     */
    public function log($message, $error = false, $level = Zend_Log::ERR, $logFilename = 'neteven.log')
    {
        $forceLog = Mage::getStoreConfigFlag('netevensync/general/debug');
        if ($message instanceof Exception) {
            Mage::log("\n" . $message->__toString(), Zend_Log::CRIT, $logFilename, $forceLog);
        } else {
            Mage::log($message, $level, $logFilename, $forceLog);
        }

        if ($error) {
            $log = Mage::getModel('netevensync/log')->loadByCode($error);
            $log->setCode($error);
            $log->setHasError(true)->save();
        }
    }

    /**
     * Log debug message
     *
     * @param string $message
     */
    public function logDebug($message)
    {
        $this->log($message, false, Zend_Log::DEBUG, 'neteven_debug.log');
    }

    /**
     * Check if SKU is valid
     *
     * @param string $sku
     * @param string $processType
     * @return string | bool
     */
    public function checkSku($sku, $processType)
    {
        if (strlen($sku) > 50) {
            $this->log($this->__('SKU length must be max 50 letters for SKU %s', $sku), $processType);
            return false;
        }
        return $sku;
    }

    /**
     * Retrieve args node of observer config
     *
     * @param Varien_Event_Observer $observer
     * @return array $observerArgs
     */
    public function getObserverArgs(Varien_Event_Observer $observer, $callingClass, $callingMethod)
    {

        /**
         * Define vars
         */
        $usedObservers  = array();
        $observerArgs   = array();
        $eventObservers = array();

        /**
         * Load Magento config
         */
        $config = Mage::getConfig();

        /**
         * Retrieve all observers attached to the current observer's event
         */
        $eventObservers = (array) $config->getXpath('//events/' . $observer->getEvent()->getName() . '/observers/*');

        /**
         * Retrieve all XML nodes of the current observer (including <args>!)
         * and populate $usedObservers with observers that:
         * - call the same class and method than the $observer passed as arguments for this function
         * - have an <args> node declared in config
         */
        foreach ($eventObservers as $eventObserver) {
            $className = $config->getModelClassName($eventObserver->class);
            $method    = $eventObserver->method;
            $args      = (bool) $eventObserver->args;
            if ($className == $callingClass && $method == $callingMethod && $args) {
                $usedObservers[] = $eventObserver;
            }
        }

        /**
         * Create array of args
         */
        foreach ($usedObservers as $usedObserver) {
            $args = (array) $usedObserver->args;
            foreach ($args as $name => $value) {
                $observerArgs[$name] = $value;
            }
        }

        $args = new Varien_Object;
        $args->setData($observerArgs);

        return $args;
    }

    /**
     * Update store configuration - requirements
     * @param Mage_Core_Model_Store $store
     * @return Neteven_NetevenSync_Helper_Data
     */
    public function updateStoreConfiguration(Mage_Core_Model_Store $store)
    {
        /* @var $logger Neteven_NetevenSync_Helper_Logger */
        $logger = Mage::helper('netevensync/logger');

        // Considering prices with taxes, because Neteven prices are tax included
        $paths = array(
            'tax/calculation/price_includes_tax' => '1',
            'tax/calculation/cross_border_trade_enabled' => '1',
        );
        foreach ($paths as $path => $correctValue) {
            if ($store->getConfig($path) != $correctValue) {
                $logger->info("Switch $path from " . $store->getConfig($path) . " to $correctValue.");
                $store->setConfig($path, $correctValue);
            }
        }

        return $this;
    }

}

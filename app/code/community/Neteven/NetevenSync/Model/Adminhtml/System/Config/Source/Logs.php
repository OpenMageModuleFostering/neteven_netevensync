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

/**
 * Adminhtml_System_Config_Source_Logs Model
 * @package Neteven_NetevenSync
 */
class Neteven_NetevenSync_Model_Adminhtml_System_Config_Source_Logs
{

    /**
     * Retrieve logs as option array
     * <p>Format [label, value]</p>
     * @return array
     */
    public function toOptionArray()
    {
        $options = array();
        foreach ($this->toArray() as $value => $label) {
            $options[] = compact('value', 'label');
        }
        return $options;
    }

    /**
     * Retrieve list of log files
     * <p>Format value => label</p>
     * @param bool $withSize Append file size at the end of the name
     * @return array
     */
    public function toArray($withSize = false)
    {
        $options = array();
        $logDir = Mage::getBaseDir('log');
        $files = glob($logDir . DS . '*neteven*');
        foreach ($files as $file) {
            if (filesize($file)) {
                $filename = basename($file);
                $options[hash('md5', $filename)] = $filename;
                if ($withSize) {
                    $options[hash('md5', $filename)] .= ' - ' . $this->_getHumanFilesize($file);
                }
            }
        }
        return $options;
    }

    /**
     * Retrieve readable file size
     * @param string $filename
     * @param int $decimals
     * @return string
     */
    protected function _getHumanFilesize($filename, $decimals = 2)
    {
        $sz     = array('b', 'Kb', 'Mb', 'Gb', 'Tb', 'Pb');
        $bytes  = filesize($filename);
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
    }

}

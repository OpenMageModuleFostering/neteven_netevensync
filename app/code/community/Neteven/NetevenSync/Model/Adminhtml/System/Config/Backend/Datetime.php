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
 * Adminhtml_System_Config_Backend_Datetime Model
 * @package Neteven_NetevenSync
 */
class Neteven_NetevenSync_Model_Adminhtml_System_Config_Backend_Datetime extends Mage_Core_Model_Config_Data
{

    /**
     * We convert the MySQL datetime (in UTC)
     * to an array (of the corresponding date using the configured timezone).
     */
    protected function _afterLoad()
    {
        if (!is_array($this->getValue())) {
            $gmtDate = (string) $this->getValue(); // From UTC

            if (empty($gmtDate)) {
                $gmtDate = 1; // Default to 1970-01-01 00:00:01
            }

            $date  = Mage::app()->getLocale()->date($gmtDate); // To Timezone
            $value = explode('-', $date->toString('y-MM-dd-HH-mm-ss'));
            $this->setValue($value);
        }
    }

    /**
     * We transform the array with date and time (configured timezone)
     * to MySQL date (in UTC).
     *
     * @throws Zend_Date_Exception
     */
    protected function _beforeSave()
    {
        if (is_array($this->getValue())) {
            $value = $this->getValue();

            if (strlen($value[0])) {
                $filterInput    = new Zend_Filter_LocalizedToNormalized(array(
                    'date_format' => Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
                ));
                $filterInternal = new Zend_Filter_NormalizedToLocalized(array(
                    'date_format' => Varien_Date::DATE_INTERNAL_FORMAT,
                ));
                $normalizedDate = $filterInternal->filter($filterInput->filter($value[0]));
            } else {
                $normalizedDate = '1970-01-01';
            }

            $date  = Mage::app()->getLocale()->date(); // From timezone
            $date
                ->setDate($normalizedDate, Varien_Date::DATE_INTERNAL_FORMAT)
                ->setHour($value[1])
                ->setMinute($value[2])
                ->setSecond($value[3])
                ->setTimezone('UTC') // To UTC
            ;

            $this->setValue($date->toString('y-MM-dd HH:mm:ss'));
        }
    }
}
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
 * Adminhtml_System_Config_Form_Field_Datetime Block
 * @package Neteven_NetevenSync
 */
class Neteven_NetevenSync_Block_Adminhtml_System_Config_Form_Field_Datetime extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        // Current date value (as array)
        $value = $element->getValue();
        
        // Date field
        $dateField = new Varien_Data_Form_Element_Date;
        $format    = Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT);

        $data = array(
            'name'    => $element->getName() . '[]',
            'html_id' => $element->getId(), // We keep the original ID here to avoid JS bugs
            'image'   => $this->getSkinUrl('images/grid-cal.gif'),
        );

        $dateField
            ->setData($data)
            ->setValue(sprintf('%d-%d-%d', $value[0], $value[1], $value[2]), Varien_Date::DATE_INTERNAL_FORMAT)
            ->setFormat($format)
            ->setForm($element->getForm())
        ;

        // Time field
        $timeField = new Varien_Data_Form_Element_Time;

        $data = array(
            'name'    => $element->getName() . '[]',
            'html_id' => $element->getId() . '_time',
        );

        $timeField
            ->setData($data)
            ->setValue(implode(',', array($value[3], $value[4], $value[5])))
            ->setForm($element->getForm())
        ;

        // Classes
        if ($element->getFieldConfig()->validate) {
            $class = $element->getFieldConfig()->validate->asArray();
            $dateField->setClass($class);
            $timeField->setClass($class);
        }

        return $dateField->getElementHtml() . "&nbsp;" . $timeField->getElementHtml();
    }
}
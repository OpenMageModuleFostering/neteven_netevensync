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
 * Adminhtml_System_Config_Form_Field_Order_Mapping_Select Block
 * @package Neteven_NetevenSync
 */
class Neteven_NetevenSync_Block_Adminhtml_System_Config_Form_Field_Order_Mapping_Select extends Mage_Adminhtml_Block_Html_Select
{

// Neteven Tag NEW_CONST

// Neteven Tag NEW_VAR

    /**
     * Alias of setName
     * @param string $name
     * @return Neteven_NetevenSync_Block_Adminhtml_System_Config_Form_Field_Order_Mapping_Select
     */
    public function setInputName($name)
    {
        return $this->setName($name);
    }

    /**
     * Set column name for config field
     * @param string $columnName
     * @return Neteven_NetevenSync_Block_Adminhtml_System_Config_Form_Field_Order_Mapping_Select
     */
    public function setColumnName($columnName)
    {
        // update the extra params
        $extraParams = $this->getExtraParams();
        $extraParams .= ' data-template-value="#{' . $columnName . '}" ';
        $this->setExtraParams($extraParams);

        return $this;
    }


// Neteven Tag NEW_METHOD

}
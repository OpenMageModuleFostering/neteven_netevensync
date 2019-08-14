<?php

/**
 * Orders state source options
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Adminhtml_System_Config_Source_MagentoOrderStatus extends Mage_Adminhtml_Model_System_Config_Source_Order_Status
{

    public function getAvailableStatuses($state)
    {
        $this->_stateStatuses = array($state);
        $options              = array();
        $srcOptions           = $this->toOptionArray();
        foreach ($srcOptions as $srcOption) {
            $options[$srcOption['value']] = $srcOption['label'];
        }
        return $options;
    }

    public function toOptionArray()
    {
        if ($this->_stateStatuses) {
            $statuses = Mage::getModel('sales/order_config')->getStateStatuses($this->_stateStatuses);
        } else {
            $statuses = Mage::getModel('sales/order_config')->getStatuses();
        }
        $options   = array();
        $options[] = array(
            'value' => '',
            'label' => Mage::helper('adminhtml')->__('-- Please Select --')
        );
        foreach ($statuses as $code => $label) {
            $options[] = array(
                'value' => $code,
                'label' => $label
            );
        }
        return $options;
    }

    public function getNewStatuses()
    {
        return $this->getAvailableStatuses(Mage_Sales_Model_Order::STATE_NEW);
    }

    public function getProcessingStatuses()
    {
        return $this->getAvailableStatuses(Mage_Sales_Model_Order::STATE_PROCESSING);
    }

    public function getCanceledStatuses()
    {
        return $this->getAvailableStatuses(Mage_Sales_Model_Order::STATE_CANCELED);
    }

    public function getClosedStatuses()
    {
        return $this->getAvailableStatuses(Mage_Sales_Model_Order::STATE_CLOSED);
    }

    public function getCompleteStatuses()
    {
        return $this->getAvailableStatuses(Mage_Sales_Model_Order::STATE_COMPLETE);
    }

}

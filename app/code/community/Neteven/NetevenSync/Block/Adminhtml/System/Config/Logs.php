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
 * Adminhtml_System_Config_Logs Block
 * @package Neteven_NetevenSync
 */
class Neteven_NetevenSync_Block_Adminhtml_System_Config_Logs extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    /**
     * {@inheritdoc}
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);

        // Find log files
        $logFiles = Mage::getSingleton('netevensync/adminhtml_system_config_source_logs')->toArray(true);

        // No logs?
        if (empty($logFiles)) {
            return "<em>" . $this->__("No log") . "</em>";
        }

        // Create select element
        /* @var $select Mage_Adminhtml_Block_Html_Select */
        $select = $this->getLayout()->createBlock('adminhtml/html_select');
        $select->setId($selectId = uniqid());
        $select->setExtraParams('onchange="netevensync_toggle_buttons(this);"');
        $select->addOption('', $this->__('--Please Select--'));

        foreach ($logFiles as $value => $label) {
            $select->addOption($value, $label);
        }

        // Append buttons to the select (display none)
        $html = $select->toHtml() . '<br/><div id="' . $selectId . '-parent" style="margin-top:5px; display: none;">';
        // Download button
        $html .= $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setType('button')
            ->setLabel($this->__('Download this log'))
            ->setOnClick("netevensync_download('$selectId');")
            ->setId($element->getHtmlId())
            ->toHtml()
        ;
        // Delete button
        $html .= ' ' . $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setType('button')
            ->setLabel($this->__('Delete this log'))
            ->setClass('delete')
            ->setOnClick("netevensync_delete('$selectId');")
            ->setId($element->getHtmlId())
            ->toHtml()
        ;
        $html .= "</div>";

        return $html;
    }

    /**
     * {@inheritdoc}
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        // URLs
        $downloadUrl = $this->getUrl('adminhtml/netevensync/downloadlog', array('log' => 'logname'));
        $deleteUrl = $this->getUrl('adminhtml/netevensync/deletelog', array('log' => 'logname'));

        // Add script to the bottom
        $js = <<<JS
<script type="text/javascript">
var netevensync_toggle_buttons = function (select)
{
    if (select.value !== '') {
        $(select.id + '-parent').show();
    } else {
        $(select.id + '-parent').hide();
    }
};
var netevensync_download = function (select_id)
{
    var select = $(select_id);
    setLocation('{$downloadUrl}'.replace('logname', encodeURIComponent(select.value)));
};
var netevensync_delete = function (select_id)
{
    var select = $(select_id);
    setLocation('{$deleteUrl}'.replace('logname', encodeURIComponent(select.value)));
};
</script>
JS;
        $jsBlock = $this->getLayout()->createBlock('core/text');
        $jsBlock->setText($js);
        $this->getLayout()->getBlock('js')->append($jsBlock);

        return $this;
    }

}

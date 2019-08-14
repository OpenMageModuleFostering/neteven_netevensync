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

try {

    /* @var $installer Mage_Sales_Model_Resource_Setup */
    $installer = Mage::getResourceModel('sales/setup', 'core_setup');
    $installer->startSetup();

    // Add quote item column
    $installer->addAttribute('quote_item', 'neteven_checksum', array(
        'type'   => Varien_Db_Ddl_Table::TYPE_VARCHAR,
    ));

    $installer->endSetup();

} catch (Exception $e) {
    // Silence is golden
}

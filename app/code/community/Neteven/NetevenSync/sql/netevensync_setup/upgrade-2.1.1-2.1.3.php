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

    /* @var $installer Mage_Core_Model_Resource_Setup */
    $installer = $this;
    $installer->startSetup();

    // Add column "has_been_processed" on the order link table
    $orderLinkTableName = $installer->getTable('netevensync/order_link');
    $installer->getConnection()->addColumn(
        $orderLinkTableName,
        'has_been_processed',
        array(
            'type'      => Varien_Db_Ddl_Table::TYPE_INTEGER,
            'length'    => 1,
            'nullable'  => true,
            'unsigned'  => true,
            'comment'   => 'If the item has been processed recently'
        )
    );

    $installer->endSetup();

} catch (Exception $e) {
    // Silence is golden
}

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

    // Add column "store_id" on the order link table
    $orderLinkTableName = $installer->getTable('netevensync/order_link');
    $installer->getConnection()->addColumn(
        $orderLinkTableName,
        'store_id',
        array(
            'type'      => Varien_Db_Ddl_Table::TYPE_SMALLINT,
            'length'    => 5,
            'nullable'  => true,
            'unsigned'  => true,
            'comment'   => 'Neteven Market Place Name'
        )
    );

    // Add constraint
    $installer->getConnection()->addForeignKey(
        $installer->getFkName('netevensync/order_link', 'store_id', 'core/store', 'store_id'),
        $orderLinkTableName,
        'store_id',
        $installer->getTable('core/store'),
        'store_id',
        Varien_Db_Ddl_Table::ACTION_SET_NULL,
        Varien_Db_Ddl_Table::ACTION_SET_NULL
    );

    $installer->endSetup();

} catch (Exception $e) {
    // Silence is golden
}

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

    /*
     * Add unique index on netevensync_product/product_id
     * Note:
     *      In case some developers already added the constraint to avoid error,
     *      this will create a new one but it won't have any bad effect (except
     *      maybe some duplicated data on the server in case of multiple indexes…).
     */
    $tableName = $installer->getTable('netevensync/product');
    $installer->getConnection()->addIndex(
        $tableName,
        $installer->getIdxName($tableName, 'product_id', Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE),
        'product_id',
        Varien_Db_Adapter_Interface::INDEX_TYPE_UNIQUE
    );

    $installer->endSetup();

} catch (Exception $e) {
    // Silence is golden
}

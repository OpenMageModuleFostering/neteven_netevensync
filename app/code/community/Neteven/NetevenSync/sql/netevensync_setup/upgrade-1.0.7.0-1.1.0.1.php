<?php
/**
 * SQL install script
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */

$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */

$installer->startSetup();

/**
 * Add column to table 'netevensync/order_link'
 */
$installer->getConnection()->addColumn(
    $installer->getTable('netevensync/order_link'),
    'neteven_market_place_order_id',
    array(
        'type'      => Varien_Db_Ddl_Table::TYPE_TEXT,
        'nullable'  => false,
        'comment'   => 'Neteven Market Place Order Id'
    )
);

$installer->endSetup();
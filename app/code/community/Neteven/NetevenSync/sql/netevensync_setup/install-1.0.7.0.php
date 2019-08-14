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
 * Create table 'netevensync/inventory'
 */
$table = $installer->getConnection()
	->newTable($installer->getTable('netevensync/inventory'))
	->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'identity'  => true,
			'unsigned'  => true,
			'nullable'  => false,
			'primary'   => true,
	), 'Id')
	->addColumn('product_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'nullable'  => false,
	), 'Product Id')
	->addColumn('sku', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
			'nullable'  => false,
	), 'Product Sku')
	->addColumn('to_delete', Varien_Db_Ddl_Table::TYPE_BOOLEAN, null, array(), 'To Be Deleted on Neteven Platform')
	->addColumn('is_new', Varien_Db_Ddl_Table::TYPE_BOOLEAN, null, array(), 'To Be Created on Neteven Platform')
	->setComment('Inventory Data Incremental Export');

$installer->getConnection()->createTable($table);

/**
 * Create table 'netevensync/log'
 */
$table = $installer->getConnection()
	->newTable($installer->getTable('netevensync/log'))
	->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'identity'  => true,
			'unsigned'  => true,
			'nullable'  => false,
			'primary'   => true,
	), 'Id')
	->addColumn('code', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
			'nullable'  => false,
	), 'Log Code')
	->addColumn('has_error', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'nullable'  => false,
	), 'Errors Flag')
	->setComment('Logs Follow Up');

$installer->getConnection()->createTable($table);

/**
 * Create table 'netevensync/process'
 */
$table = $installer->getConnection()
	->newTable($installer->getTable('netevensync/process'))
	->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'identity'  => true,
			'unsigned'  => true,
			'nullable'  => false,
			'primary'   => true,
	), 'Id')
	->addColumn('process_code', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
			'nullable'  => false,
	), 'Process Code')
	->addColumn('last_sync', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
			'nullable'  => false,
	), 'Last Sync Datetime')
	->addColumn('next_sync', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array(
			'nullable'  => false,
	), 'Next Sync Datetime')
	->setComment('Synchronization Processes');

$installer->getConnection()->createTable($table);

/**
 * Create table 'netevensync/product'
 */
$table = $installer->getConnection()
	->newTable($installer->getTable('netevensync/product'))
	->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'identity'  => true,
			'unsigned'  => true,
			'nullable'  => false,
			'primary'   => true,
	), 'Id')
	->addColumn('product_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'nullable'  => false,
	), 'Product Id')
	->addForeignKey(
			$installer->getFkName('netevensync/product', 'product_id', 'catalog/product', 'entity_id'), 'product_id',
			$installer->getTable('catalog/product'), 'entity_id',
			Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE
		)
	->setComment('Products Selected As To Be Exported');

$installer->getConnection()->createTable($table);

/**
 * Create table 'netevensync/order'
 */
$table = $installer->getConnection()
	->newTable($installer->getTable('netevensync/order'))
	->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'identity'  => true,
			'unsigned'  => true,
			'nullable'  => false,
			'primary'   => true,
	), 'Id')
	->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'nullable'  => false,
	), 'Magento Order Id')
	->addForeignKey(
			$installer->getFkName('netevensync/order', 'order_id', 'sales/order', 'entity_id'), 'order_id',
			$installer->getTable('sales/order'), 'entity_id',
			Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE
	)
	->setComment('Orders Data Incremental Export');

$installer->getConnection()->createTable($table);

/**
 * Create table 'netevensync/order_link'
 */
$table = $installer->getConnection()
	->newTable($installer->getTable('netevensync/order_link'))
	->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'identity'  => true,
			'unsigned'  => true,
			'nullable'  => false,
			'primary'   => true,
	), 'Id')
	->addColumn('neteven_order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'nullable'  => false,
	), 'Neteven Order Id')
	->addColumn('neteven_customer_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'nullable'  => false,
	), 'Neteven Customer Id')
	->addColumn('magento_order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'nullable'  => true,
	), 'Magento Order Id')
	->addColumn('magento_quote_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'nullable'  => false,
	), 'Magento Quote Id')
	->addColumn('payment_method', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
			'nullable'  => true,
	), 'Neteven Payment Method')
	->addColumn('order_status', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
			'nullable'  => true,
	), 'Neteven Order Status')
	->addForeignKey(
			$installer->getFkName('netevensync/order_link', 'magento_order_id', 'sales/order', 'entity_id'), 'magento_order_id',
			$installer->getTable('sales/order'), 'entity_id',
			Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE
	)
	->addForeignKey(
			$installer->getFkName('netevensync/order_link', 'magento_quote_id', 'sales/quote', 'entity_id'), 'magento_quote_id',
			$installer->getTable('sales/quote'), 'entity_id',
			Varien_Db_Ddl_Table::ACTION_CASCADE, Varien_Db_Ddl_Table::ACTION_CASCADE
	)
	->setComment('Link between Neteven and Magento Order Data');
	
$installer->getConnection()->createTable($table);
	
/**
 * Create table 'netevensync/order_temp'
 */
$table = $installer->getConnection()
	->newTable($installer->getTable('netevensync/order_temp'))
	->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'identity'  => true,
			'unsigned'  => true,
			'nullable'  => false,
			'primary'   => true,
	), 'Id')
	->addColumn('neteven_item_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
			'nullable'  => false,
	), 'Neteven Order Item Id')
	->addColumn('neteven_item_data', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
			'nullable'  => false,
	), 'Order Item Data')
	->setComment('Temp Orders Data for Import');

$installer->getConnection()->createTable($table);

$installer->endSetup();
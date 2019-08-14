<?php
/**
 * Tab with a grid of products selected for export
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Block_Adminhtml_Product_View_Tab_Exported extends Mage_Adminhtml_Block_Widget_Grid {
	
	/**
	 * Set grid params
	 */
	public function __construct() {
		parent::__construct();
		$this->setId('netevensync_product_view_exported');
		$this->setDefaultSort('entity_id');
		$this->setDefaultDir('asc');
		$this->setUseAjax(true);
	}
	
	/**
	 * Prepare collection
	 * 
	 * @return Mage_Adminhtml_Block_Widget_Grid
	 */
	protected function _prepareCollection() {
		$collection = Mage::getModel('catalog/product')->getCollection()
			->addAttributeToSelect(array(
					'name',
					'sku',
					'type_id',
					'attribute_set_id',
			))
			->addFieldToFilter('type_id', $this->_getAvailableProductTypes())
			;
		
		$collection->getSelect()->joinRight(
				array('netevensync_product' => $collection->getTable('netevensync/product')),
				'netevensync_product.product_id = e.entity_id',
				array()
			);

		$collection->getSelect()->group('netevensync_product.product_id'); // This is a fix in case some items in Neteven Selection are present several times in module version < 1.0.0.1
		
		$this->setCollection($collection);
		return parent::_prepareCollection();
	}
	
	/**
	 * Add filter
	 * 
	 * @param object $column
	 * @return Mage_Adminhtml_Block_Widget_Grid
	 */
	protected function _addColumnFilterToCollection($column) {
		if($column->getId() == 'in_products') {
			$productIds = $this->_getExportedSelectedProducts();
			if(empty($productIds)) {
				$productIds = 0;
			}
			if($column->getFilter()->getValue()) {
				$this->getCollection()->addFieldToFilter('entity_id', array('in' => $productIds));
			}
			else {
				if($productIds) {
					$this->getCollection()->addFieldToFilter('entity_id', array('nin' => $productIds));
				}
			}
		}
		else {
			parent::_addColumnFilterToCollection($column);
		}
		return $this;
	}
	
	/**
	 * Add columns to grid
	 * 
	 * @return Mage_Adminhtml_Block_Widget_Grid
	 */
	protected function _prepareColumns() {
		
		$this->addColumn('in_products', array(
				'header_css_class'	=> 'a-center',
				'type'				=> 'checkbox',
				'name'				=> 'in_products',
				'values'			=> $this->_getExportedSelectedProducts(),
				'align'				=> 'center',
				'index'				=> 'entity_id', 
		));
		
		$this->addColumn('entity_id', array(
				'header'	=> Mage::helper('catalog')->__('ID'),
				'sortable'	=> true,
				'width'		=> 60,
				'index'		=> 'entity_id',
		));
		
		$this->addColumn('sku', array(
				'header'	=> Mage::helper('catalog')->__('SKU'),
				'width'		=> 80,
				'index'		=> 'sku',
		));
		
		$this->addColumn('name', array(
				'header'	=> Mage::helper('catalog')->__('Name'),
				'index'		=> 'name',
		));
		
		$this->addColumn('type', array(
				'header'	=> Mage::helper('catalog')->__('Type'),
				'width' => '60px',
				'index' => 'type_id',
				'type'  => 'options',
				'options' => $this->_getAvailableProductTypes(true),
		));
		
		$sets = Mage::getResourceModel('eav/entity_attribute_set_collection')
			->setEntityTypeFilter(Mage::getModel('catalog/product')->getResource()->getTypeId())
			->load()
			->toOptionHash();
		
		$this->addColumn('set_name', array(
				'header'=> Mage::helper('catalog')->__('Attrib. Set Name'),
				'width' => '100px',
				'index' => 'attribute_set_id',
				'type'  => 'options',
				'options' => $sets,
		));
		
		return parent::_prepareColumns();
	}
	
	/**
	 * Retrieve available product types
	 *
	 * @param bool $withLabel
	 * @return array
	 */
	public function _getAvailableProductTypes($withLabel = false) {
		return Mage::getSingleton('netevensync/config')->getAvailableProductTypes($withLabel);
	}
	
	/**
	 * Retrieve selected exported products
	 * 
	 * @return array
	 */
	protected function _getExportedSelectedProducts() {
		$products = $this->getCheckedProducts();
		if(!is_array($products)) {
			$products = array();
		}
		return $products;		
	}
	
	/**
	 * Ajax callback
	 * 
	 * @return array
	 */
	public function getExportedSelectedProducts() {
		return $this->_getExportedSelectedProducts();
	}
	
	/**
	 * Retrieve grid for Ajax
	 * 
	 * @return string
	 */
	public function getGridUrl() {
		return $this->getUrl('*/*/productExportedGrid', array('_current' => true));
	}
}
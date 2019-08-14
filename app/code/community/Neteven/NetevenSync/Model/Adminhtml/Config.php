<?php
/**
 * REWRITE Config model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Model_Adminhtml_Config extends Mage_Adminhtml_Model_Config {

    /**
     * Init modules configuration
     * REWRITE in order to dispatch event
     *
     * @return void
     */
    protected function _initSectionsAndTabs()
    {
    	if(str_replace('.', '', Mage::getVersion()) >= 1702) {
    		return parent::_initSectionsAndTabs();
    	}
    	
        $config = Mage::getConfig()->loadModulesConfiguration('system.xml')
            ->applyExtends();

        Mage::dispatchEvent('adminhtml_init_system_config', array('config' => $config));
        $this->_sections = $config->getNode('sections');
        $this->_tabs = $config->getNode('tabs');
    }
}

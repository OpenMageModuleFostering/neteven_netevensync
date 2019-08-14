<?php
/**
 * Shipping model
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */

class Neteven_NetevenSync_Model_Shipping_Dynamic
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{

    protected $_code = 'neteven';
    protected $_isFixed = true;

    /**
     * Enter description here...
     *
     * @param Mage_Shipping_Model_Rate_Request $data
     * @return Mage_Shipping_Model_Rate_Result
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
    	$result = Mage::getModel('shipping/rate_result');
    	
    	if(!$this->isActive()) {
    		return $result;
    	}
    	
    	$carrierCode = Mage::getStoreConfig('netevensync/shipping/carrier', $request->getStoreId());
    	$carrier = Mage::getModel('shipping/shipping')->getCarrierByCode($carrierCode);
    	$availableMethods = $carrier->getAllowedMethods();
    	$carrierTitle = $carrier->getConfigData('title');
    	$methodTitle = reset($availableMethods);
    	
    	
    	$method = Mage::getModel('shipping/rate_result_method');
    	
    	$method->setCarrier('neteven');
    	$method->setCarrierTitle($carrierTitle);
    	
    	$method->setMethod('dynamic');
    	$method->setMethodTitle($methodTitle);
    	
    	$method->setPrice($this->getSession()->getNetevenShippingPrice());
    	$method->setCost($this->getSession()->getNetevenShippingPrice());
    	
    	$result->append($method);
    	
    	return $result;
    }

    public function getAllowedMethods()
    {
        return array('neteven'=>$this->getConfigData('name'));
    }
    
    public function isActive() {
    	return ($this->getSession()->getIsFromNeteven()) ? true : false;
    }
    
    public function getSession() {
    	return Mage::getSingleton('checkout/session');
    }

}

<?php
/**
 * Process Run block
 *
 * @category    Neteven
 * @package     Neteven_NetevenSync
 * @copyright   Copyright (c) 2013 Agence Soon. (http://www.agence-soon.fr)
 * @licence     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author      Hervé Guétin <herve.guetin@agence-soon.fr> <@herveguetin>
 */
class Neteven_NetevenSync_Block_Adminhtml_Process_Run extends Mage_Adminhtml_Block_Template {
	
	/**
	 * Return a batch model config JSON
	 * 
	 * @return string
	 */
	public function getBatchConfigJson()
	{
		return Mage::helper('core')->jsonEncode(
				$this->getBatchConfig()
		);
	}
	
	/**
	 * Retrieve batch config
	 * 
	 * @return array $batchConfig
	 */
	public function getBatchConfig() {
		
		return array(
				'styles' => array(
						'error' => array(
								'icon' => Mage::getDesign()->getSkinUrl('images/error_msg_icon.gif'),
								'bg'   => '#FDD'
						),
						'message' => array(
								'icon' => Mage::getDesign()->getSkinUrl('images/fam_bullet_success.gif'),
								'bg'   => '#DDF'
						),
						'loader'  => Mage::getDesign()->getSkinUrl('images/ajax-loader.gif')
				),
				'template' => '<li style="#{style}" id="#{id}">'
				. '<img id="#{id}_img" src="#{image}" class="v-middle" style="margin-right:5px"/>'
				. '<span id="#{id}_status" class="text">#{text}</span>'
				. '</li>',
				'text'     => $this->__('Processed <strong>%s%% %s/%s</strong> records', '#{percent}', '#{updated}', '#{total}'),
				'successText'  => $this->__('Processed <strong>%s</strong> records', '#{updated}')
		);
		
	}
	
	/**
	 * Retrieve process URL
	 * 
	 * @return string
	 */
	public function getProcessUrl() {
		$url = $this->getUrl('adminhtml/netevensync/runProcessPage', array(
				'mode' => $this->getMode(),
				'type' => $this->getType(),
				)
		);
		
		return $url;
	}
	
	/**
	 * Retrieve items count URL
	 *
	 * @return string
	 */
	public function getLaunchUrl() {
		$url = $this->getUrl('adminhtml/netevensync/launchProcesses', array(
				'mode' => $this->getMode(),
				'type' => $this->getType(),
				)
		);
		
		return $url;
	}
	
	/**
	 * Retrieve finish process URL
	 *
	 * @return string
	 */
	public function getFinishUrl() {
		$url = $this->getUrl('adminhtml/netevensync/finishProcesses', array(
				'mode' => $this->getMode(),
				'type' => $this->getType(),
				)
		);
		return $url;
	}
}
<?php
/**
 * Hint for system configuration
 *
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author Hervé Guétin <@herveguetin> <herve.guetin@agence-soon.fr>
 * @category Neteven
 * @package Neteven_NetevenSync
 * @copyright Copyright (c) 2013 Agence Soon (http://www.agence-soon.fr)
 */

class Neteven_NetevenSync_Block_Adminhtml_System_Config_Hint
    extends Mage_Adminhtml_Block_Abstract
    implements Varien_Data_Form_Element_Renderer_Interface
{
    protected $_template = 'netevensync/system/config/shipping/hint.phtml';

    /**
     * Render fieldset html
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $elementOriginalData = $element->getOriginalData();
        if (isset($elementOriginalData['hint_content'])) {
            $this->setText(Mage::helper('netevensync')->__($elementOriginalData['hint_content']));
            return $this->toHtml();
        }
        return '';
    }
}

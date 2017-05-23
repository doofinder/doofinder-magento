<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   blocks
 * @package    Doofinder_Feed
 * @version    1.8.7
 */

class Doofinder_Feed_Block_Settings_Panel_Description extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    const INFO = 'info';
    const WARNING = 'warning';

    protected $level = self::INFO;
    protected $description = 'You can set the rest of the options for each store separately by modifying the Current Configuration Scope.';

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $text = '';

        if (!Mage::app()->getRequest()->getParam('store'))
        {
            $text = $this->description;
        }

        $this->setElement($element);
        $name = $element->getName();
        $element->setScopeLabel('');

        return '<p class="doofinder-' . $this->level . '">' . $text . '</p>';
    }

    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = '<td class="label"></td>' .
                '<td class="value" colspan="3">' . $this->_getElementHtml($element) . '</td>';
        return $this->_decorateRowHtml($element, $html);
    }

    /**
     * Decorate field row html
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @param string $html
     * @return string
     */
    protected function _decorateRowHtml($element, $html)
    {
        return '<tr id="row_' . $element->getHtmlId() . '">' . $html . '</tr>';
    }
}

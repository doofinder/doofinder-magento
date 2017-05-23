<?php
/**
 * This file is part of Doofinder_Feed.
 */

/**
 * @category   blocks
 * @package    Doofinder_Feed
 * @version    1.8.7
 */

class Doofinder_Feed_Block_Adminhtml_Map_Additional extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected $_addRowButtonHtml = array();
    protected $_removeRowButtonHtml = array();

    protected $_rows = 0;

    /**
    * Returns html part of the setting
    *
    * @param Varien_Data_Form_Element_Abstract $element
    * @return string
    */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);

        $html = '<table style="display:none"><tbody id="doofinder_feed_additional_mapping_template">';
        $html .= $this->_getRowTemplateHtml(-1);
        $html .= '</tbody></table>';

        $html .= '<table>';
        $html .= '<thead><tr>';
        $html .= '<th>' . $this->__('Label') . '</th><th>' . $this->__('Field') . '</th><th>' . $this->__('Attribute') . '</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody id="doofinder_feed_additional_mapping_container">';

        $count = 0;
        if ($this->_getValue('additional_mapping')) {
            foreach ($this->_getValue('additional_mapping') as $i => $f) {
                $html .= $this->_getRowTemplateHtml($count++);
            }
        }

        $html .= '</tbody></table>';
        $html .= $this->_getAddRowButtonHtml();

        $html .= '<script type="text/javascript">';
        ob_start();
        ?>
            var DoofinderFeedMapAdditionalRowGenerator = function() {
                this.count = <?php print $count; ?>;
            };

            DoofinderFeedMapAdditionalRowGenerator.prototype.add = function() {
                var html = $('doofinder_feed_additional_mapping_template').innerHTML;
                html = html.replace(/\[additional_mapping\]\[-1\]/g, '[additional_mapping][' + (this.count++) + ']');
                Element.insert($('doofinder_feed_additional_mapping_container'), {bottom: html});
            };

            var doofinderFeedMapAdditionalRowGenerator = new DoofinderFeedMapAdditionalRowGenerator();
        <?php
        $html .= ob_get_clean();
        $html .= '</script>';

        return $html;
    }

    /**
    * Retrieve html template for setting
    *
    * @param int $rowIndex
    * @return string
    */
    protected function _getRowTemplateHtml($rowIndex = null)
    {
        $value = $rowIndex !== null ? (array) $this->_getValue('additional_mapping/' . $rowIndex) : array();
        $value += array('field' => '', 'label' => '', 'attribute' => '');
        $html = '<tr>';

        $html .= '<td>';
        $html .= '<input name="'
            . $this->getElement()->getName() . '[additional_mapping][' . $rowIndex . '][label]" value="'
            . $value['label'] . '" ' . $this->_getDisabled() . '/> ';
        $html .= '</td><td>';
        $html .= '<input name="'
            . $this->getElement()->getName() . '[additional_mapping][' . $rowIndex . '][field]" value="'
            . $value['field'] . '" ' . $this->_getDisabled() . '/> ';
        $html .= '</td><td>';
        $html .= '<select name="'
            . $this->getElement()->getName() . '[additional_mapping][' . $rowIndex . '][attribute]" ' . $this->_getDisabled() . '>';
        foreach (Mage::getSingleton('doofinder_feed/system_config_source_product_attributes')->toOptionArray() as $key => $label) {
            $html .= '<option value="' . $key . '"'. ($value['attribute'] == $key ? 'selected="selected"' : '') . '>' . $label . '</option>';
        }
        $html .= '</select> ';
        $html .= '</td><td>';
        $html .= $this->_getRemoveRowButtonHtml();
        $html .= '</td>';
        $html .= '</tr>';

        return $html;
    }

    protected function _getDisabled()
    {
        return $this->getElement()->getDisabled() ? 'disabled' : '';
    }

    protected function _getValue($key)
    {
        return $this->getElement()->getData('value/' . $key);
    }

    protected function _getSelected($key, $value)
    {
        return $this->getElement()->getData('value/' . $key) == $value ? 'selected="selected"' : '';
    }

    protected function _getAddRowButtonHtml()
    {
        $container = isset($container) ? $container : null;

        if (!isset($this->_addRowButtonHtml[$container])) {
            $_cssClass = 'add';

            if (version_compare(Mage::getVersion(), '1.6', '<')) {
                $_cssClass .= ' ' . $this->_getDisabled();
            }

            $this->_addRowButtonHtml[$container] = $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setType('button')
                ->setClass($_cssClass)
                ->setLabel($this->__('Add'))
                ->setOnClick("doofinderFeedMapAdditionalRowGenerator.add()")
                ->setDisabled($this->_getDisabled())
                ->toHtml();
        }
        return $this->_addRowButtonHtml[$container];
    }

    protected function _getRemoveRowButtonHtml()
    {
        if (!$this->_removeRowButtonHtml) {
            $_cssClass = 'delete v-middle';

            if (version_compare(Mage::getVersion(), '1.6', '<')) {
                $_cssClass .= ' ' . $this->_getDisabled();
            }

            $this->_removeRowButtonHtml = $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setType('button')
                ->setClass($_cssClass)
                ->setLabel($this->__('Delete'))
                ->setOnClick("Element.remove($(this).up('tr'))")
                ->setDisabled($this->_getDisabled())
                ->toHtml();
        }
        return $this->_removeRowButtonHtml;
    }
}

<?php
namespace Xendit\M2Invoice\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field as BaseField;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\UrlInterface;

class ReturnUrl extends BaseField
{
    /**
     * Render element value
     *
     * @param   \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return  string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _renderValue(AbstractElement $element)
    {
        $stores = $this->_storeManager->getStores();
        $valueReturn = '';
        $urlArray = [];
        foreach ($stores as $store) {
            $baseUrl = $store->getBaseUrl(UrlInterface::URL_TYPE_WEB, true);
            if ($baseUrl) {
                $value      = $baseUrl . 'xendit/checkout/notification';
                $urlArray[] = "<div>".$this->escapeHtml($value)."</div>";
            }
        }
        $urlArray = array_unique($urlArray);
        foreach ($urlArray as $uniqueUrl) {
            $valueReturn .= "<div>".$uniqueUrl."</div>";
        }

        $fieldNote = '<p class="note">
            <span>Please copy and paste this callback URLs to your invoice setting in invoice dashboard setting</span>
        </p>';

        $valueReturn .= $fieldNote;

        return '<td class="value">' . $valueReturn . '</td>';
    }
    /**
     * Render element value
     *
     * @param   \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return  string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _renderInheritCheckbox(AbstractElement $element)
    {
        return '<td class="use-default"></td>';
    }
}
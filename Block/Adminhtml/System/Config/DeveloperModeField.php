<?php

namespace Xendit\M2Invoice\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\App\State;

class DeveloperModeField extends Field
{
    /**
     * @var State
     */
    protected $appState;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param State $appState
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        State $appState,
        array $data = []
    ) {
        $this->appState = $appState;
        parent::__construct($context, $data);
    }

    /**
     * Render element HTML
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        // Only show this field when Magento is in developer mode
        if ($this->appState->getMode() !== State::MODE_DEVELOPER) {
            return '';
        }

        return parent::render($element);
    }
}
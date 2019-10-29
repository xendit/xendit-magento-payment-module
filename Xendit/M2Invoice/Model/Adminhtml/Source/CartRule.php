<?php
namespace Xendit\M2Invoice\Model\Adminhtml\Source;

use Magento\SalesRule\Model\RuleRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;

/**
 * Class CartRule
 */
class CartRule implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @var RuleCollection
     */
    protected $_ruleCollection;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;

    public function __construct(
        RuleRepository $ruleRepo,
        SearchCriteriaBuilder $searchBuilder
    ) {
        $this->_ruleCollection = $ruleRepo;
        $this->_searchCriteriaBuilder = $searchBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        $searchCriteria = $this->_searchCriteriaBuilder->addFilter('is_active', true)->create();
        $rules = $this->_ruleCollection->getList($searchCriteria);
        $options = array([
            'value' => 'notset',
            'label' => __('Choose Cart Rule')
        ]);

        foreach ($rules->getItems() as $rule) {
            $options[] = [
                'value' => $rule->getRuleId(),
                'label' => $rule->getName()
            ];
        }

        return $options;
    }
}

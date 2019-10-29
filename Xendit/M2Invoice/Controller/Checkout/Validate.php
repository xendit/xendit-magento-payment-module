<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\App\Action\Context;
use Magento\SalesRule\Model\RuleRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Xendit\M2Invoice\Helper\Data;

class Validate extends \Magento\Framework\App\Action\Action
{
    /**
     * @var RuleCollection
     */
    protected $_ruleCollection;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $_searchCriteriaBuilder;

    protected $_data;

    public function __construct(
        Context $context,
        RuleRepository $ruleRepo,
        SearchCriteriaBuilder $searchBuilder,
        Data $data
    ) {
        parent::__construct($context);
        $this->_ruleCollection = $ruleRepo;
        $this->_searchCriteriaBuilder = $searchBuilder;
        $this->_data = $data;
    }

    public function execute()
    {
        echo 'something </br>';

        $enabledPromo = $this->_data->getEnabledPromo();

        echo print_r($enabledPromo, true) . '</br>';

        $filteredPromo = array_filter(
            $enabledPromo,
            function ($obj) {
                echo print_r($obj, true) . '</br>';
                echo $obj['rule_id'] . '</br>';
                return $obj['rule_id'] === '1';
            }
        );

        echo print_r(reset($filteredPromo), true);

        // $searchCriteria = $this->_searchCriteriaBuilder->addFilter('is_active', true)->create();
        // $rules = $this->_ruleCollection->getList($searchCriteria);
        // // /** @var Collection $collection */
        // // $ruleId = 1;
        // // $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        // // $ruleCollection = $objectManager->create('Magento\SalesRule\Model\Rule')->load($ruleId);

        // // $conditions = $ruleCollection->getConditions();

        // // echo var_dump($rules->getItems());

        // foreach ($rules->getItems() as $rule) {
        //     echo 'apakah ini benar' . $rule->getName();

        //     $condition = $rule->getCondition();

        //     // echo print_r($condition->getConditions(), true);
        //     echo $condition->getValue();
        // }

        exit;
        // return false;
    }
}

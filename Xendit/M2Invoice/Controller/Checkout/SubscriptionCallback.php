<?php

namespace Xendit\M2Invoice\Controller\Checkout;

if (interface_exists("Magento\Framework\App\CsrfAwareActionInterface")) {
    include __DIR__ . "/SubscriptionCallback.m23.php";
} else {
    include __DIR__ . "/SubscriptionCallback.m22.php";
}
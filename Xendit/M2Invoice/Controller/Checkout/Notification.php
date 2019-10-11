<?php

namespace Xendit\M2Invoice\Controller\Checkout;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

if (interface_exists("Magento\Framework\App\CsrfAwareActionInterface")) {
    include __DIR__ . "/Notification.m23.php";
} else {
    include __DIR__ . "/Notification.m22.php";
}

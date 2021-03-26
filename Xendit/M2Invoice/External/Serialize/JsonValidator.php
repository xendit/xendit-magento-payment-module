<?php

namespace Xendit\M2Invoice\External\Serialize;

/**
 * Validate JSON string
 * Class JsonValidator
 * @package Xendit\M2Invoice\External\Serialize
 */
class JsonValidator
{
    /**
     * Check if string is valid JSON string
     * @param $string
     * @return bool
     */
    public function isValid($string)
    {
        if ($string !== false && $string !== null && $string !== '') {
            json_decode($string);
            if (json_last_error() === JSON_ERROR_NONE) {
                return true;
            }
        }
        return false;
    }
}

<?php

namespace Xendit\M2Invoice\Helper;

/**
 * Class Crypto
 * @package Xendit\M2Invoice\Helper
 */
class Crypto
{
    /**
     * @param $data
     * @param $apiKey
     * @return string
     */
    public static function generateSignature($data, $apiKey)
    {
        $text = '';
        ksort($data);
        foreach ($data as $k => $v) {
            $text .= $k . $v;
        }
        $hash = base64_encode(hash_hmac('sha256', $text, $apiKey, true));
        return $hash;
    }

    /**
     * @param $userName
     * @param string $password
     * @return string
     */
    public static function generateBasicAuth($userName, $password = "")
    {
        return 'Basic ' . base64_encode("$userName:$password");
    }
}

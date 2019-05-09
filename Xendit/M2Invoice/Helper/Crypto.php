<?php

namespace Xendit\M2Invoice\Helper;

class Crypto
{
    public static function generateSignature($data, $apiKey)
    {
        $text = '';
        ksort($data);

        foreach ($data as $k => $v) {
            $text .= $k . $v;
        }

        $hash = base64_encode(hash_hmac( 'sha256', $text, $apiKey, true ));

        return $hash;
    }

    public static function generateBasicAuth($userName, $password = "")
    {
        return 'Basic ' . base64_encode("$userName:$password");
    }
}
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

        $hash = hash_hmac( "sha256", $text, $apiKey );
        $hash = str_replace('-', '', $hash);

        return $hash;
    }
}
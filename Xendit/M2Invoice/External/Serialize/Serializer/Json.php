<?php

namespace Xendit\M2Invoice\External\Serialize\Serializer;

use Xendit\M2Invoice\External\Serialize\SerializerInterface;

/**
 * Serialize data to JSON, unserialize JSON encoded data
 * Class Json
 * @package Xendit\M2Invoice\External\Serialize\Serializer
 */
class Json implements SerializerInterface
{
    /**
     * @param array|bool|float|int|string|null $data
     * @return bool|false|string
     */
    public function serialize($data)
    {
        $result = json_encode($data);
        if (false === $result) {
            throw new \InvalidArgumentException("Unable to serialize value. Error: " . json_last_error_msg());
        }
        return $result;
    }

    /**
     * @param string $string
     * @return array|bool|float|int|mixed|string|null
     */
    public function unserialize($string)
    {
        $result = json_decode($string, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("Unable to unserialize value. Error: " . json_last_error_msg());
        }
        return $result;
    }
}

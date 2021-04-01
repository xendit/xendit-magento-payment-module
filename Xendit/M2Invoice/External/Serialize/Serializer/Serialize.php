<?php

namespace Xendit\M2Invoice\External\Serialize\Serializer;

use Xendit\M2Invoice\External\Serialize\SerializerInterface;

/**
 * Less secure than Json implementation, but gives higher performance on big arrays. Does not unserialize objects.
 * Using this implementation is discouraged as it may lead to security vulnerabilities
 * Class Serialize
 * @package Xendit\M2Invoice\External\Serialize\Serializer
 */
class Serialize implements SerializerInterface
{
    /**
     * @param array|bool|float|int|string|null $data
     * @return bool|string
     */
    public function serialize($data)
    {
        if (is_resource($data)) {
            throw new \InvalidArgumentException('Unable to serialize value.');
        }
        return serialize($data);
    }

    /**
     * @param string $string
     * @return array|bool|float|int|mixed|string|null
     */
    public function unserialize($string)
    {
        if (false === $string || null === $string || '' === $string) {
            throw new \InvalidArgumentException('Unable to unserialize value.');
        }
        set_error_handler(
            function () {
                restore_error_handler();
                throw new \InvalidArgumentException('Unable to unserialize value, string is corrupted.');
            },
            E_NOTICE
        );
        $result = unserialize($string, ['allowed_classes' => false]);
        restore_error_handler();
        return $result;
    }
}

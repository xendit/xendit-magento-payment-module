<?php

declare(strict_types=1);

namespace Xendit\M2Invoice\External\Serialize\Serializer;

use Xendit\M2Invoice\External\Serialize\SerializerInterface;

/**
 * Serialize data to JSON with the JSON_HEX_TAG option enabled
 * (All < and > are converted to \u003C and \u003E),
 * unserialize JSON encoded data
 *
 * Class JsonHexTag
 * @package Xendit\M2Invoice\External\Serialize\Serializer
 */
class JsonHexTag extends Json implements SerializerInterface
{
    /**
     * @param array|bool|float|int|string|null $data
     * @return string
     */
    public function serialize($data): string
    {
        $result = json_encode($data, JSON_HEX_TAG);
        if (false === $result) {
            throw new \InvalidArgumentException('Unable to serialize value.');
        }
        return $result;
    }
}

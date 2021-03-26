<?php

declare(strict_types=1);

namespace Xendit\M2Invoice\External\Serialize\Serializer;

/**
 * Class for processing of serialized form data.
 * Class FormData
 * @package Xendit\M2Invoice\External\Serialize\Serializer
 */
class FormData
{
    /**
     * @var Json
     */
    private $serializer;

    /**
     * FormData constructor.
     * @param Json $serializer
     */
    public function __construct(
        Json $serializer
    ) {
        $this->serializer = $serializer;
    }

    /**
     * Provides form data from the serialized data.
     * @param string $serializedData
     * @return array
     */
    public function unserialize(string $serializedData): array
    {
        $encodedFields = $this->serializer->unserialize($serializedData);

        if (!is_array($encodedFields)) {
            throw new \InvalidArgumentException('Unable to unserialize value.');
        }

        $formData = [];
        foreach ($encodedFields as $item) {
            $decodedFieldData = [];
            parse_str($item, $decodedFieldData);
            $formData = array_replace_recursive($formData, $decodedFieldData);
        }

        return $formData;
    }
}

<?php

namespace Xendit\M2Invoice\Helper;

/**
 * Hardcoded ECDSA P-384 public keys for verifying integration notification signatures.
 *
 * Keys are shared with the WooCommerce plugin — both integrations run on the same
 * TPI Service deployment. Two sets: staging and production, selected at runtime
 * via xendit_app_env config. Multiple keys per environment support key rotation.
 */
class IntegrationNotificationKeys
{
    /**
     * Staging TPI Service public keys (ECDSA P-384)
     */
    public const STAGING_PUBLIC_KEYS = [
        "-----BEGIN PUBLIC KEY-----\nMHYwEAYHKoZIzj0CAQYFK4EEACIDYgAEhYdfdTwZDhMPRNCWB7sfZZgfEqzYcMgNEcNoReZBLtQ+cuPBcawbZVXox8TjxRQkZhTgvmBAE3Nr7XZgxmrRyFyAGhzF+peAgrEqDm4aWgwDU67V9Q66Zu7dCPQyw6nA\n-----END PUBLIC KEY-----",
        "-----BEGIN PUBLIC KEY-----\nMHYwEAYHKoZIzj0CAQYFK4EEACIDYgAEIE0YA+Z8m7mVU9d7vfzXbNt/Lre6UVxy8XRsn9u13kWjhQpwbPnOgTwBUSmJgI9hI8YyYWJNK0ACPxWHHUkSnXR8Cgr6cM9sPi7TPUcOyNhf0wbPS0QzEPdn4BWNGR9a\n-----END PUBLIC KEY-----",
    ];

    /**
     * Production TPI Service public keys (ECDSA P-384)
     */
    public const PRODUCTION_PUBLIC_KEYS = [
        "-----BEGIN PUBLIC KEY-----\nMHYwEAYHKoZIzj0CAQYFK4EEACIDYgAEqaSYgkq632WHte+NOc1QqCbDlYC7sB1tlDUf/8EBDApTwsB5k4pB9h5BDCG/8xOQhwl2dr1nNoOarD4uCNARgByQJ+S91iyfJqLp+JuQF1z3HKu51f1biV80RLqcaTPo\n-----END PUBLIC KEY-----",
        "-----BEGIN PUBLIC KEY-----\nMHYwEAYHKoZIzj0CAQYFK4EEACIDYgAE5Zg9WXeJw0GbEYZREVI+WOfPE4AbHCN8XfOsPFYge7EOrQsU1Vh24ZLzIOocQbRj76r1Kjp8JCZJM5iT2++RkCmIaY/iV/lxKaOL9/jINMQPqYeKFLbrliCejSEg8EE/\n-----END PUBLIC KEY-----",
    ];

    /**
     * Get the public keys for the given deployment environment.
     *
     * @param string $appEnv 'staging' or 'production'
     * @return string[]
     */
    public static function getPublicKeys(string $appEnv): array
    {
        return $appEnv === 'staging'
            ? self::STAGING_PUBLIC_KEYS
            : self::PRODUCTION_PUBLIC_KEYS;
    }
}

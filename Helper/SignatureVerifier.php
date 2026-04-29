<?php

namespace Xendit\M2Invoice\Helper;

/**
 * ECDSA P-384 / SHA-384 signature verification.
 *
 * Verifies integration notification signatures from TPI Service.
 * Handles raw-to-DER signature conversion (WebCrypto produces raw format,
 * PHP's openssl_verify expects DER format).
 *
 * Ported from the WooCommerce plugin's WC_Xendit_Signature_Verifier.
 */
class SignatureVerifier
{
    /**
     * Verify an ECDSA signature against a message using the given PEM public key.
     *
     * @param string $message The signed message (plaintext)
     * @param string $signatureBase64 Base64-encoded raw ECDSA signature
     * @param string $publicKeyPem PEM-encoded EC public key
     * @return bool
     */
    public function verify(string $message, string $signatureBase64, string $publicKeyPem): bool
    {
        $signatureBytes = base64_decode($signatureBase64, true);
        if ($signatureBytes === false) {
            return false;
        }

        $pubKey = openssl_pkey_get_public($publicKeyPem);
        if ($pubKey === false) {
            return false;
        }

        $derSignature = $this->rawToDerSignature($signatureBytes);
        $result = openssl_verify($message, $derSignature, $pubKey, OPENSSL_ALGO_SHA384);

        // Free key resource for PHP < 8.0
        if (PHP_VERSION_ID < 80000) {
            openssl_free_key($pubKey);
        }

        return $result === 1;
    }

    /**
     * Verify a signature against multiple public keys (for key rotation support).
     *
     * @param string $message The signed message
     * @param string $signatureBase64 Base64-encoded signature
     * @param string[] $publicKeys Array of PEM public keys
     * @return bool True if any key verifies successfully
     */
    public function verifyWithMultipleKeys(string $message, string $signatureBase64, array $publicKeys): bool
    {
        foreach ($publicKeys as $publicKeyPem) {
            if ($this->verify($message, $signatureBase64, $publicKeyPem)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Convert a raw ECDSA signature (r||s) to DER format.
     *
     * WebCrypto's ECDSA produces raw signatures (r and s concatenated).
     * PHP's openssl_verify expects DER-encoded ASN.1 format.
     * For P-384: raw signature is 96 bytes (48 bytes r + 48 bytes s).
     *
     * @param string $signature Raw signature bytes
     * @return string DER-encoded signature
     */
    private function rawToDerSignature(string $signature): string
    {
        // If already DER-encoded (starts with SEQUENCE tag 0x30), return as-is
        if (strlen($signature) > 0 && ord($signature[0]) === 0x30) {
            return $signature;
        }

        // P-384 raw signature = 96 bytes (48 r + 48 s)
        if (strlen($signature) !== 96) {
            return $signature;
        }

        $r = substr($signature, 0, 48);
        $s = substr($signature, 48, 48);

        // Strip leading zero bytes
        $r = ltrim($r, "\0");
        if (empty($r)) {
            $r = "\0";
        }
        $s = ltrim($s, "\0");
        if (empty($s)) {
            $s = "\0";
        }

        // Add leading zero byte if high bit is set (ASN.1 INTEGER is signed)
        if (ord($r[0]) & 0x80) {
            $r = "\0" . $r;
        }
        if (ord($s[0]) & 0x80) {
            $s = "\0" . $s;
        }

        $derR = "\x02" . chr(strlen($r)) . $r;
        $derS = "\x02" . chr(strlen($s)) . $s;
        $der = "\x30" . chr(strlen($derR . $derS)) . $derR . $derS;

        return $der;
    }
}

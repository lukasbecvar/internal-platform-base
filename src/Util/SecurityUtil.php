<?php

namespace App\Util;

/**
 * Class SecurityUtil
 *
 * Util for security related functionality
 *
 * @package App\Util
 */
class SecurityUtil
{
    private AppUtil $appUtil;

    public function __construct(AppUtil $appUtil)
    {
        $this->appUtil = $appUtil;
    }

    /**
     * Escape string
     *
     * @param string $string The string to escape
     *
     * @return string|null The escaped string
     */
    public function escapeString(string $string): ?string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Generate hash for given password
     *
     * @param string $password The password to hash
     *
     * @return string The hashed password
     */
    public function generateHash(string $password): string
    {
        $config = $this->appUtil->getHasherConfig();

        $options = [
            'threads' => $config['threads'],
            'time_cost' => $config['time_cost'],
            'memory_cost' => $config['memory_cost']
        ];

        // generate hash
        return password_hash($password, PASSWORD_ARGON2ID, $options);
    }

    /**
     * Verify if password hash is valid password
     *
     * @param string $password The password to verify
     * @param string $hash The hash to verify
     *
     * @return bool True if password is valid, false otherwise
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Encrypt string using AES encryption
     *
     * @param string $plainText The plain text to encrypt
     *
     * @return string The base64-encoded encrypted string
     */
    public function encryptAes(string $plainText): string
    {
        $key = hash('sha256', $_ENV['APP_SECRET'], true);
        $iv  = random_bytes(12);
        $cipher = 'aes-256-gcm';
        $tag = null;

        $cipherText = openssl_encrypt(
            data: $plainText,
            cipher_algo: $cipher,
            passphrase: $key,
            options: OPENSSL_RAW_DATA,
            iv: $iv,
            tag: $tag
        );

        return base64_encode($iv . $tag . $cipherText);
    }

    /**
     * Decrypt AES-encrypted string
     *
     * @param string $encryptedData The base64-encoded encrypted string
     *
     * @return string|null The decrypted string or null on error
     */
    public function decryptAes(string $encryptedData): ?string
    {
        // decode base64 string
        $raw = base64_decode($encryptedData);

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipherText = substr($raw, 28);
        $key = hash('sha256', $_ENV['APP_SECRET'], true);
        $cipher = 'aes-256-gcm';

        return openssl_decrypt(
            data: $cipherText,
            cipher_algo: $cipher,
            passphrase: $key,
            options: OPENSSL_RAW_DATA,
            iv: $iv,
            tag: $tag
        ) ?: null;
    }
}

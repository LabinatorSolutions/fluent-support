<?php

namespace FluentSupport\Framework\Encryption;

interface StringEncrypterInterface
{
    /**
     * Encrypt a string without serialization.
     *
     * @param  string  $value
     * @return string
     *
     * @throws \FluentSupport\Framework\Encryption\EncryptException
     */
    public function encryptString($value);

    /**
     * Decrypt the given string without unserialization.
     *
     * @param  string  $payload
     * @return string
     *
     * @throws \FluentSupport\Framework\Encryption\DecryptException
     */
    public function decryptString($payload);
}

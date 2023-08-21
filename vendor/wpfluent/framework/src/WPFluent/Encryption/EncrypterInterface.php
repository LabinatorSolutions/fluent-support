<?php

namespace FluentSupport\Framework\Encryption;

interface EncrypterInterface
{
    /**
     * Encrypt the given value.
     *
     * @param  mixed  $value
     * @param  bool  $serialize
     * @return string
     *
     * @throws \FluentSupport\Framework\Encryption\EncryptException
     */
    public function encrypt($value, $serialize = true);

    /**
     * Decrypt the given value.
     *
     * @param  string  $payload
     * @param  bool  $unserialize
     * @return mixed
     *
     * @throws \FluentSupport\Framework\Encryption\DecryptException
     */
    public function decrypt($payload, $unserialize = true);
}

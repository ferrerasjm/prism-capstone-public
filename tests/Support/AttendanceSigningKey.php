<?php

declare(strict_types=1);

namespace Tests\Support;

final class AttendanceSigningKey
{
    /** @return array{0: \OpenSSLAsymmetricKey, 1: string} */
    public static function generate(): array
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
            'config' => __DIR__.'/../Fixtures/openssl.cnf',
        ]);
        if ($key === false) {
            throw new \RuntimeException('Could not generate the test signing key: '.openssl_error_string());
        }
        $details = openssl_pkey_get_details($key);
        if ($details === false) {
            throw new \RuntimeException('Could not read the test signing public key.');
        }

        return [$key, $details['key']];
    }
}

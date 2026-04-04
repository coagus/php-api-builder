<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\CLI\Commands;

class KeysGenerateCommand implements CommandInterface
{
    public function execute(array $args): int
    {
        $dir = getcwd() . '/keys';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $type = $this->getOption($args, '--type', 'rsa');

        if ($type === 'rsa') {
            return $this->generateRsa($dir);
        }

        echo "Unsupported key type: {$type}. Supported: rsa\n";
        return 1;
    }

    private function generateRsa(string $dir): int
    {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $key = openssl_pkey_new($config);
        if ($key === false) {
            echo "Failed to generate RSA key pair.\n";
            return 1;
        }

        openssl_pkey_export($key, $privateKey);
        $publicKey = openssl_pkey_get_details($key)['key'];

        file_put_contents("{$dir}/private.pem", $privateKey);
        file_put_contents("{$dir}/public.pem", $publicKey);
        chmod("{$dir}/private.pem", 0600);

        echo "RSA key pair generated:\n";
        echo "  Private key: keys/private.pem\n";
        echo "  Public key:  keys/public.pem\n\n";
        echo "Update .env:\n";
        echo "  JWT_ALGORITHM=RS256\n";
        echo "  JWT_PRIVATE_KEY=./keys/private.pem\n";
        echo "  JWT_PUBLIC_KEY=./keys/public.pem\n";

        return 0;
    }

    private function getOption(array $args, string $name, string $default): string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "{$name}=")) {
                return substr($arg, strlen($name) + 1);
            }
        }

        return $default;
    }
}

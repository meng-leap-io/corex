<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

trait HasEncryptedAttributes
{
    abstract protected function getEncryptedAttributes(): array;

    public function initializeHasEncryptedAttributes(): void
    {
        foreach ($this->getEncryptedAttributes() as $attribute) {
            $this->mergeCasts([$attribute => 'encrypted']);
        }
    }

    public function getAttributeValue($key): mixed
    {
        $value = parent::getAttributeValue($key);

        if ($this->isEncrypted($key) && is_string($value)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (DecryptException $e) {
                Log::warning('encryption.decrypt_failed', [
                    'model' => static::class,
                    'attribute' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $value;
    }

    public function setAttribute($key, $value): mixed
    {
        if ($this->isEncrypted($key) && $value !== null) {
            $value = Crypt::encryptString((string) $value);
        }

        return parent::setAttribute($key, $value);
    }

    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();

        foreach ($this->getEncryptedAttributes() as $key) {
            if (isset($attributes[$key]) && $this->isEncrypted($key)) {
                try {
                    $attributes[$key] = Crypt::decryptString($attributes[$key]);
                } catch (DecryptException) {
                    $attributes[$key] = '[encrypted]';
                }
            }
        }

        return $attributes;
    }

    public function getEncryptedValue(string $key): ?string
    {
        $value = parent::getAttributeValue($key);

        if (!is_string($value) || !$this->isEncrypted($key)) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    public function setEncryptedValue(string $key, mixed $value): static
    {
        $this->setAttribute($key, $value);

        return $this;
    }

    public function reEncrypt(): void
    {
        $dirty = false;

        foreach ($this->getEncryptedAttributes() as $attribute) {
            $rawValue = $this->attributes[$attribute] ?? null;

            if ($rawValue === null) {
                continue;
            }

            try {
                $decrypted = Crypt::decryptString($rawValue);
                $reEncrypted = Crypt::encryptString($decrypted);

                if ($reEncrypted !== $rawValue) {
                    $this->attributes[$attribute] = $reEncrypted;
                    $dirty = true;
                }
            } catch (DecryptException $e) {
                Log::warning('encryption.re_encrypt_failed', [
                    'model' => static::class,
                    'attribute' => $attribute,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($dirty) {
            $this->saveQuietly();
        }
    }

    protected function isEncrypted(string $key): bool
    {
        return in_array($key, $this->getEncryptedAttributes(), true);
    }
}

<?php
/**
 * Minimal cache compatibility class.
 */

if (class_exists('PayPalacCache', false)) {
    return;
}

/**
 * Lightweight cache implementation used when the Zen Cart core cache class
 * is not available.
 */
class PayPalacCache
{
    /** @var array<string, mixed> */
    protected $storage = [];

    public function __construct()
    {
    }

    public function clear_cache(string $name = ''): void
    {
        if ($name === '') {
            $this->storage = [];
            return;
        }

        unset($this->storage[$name]);
    }

    public function write(string $name, $value, int $ttl = 0): void
    {
        $this->storage[$name] = $value;
    }

    public function read(string $name)
    {
        return $this->storage[$name] ?? false;
    }

    public function sql_cache_flush_cache(): void
    {
        $this->storage = [];
    }

    public function __call(string $name, array $arguments)
    {
        // Silently ignore undefined cache interactions for compatibility.
        return null;
    }
}

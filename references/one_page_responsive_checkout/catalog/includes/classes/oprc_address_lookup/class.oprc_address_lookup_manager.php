<?php
interface OPRC_Address_Lookup_Provider
{
    /**
     * Returns the provider key.
     *
     * @return string
     */
    public function getKey();

    /**
     * Returns a human friendly provider title.
     *
     * @return string
     */
    public function getTitle();

    /**
     * Executes the lookup for the supplied postal code.
     *
     * @param string $postalCode
     * @param array $context
     *
     * @return array[]
     */
    public function lookup($postalCode, array $context = []);
}

abstract class OPRC_Address_Lookup_AbstractProvider implements OPRC_Address_Lookup_Provider
{
    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var string
     */
    protected $key = '';

    /**
     * @var string
     */
    protected $title = '';

    public function __construct(array $config = [])
    {
        $this->config = $config;
        if (isset($config['key'])) {
            $this->key = (string)$config['key'];
        }
        if (isset($config['title'])) {
            $this->title = (string)$config['title'];
        }
    }

    public function getKey()
    {
        return $this->key;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function isConfigured()
    {
        return true;
    }

    /**
     * Helper accessor for provider configuration values.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getConfig($key, $default = null)
    {
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }
}

class OPRC_Address_Lookup_Manager
{
    /**
     * @var OPRC_Address_Lookup_Manager
     */
    protected static $instance;

    /**
     * @var OPRC_Address_Lookup_Provider|null
     */
    protected $provider;

    /**
     * @var array
     */
    protected $providerDefinition = [];

    /**
     * @var string
     */
    protected $lastError = '';

    protected function __construct()
    {
        $this->initialize();
    }

    /**
     * @return OPRC_Address_Lookup_Manager
     */
    public static function instance()
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    protected function initialize()
    {
        if (!defined('OPRC_ADDRESS_LOOKUP_PROVIDER')) {
            return;
        }

        $providerKey = trim(OPRC_ADDRESS_LOOKUP_PROVIDER);
        if ($providerKey === '') {
            return;
        }

        $provider = $this->loadProvider($providerKey);
        if ($provider instanceof OPRC_Address_Lookup_Provider) {
            $this->provider = $provider;
        }
    }

    /**
     * @param string $providerKey
     * @return OPRC_Address_Lookup_Provider|null
     */
    protected function loadProvider($providerKey)
    {
        $definitions = $this->discoverProviders();
        if (!isset($definitions[$providerKey])) {
            return null;
        }

        $definition = $definitions[$providerKey];
        if (!isset($definition['key'])) {
            $definition['key'] = $providerKey;
        }

        if (!empty($definition['file']) && file_exists($definition['file'])) {
            require_once $definition['file'];
        }

        $provider = null;
        if (isset($definition['factory']) && is_callable($definition['factory'])) {
            $provider = call_user_func($definition['factory'], $definition);
        } elseif (!empty($definition['class']) && class_exists($definition['class'])) {
            $provider = new $definition['class']($definition);
        }

        if ($provider instanceof OPRC_Address_Lookup_Provider) {
            if (!$this->isProviderConfigured($provider)) {
                $this->providerDefinition = [];
                return null;
            }

            $this->providerDefinition = $definition;
            return $provider;
        }

        return null;
    }

    /**
     * @return array
     */
    protected function discoverProviders()
    {
        $providers = [];

        if (isset($GLOBALS['OPRC_ADDRESS_LOOKUP_PROVIDER_REGISTRY']) && is_array($GLOBALS['OPRC_ADDRESS_LOOKUP_PROVIDER_REGISTRY'])) {
            foreach ($GLOBALS['OPRC_ADDRESS_LOOKUP_PROVIDER_REGISTRY'] as $key => $definition) {
                if (!is_array($definition)) {
                    continue;
                }

                $providerKey = isset($definition['key']) ? (string)$definition['key'] : (string)$key;
                if ($providerKey === '') {
                    continue;
                }

                if (!isset($definition['key'])) {
                    $definition['key'] = $providerKey;
                }

                if (!isset($providers[$providerKey])) {
                    $providers[$providerKey] = $definition;
                }
            }
        }

        $directory = DIR_FS_CATALOG . 'includes/modules/oprc_address_lookup/providers';
        if (is_dir($directory)) {
            foreach (glob($directory . '/*.php') as $file) {
                $definition = include $file;
                if (!is_array($definition)) {
                    continue;
                }

                $providerKey = isset($definition['key']) ? (string)$definition['key'] : '';
                if ($providerKey === '') {
                    continue;
                }

                if (!isset($definition['file'])) {
                    $definition['file'] = $file;
                }

                if (!isset($providers[$providerKey])) {
                    $providers[$providerKey] = $definition;
                }
            }
        }

        return $providers;
    }

    protected function isProviderConfigured(OPRC_Address_Lookup_Provider $provider)
    {
        if (method_exists($provider, 'isConfigured')) {
            try {
                return (bool)$provider->isConfigured();
            } catch (Exception $exception) {
                $this->lastError = $exception->getMessage();
                return false;
            }
        }

        return true;
    }

    public function isEnabled()
    {
        return $this->provider instanceof OPRC_Address_Lookup_Provider;
    }

    public function getProviderKey()
    {
        if ($this->provider instanceof OPRC_Address_Lookup_Provider) {
            $key = $this->provider->getKey();
            if ($key !== '') {
                return $key;
            }
        }

        return isset($this->providerDefinition['key']) ? (string)$this->providerDefinition['key'] : '';
    }

    public function getProviderTitle()
    {
        if ($this->provider instanceof OPRC_Address_Lookup_Provider) {
            $title = $this->provider->getTitle();
            if ($title !== '') {
                return $title;
            }
        }

        return isset($this->providerDefinition['title']) ? (string)$this->providerDefinition['title'] : '';
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * @param string $postalCode
     * @param array $context
     * @return array
     */
    public function lookup($postalCode, array $context = [])
    {
        $this->lastError = '';
        if (!$this->isEnabled()) {
            return [];
        }

        try {
            $results = $this->provider->lookup($postalCode, $context);
        } catch (Exception $exception) {
            $this->lastError = $exception->getMessage();
            return [];
        }

        return $this->normalizeResults($results);
    }

    /**
     * @param array $results
     * @return array
     */
    protected function normalizeResults($results)
    {
        if (!is_array($results)) {
            return [];
        }

        $normalized = [];
        foreach ($results as $result) {
            if (!is_array($result)) {
                continue;
            }

            $label = isset($result['label']) ? (string)$result['label'] : '';
            if ($label === '') {
                continue;
            }

            $fields = [];
            if (isset($result['fields']) && is_array($result['fields'])) {
                foreach ($result['fields'] as $field => $value) {
                    $fields[(string)$field] = (string)$value;
                }
            }

            $normalized[] = [
                'label' => $label,
                'fields' => $fields,
                'meta' => isset($result['meta']) && is_array($result['meta']) ? $result['meta'] : []
            ];

            if (defined('OPRC_ADDRESS_LOOKUP_MAX_RESULTS')) {
                if (count($normalized) >= (int)OPRC_ADDRESS_LOOKUP_MAX_RESULTS) {
                    break;
                }
            }
        }

        return $normalized;
    }
}
// eof

<?php
namespace PayPalRestful\Compatibility;

if (class_exists('base', false)) {
    abstract class LanguageShimBase extends \base
    {
    }
} else {
    abstract class LanguageShimBase
    {
    }
}

final class LanguageShim extends LanguageShimBase
{
    /** @var array<string,mixed> */
    public array $language = [];

    /** @var array<string,array<string,mixed>> */
    public array $catalog_languages = [];

    /** @var array<string,array<string,mixed>> */
    private array $languagesByCode = [];

    /** @var array<int,array<string,mixed>> */
    private array $languagesById = [];

    /** @var array<string,array<string,mixed>> */
    private array $languagesByDirectory = [];

    public function __construct(string $language = '')
    {
        $this->initializeLanguages();
        $this->set_language($language !== '' ? $language : $this->getDefaultDirectory());
    }

    private function initializeLanguages(): void
    {
        $this->registerLanguage([
            'id' => 1,
            'name' => ucfirst($this->getDefaultDirectory()),
            'image' => $this->getDefaultCode($this->getDefaultDirectory()) . '.png',
            'code' => $this->getDefaultCode($this->getDefaultDirectory()),
            'directory' => $this->getDefaultDirectory(),
        ]);

        foreach ($this->discoverAdditionalLanguages() as $language) {
            $this->registerLanguage($language);
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function discoverAdditionalLanguages(): array
    {
        $languages = [];

        if (defined('PAYPALR_AVAILABLE_LANGUAGES') && is_array(PAYPALR_AVAILABLE_LANGUAGES)) {
            foreach (PAYPALR_AVAILABLE_LANGUAGES as $language) {
                if (is_array($language) && isset($language['code'], $language['directory'])) {
                    $languages[] = $this->normaliseLanguageArray($language);
                } elseif (is_string($language) && $language !== '') {
                    $languages[] = $this->languageFromString($language);
                }
            }
        }

        return $languages;
    }

    /**
     * @param array<string,mixed> $language
     * @return array<string,mixed>
     */
    private function normaliseLanguageArray(array $language): array
    {
        $directory = strtolower((string) ($language['directory'] ?? ''));
        $code = strtolower((string) ($language['code'] ?? ''));

        return [
            'id' => (int) ($language['id'] ?? (count($this->languagesById) + 1)),
            'name' => (string) ($language['name'] ?? ucfirst($directory)),
            'image' => (string) ($language['image'] ?? ($code . '.png')),
            'code' => $code,
            'directory' => $directory,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function languageFromString(string $language): array
    {
        $directory = strtolower(trim($language));
        $code = $this->getDefaultCode($directory);

        return [
            'id' => count($this->languagesById) + 1,
            'name' => ucfirst($directory),
            'image' => $code . '.png',
            'code' => $code,
            'directory' => $directory,
        ];
    }

    /**
     * @param array<string,mixed> $language
     */
    private function registerLanguage(array $language): void
    {
        $code = strtolower((string) ($language['code'] ?? ''));
        $directory = strtolower((string) ($language['directory'] ?? ''));
        if ($code === '' || $directory === '') {
            return;
        }

        $id = (int) ($language['id'] ?? (count($this->languagesById) + 1));
        $language['id'] = $id;
        $language['name'] = (string) ($language['name'] ?? ucfirst($directory));
        $language['image'] = (string) ($language['image'] ?? ($code . '.png'));

        $this->languagesByCode[$code] = $language;
        $this->languagesByDirectory[$directory] = $language;
        $this->languagesById[$id] = $language;
        $this->catalog_languages[$code] = $language;
    }

    public function set_language(string $language): void
    {
        $languageInfo = $this->findLanguage($language);
        if ($languageInfo === null) {
            $languageInfo = $this->findLanguage($this->getDefaultDirectory());
        }

        if ($languageInfo === null) {
            $languageInfo = $this->languageFromString($this->getDefaultDirectory());
            $this->registerLanguage($languageInfo);
        }

        $this->language = $languageInfo;
    }

    public function get_browser_language(): void
    {
        if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return;
        }

        $fragments = explode(',', (string) $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        foreach ($fragments as $fragment) {
            $fragment = strtolower(trim($fragment));
            if ($fragment === '') {
                continue;
            }

            $languageInfo = $this->findLanguage($fragment);
            if ($languageInfo !== null) {
                $this->language = $languageInfo;
                return;
            }

            $primary = substr($fragment, 0, 2);
            $languageInfo = $this->findLanguage($primary);
            if ($languageInfo !== null) {
                $this->language = $languageInfo;
                return;
            }
        }
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function get_languages_by_code(): array
    {
        return $this->languagesByCode;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function get_languages_by_id(): array
    {
        return $this->languagesById;
    }

    /**
     * @return array<int,string>
     */
    public function get_language_list(): array
    {
        $list = [];
        foreach ($this->languagesById as $language) {
            $list[$language['id']] = $language['code'];
        }

        return $list;
    }

    private function findLanguage(string $language): ?array
    {
        $key = strtolower(trim($language));
        if ($key === '') {
            return null;
        }

        if (isset($this->languagesByCode[$key])) {
            return $this->languagesByCode[$key];
        }

        if (isset($this->languagesByDirectory[$key])) {
            return $this->languagesByDirectory[$key];
        }

        foreach ($this->languagesByCode as $info) {
            if (strtolower((string) ($info['name'] ?? '')) === $key) {
                return $info;
            }
        }

        return null;
    }

    private function getDefaultDirectory(): string
    {
        $default = defined('DEFAULT_LANGUAGE') ? strtolower((string) DEFAULT_LANGUAGE) : 'english';

        return $default !== '' ? $default : 'english';
    }

    private function getDefaultCode(string $directory): string
    {
        if (defined('DEFAULT_LANGUAGE_CODE') && DEFAULT_LANGUAGE_CODE !== '') {
            return strtolower((string) DEFAULT_LANGUAGE_CODE);
        }

        if (preg_match('/^[a-z]{2}/', $directory, $matches)) {
            return strtolower($matches[0]);
        }

        return 'en';
    }
}

if (!class_exists('language', false)) {
    class_alias(__NAMESPACE__ . '\\LanguageShim', 'language');
}

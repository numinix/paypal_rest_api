<?php
/**
 * Minimal compatibility layer for Zen Cart's template_func helper.
 */

if (class_exists('template_func')) {
    return;
}

class template_func
{
    /**
     * Determine the directory that should contain the requested template asset.
     */
    public function get_template_dir($file, string $base_dir, $current_page_base = '', string $template_type = 'templates'): string
    {
        $base_dir = $this->normaliseBaseDir($base_dir);
        $template_candidates = $this->candidateDirectories($base_dir, $template_type);

        foreach ($template_candidates as $directory) {
            if ($this->templateFileExists($directory, $file)) {
                return $directory;
            }
        }

        return $template_candidates[0] ?? $base_dir;
    }

    protected function normaliseBaseDir(string $base_dir): string
    {
        if ($base_dir === '') {
            $base_dir = 'includes/templates/';
        }

        if ($base_dir[0] === '/' || preg_match('/^[A-Za-z]:\\\\/', $base_dir) === 1) {
            return rtrim($base_dir, '/');
        }

        $catalog = defined('DIR_FS_CATALOG') ? rtrim((string) DIR_FS_CATALOG, '/') : getcwd();

        return rtrim($catalog . '/' . ltrim($base_dir, '/'), '/');
    }

    protected function candidateDirectories(string $base_dir, string $template_type): array
    {
        $directories = [];
        $template_directory = $this->determineTemplateDirectory();

        if ($template_directory !== null) {
            $directories[] = $base_dir . '/' . $template_directory . '/' . $template_type;
        }

        $directories[] = $base_dir . '/template_default/' . $template_type;

        return array_values(array_unique(array_map(static fn ($dir) => rtrim($dir, '/'), $directories)));
    }

    protected function determineTemplateDirectory(): ?string
    {
        if (defined('DIR_WS_TEMPLATE')) {
            $template = basename(rtrim((string) DIR_WS_TEMPLATE, '/'));
            if ($template !== '' && $template !== '.' && $template !== '..') {
                return $template;
            }
        }

        if (!empty($_SESSION['tplDir'])) {
            $template = basename((string) $_SESSION['tplDir']);
            if ($template !== '' && $template !== '.' && $template !== '..') {
                return $template;
            }
        }

        return null;
    }

    protected function templateFileExists(string $directory, $file): bool
    {
        if (!is_dir($directory)) {
            return false;
        }

        $directory = rtrim($directory, '/');
        $pattern = $this->normalisePattern($file);

        if ($pattern === null) {
            $path = $directory . '/' . ltrim((string) $file, '/');
            return is_file($path);
        }

        foreach (glob($directory . '/*') ?: [] as $candidate) {
            if (preg_match($pattern, basename($candidate))) {
                return true;
            }
        }

        return false;
    }

    protected function normalisePattern($pattern): ?string
    {
        if (!is_string($pattern)) {
            return null;
        }

        if ($pattern === '') {
            return null;
        }

        if ($pattern[0] !== '^' && strpos($pattern, '*') === false) {
            return null;
        }

        $delimited = '#'
            . str_replace('#', '\\#', strtr($pattern, ['*' => '.*']))
            . '#i';

        return $delimited;
    }
}

<?php
/**
 * Store-credit compatibility helpers for the PayPal Advanced Checkout module.
 *
 * This helper guards the third-party `ot_sc` order-total module against PHP 8+ "Attempt to modify
 * property \"fields\" on null" fatals that surface when the module's lookup query returns no row.
 * The patch is applied in-place since the upstream package doesn't currently provide the guard.
 *
 * @copyright Copyright 2025 Zen Cart Development Team
 * @license   https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */

namespace PayPalRestful\Compatibility;

class StoreCredit
{
    private const PATCH_MARKER = 'paypalr store-credit null guard';

    /**
     * Ensures that the ot_sc order-total module's apply_credit method handles null queryFactory
     * results safely. When the optional $overridePath is supplied, that file is patched instead of
     * the storefront's live module—this is primarily used by the automated tests.
     */
    public static function ensureSafeApplyCredit(?string $overridePath = null): void
    {
        $modulePath = $overridePath ?? self::getStoreCreditModulePath();
        if ($modulePath === null) {
            return;
        }

        if (!is_file($modulePath) || !is_readable($modulePath)) {
            return;
        }

        $contents = file_get_contents($modulePath);
        if ($contents === false || strpos($contents, self::PATCH_MARKER) !== false) {
            return;
        }

        if (!preg_match('/function\s+apply_credit\s*\([^)]*\)\s*\{/', $contents)) {
            return;
        }

        if (!preg_match_all('/\$this->([A-Za-z_][A-Za-z0-9_]*)->fields/', $contents, $matches)) {
            return;
        }

        $properties = array_values(array_unique($matches[1]));
        if ($properties === []) {
            return;
        }

        $guardLines = [sprintf('        // %s', self::PATCH_MARKER)];
        foreach ($properties as $property) {
            $guardLines[] = sprintf(
                '        if (!isset($this->%1$s) || !is_object($this->%1$s)) {',
                $property
            );
            $guardLines[] = '            return;';
            $guardLines[] = '        }';
            $guardLines[] = sprintf(
                '        if (!isset($this->%1$s->fields) || !is_array($this->%1$s->fields)) {',
                $property
            );
            $guardLines[] = sprintf('            $this->%1$s->fields = [];', $property);
            $guardLines[] = '        }';
        }
        $guardLines[] = '';

        $guardBlock = implode(PHP_EOL, $guardLines);

        $patchedContents = preg_replace(
            '/(function\s+apply_credit\s*\([^)]*\)\s*\{\s*)/',
            "$1\n$guardBlock",
            $contents,
            1,
            $count
        );

        if ($count !== 1) {
            return;
        }

        if (!is_writable($modulePath)) {
            return;
        }

        file_put_contents($modulePath, $patchedContents);
    }

    private static function getStoreCreditModulePath(): ?string
    {
        if (!defined('DIR_FS_CATALOG') || !defined('DIR_WS_MODULES')) {
            return null;
        }

        $path = DIR_FS_CATALOG . DIR_WS_MODULES . 'order_total/ot_sc.php';
        return $path;
    }
}


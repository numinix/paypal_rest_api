<?php
/**
 * Lightweight compatibility implementation of Zen Cart's sniffer class.
 */

if (class_exists('sniffer')) {
    return;
}

class sniffer
{
    /**
     * Database adapter instance.
     */
    private mixed $db = null;

    public function __construct(mixed $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
            return;
        }

        if (isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $this->db = $GLOBALS['db'];
        }
    }

    public function field_exists(string $tableName, string $columnName): bool
    {
        return $this->describeColumn($tableName, $columnName) !== null;
    }

    public function field_type(string $tableName, string $columnName, string $expectedType): bool
    {
        $column = $this->describeColumn($tableName, $columnName);
        if ($column === null) {
            return false;
        }

        $actualType = $this->extractColumnType($column);
        if ($actualType === null) {
            return false;
        }

        return $this->normalizeType($actualType) === $this->normalizeType($expectedType);
    }

    private function describeColumn(string $tableName, string $columnName): ?array
    {
        $db = $this->resolveDb();
        if ($db === null) {
            return null;
        }

        $quotedTable = $this->quoteIdentifier($tableName);
        $escapedColumn = $this->escapeValue($columnName);

        if ($quotedTable === '' || $escapedColumn === '') {
            return null;
        }

        $sql = sprintf("SHOW COLUMNS FROM %s LIKE '%s'", $quotedTable, $escapedColumn);

        try {
            $result = $db->Execute($sql);
        } catch (\Throwable $exception) {
            return null;
        }

        return $this->extractFirstRow($result);
    }

    private function resolveDb(): mixed
    {
        if (isset($this->db) && is_object($this->db)) {
            return $this->db;
        }

        if (isset($GLOBALS['db']) && is_object($GLOBALS['db'])) {
            $this->db = $GLOBALS['db'];
            return $this->db;
        }

        return null;
    }

    private function extractFirstRow(mixed $result): ?array
    {
        if (is_array($result)) {
            if (empty($result)) {
                return null;
            }

            $first = reset($result);
            return is_array($first) ? $first : (array) $first;
        }

        if (!is_object($result)) {
            return null;
        }

        if (isset($result->EOF) && $result->EOF) {
            return null;
        }

        if (isset($result->fields) && is_array($result->fields) && !empty($result->fields)) {
            return $result->fields;
        }

        if (method_exists($result, 'fields')) {
            $fields = $result->fields();
            if (is_array($fields) && !empty($fields)) {
                return $fields;
            }
        }

        if (method_exists($result, 'fetch_assoc')) {
            $row = $result->fetch_assoc();
            if (is_array($row) && !empty($row)) {
                return $row;
            }
        }

        if (method_exists($result, 'fetch')) {
            $row = $result->fetch();
            if ($row) {
                return (array) $row;
            }
        }

        if ($result instanceof \Traversable) {
            foreach ($result as $row) {
                return is_array($row) ? $row : (array) $row;
            }
            return null;
        }

        if (isset($result->fields) && is_array($result->fields)) {
            return $result->fields;
        }

        return null;
    }

    private function extractColumnType(array $column): ?string
    {
        foreach (['Type', 'type', 1] as $key) {
            if (isset($column[$key])) {
                return (string) $column[$key];
            }
        }

        return null;
    }

    private function normalizeType(string $type): string
    {
        $lower = strtolower($type);
        $stripped = preg_replace('/\s+/', '', $lower);

        return $stripped ?? $lower;
    }

    private function quoteIdentifier(string $identifier): string
    {
        $trimmed = trim($identifier);
        if ($trimmed === '') {
            return '';
        }

        $parts = explode('.', $trimmed);
        foreach ($parts as &$part) {
            $part = '`' . str_replace('`', '``', $part) . '`';
        }

        return implode('.', $parts);
    }

    private function escapeValue(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        return addcslashes($trimmed, "\\_%'");
    }
}

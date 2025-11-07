<?php
/**
 * Lightweight compatibility implementation of Zen Cart's zcDate class.
 */

if (class_exists('zcDate')) {
    return;
}

class zcDate
{
    /** @var \DateTimeZone */
    private $timezone;

    public function __construct(?\DateTimeZone $timezone = null)
    {
        $this->timezone = $timezone ?? new \DateTimeZone(date_default_timezone_get());
    }

    public function setTimeZone(\DateTimeZone $timezone): void
    {
        $this->timezone = $timezone;
    }

    /**
     * @param mixed $timestamp
     */
    public function output(string $format, $timestamp = null): string
    {
        $dateTime = $this->normalizeToDateTime($timestamp);

        $convertedFormat = $this->convertFormat($format);

        return $dateTime->setTimezone($this->timezone)->format($convertedFormat);
    }

    /**
     * @param mixed $timestamp
     */
    private function normalizeToDateTime($timestamp): \DateTimeImmutable
    {
        if ($timestamp instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($timestamp);
        }

        if ($timestamp === null) {
            return new \DateTimeImmutable('now', $this->timezone);
        }

        if (is_int($timestamp)) {
            return (new \DateTimeImmutable('@' . $timestamp))->setTimezone($this->timezone);
        }

        if (is_numeric($timestamp)) {
            return (new \DateTimeImmutable('@' . (int) $timestamp))->setTimezone($this->timezone);
        }

        try {
            return new \DateTimeImmutable((string) $timestamp, $this->timezone);
        } catch (\Exception $exception) {
            return new \DateTimeImmutable('now', $this->timezone);
        }
    }

    private function convertFormat(string $format): string
    {
        $replacements = [
            '%a' => 'D',
            '%A' => 'l',
            '%b' => 'M',
            '%B' => 'F',
            '%d' => 'd',
            '%e' => 'j',
            '%H' => 'H',
            '%I' => 'h',
            '%m' => 'm',
            '%M' => 'i',
            '%p' => 'A',
            '%S' => 's',
            '%y' => 'y',
            '%Y' => 'Y',
            '%Z' => 'T',
            '%z' => 'O',
            '%%' => '%',
        ];

        $converted = strtr($format, $replacements);

        return preg_replace('/%./', '', $converted) ?? $converted;
    }
}

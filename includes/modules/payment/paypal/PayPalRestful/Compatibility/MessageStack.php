<?php
/**
 * Lightweight messageStack compatibility class.
 */

if (class_exists('messageStack')) {
    return;
}

class messageStack
{
    /** @var array<string, array<int, array{text: string, type: string}>> */
    protected $messages = [];

    public function add_session($stack, $message = null, $type = 'error'): void
    {
        if ($message === null) {
            $message = (string) $stack;
            $stack = 'header';
        }

        $stack = $this->normaliseStackName($stack);
        $this->messages[$stack][] = ['text' => (string) $message, 'type' => (string) $type];

        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }
        if (!isset($_SESSION['messageToStack']) || !is_array($_SESSION['messageToStack'])) {
            $_SESSION['messageToStack'] = [];
        }
        $_SESSION['messageToStack'][$stack][] = ['text' => (string) $message, 'type' => (string) $type];
    }

    public function add($stack, $message, $type = 'error'): void
    {
        $stack = $this->normaliseStackName($stack);
        $this->messages[$stack][] = ['text' => (string) $message, 'type' => (string) $type];
    }

    public function size($stack = 'header'): int
    {
        $stack = $this->normaliseStackName($stack);

        return count($this->messages[$stack] ?? []);
    }

    public function reset(): void
    {
        $this->messages = [];
    }

    protected function normaliseStackName($stack): string
    {
        $stack = (string) $stack;
        $stack = trim($stack);

        if ($stack === '') {
            return 'header';
        }

        return $stack;
    }
}

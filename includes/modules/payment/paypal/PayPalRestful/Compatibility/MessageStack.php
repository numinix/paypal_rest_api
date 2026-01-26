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

    public function __construct()
    {
        // Load messages from session on initialization
        if (isset($_SESSION['messageToStack']) && is_array($_SESSION['messageToStack'])) {
            foreach ($_SESSION['messageToStack'] as $stack => $stackMessages) {
                if (is_array($stackMessages)) {
                    foreach ($stackMessages as $msg) {
                        if (isset($msg['text'], $msg['type'])) {
                            $this->messages[$stack][] = $msg;
                        }
                    }
                }
            }
            // Clear session messages after loading
            unset($_SESSION['messageToStack']);
        }
    }

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

    public function output($stack = 'header'): string
    {
        $stack = $this->normaliseStackName($stack);
        $messages = $this->messages[$stack] ?? [];

        if (empty($messages)) {
            return '';
        }

        $output = '';
        foreach ($messages as $msg) {
            $type = $msg['type'] ?? 'error';
            $text = $msg['text'] ?? '';
            
            // Map message types to alert classes
            $alertClass = 'alert-info';
            if ($type === 'error') {
                $alertClass = 'alert-danger';
            } elseif ($type === 'success') {
                $alertClass = 'alert-success';
            } elseif ($type === 'warning') {
                $alertClass = 'alert-warning';
            }
            
            $output .= '<div class="messageStack-header noprint">';
            $output .= '<div class="row messageStackAlert alert ' . $alertClass . '">';
            $output .= htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
            $output .= '</div>';
            $output .= '</div>';
        }

        return $output;
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

<?php
/**
 * Lightweight compatibility implementation of Zen Cart's notifier for
 * environments where the core class isn't yet available.
 */

if (class_exists('notifier')) {
    return;
}

trait PayPalAdvancedCheckoutLegacyNotifierTrait
{
    /**
     * Map of historical notifier aliases used by the core implementation.
     */
    /**
     * @var array<string, string>
     */
    private $observerAliases = [
        'NOTIFIY_ORDER_CART_SUBTOTAL_CALCULATE' => 'NOTIFY_ORDER_CART_SUBTOTAL_CALCULATE',
        'NOTIFY_ADMIN_INVOIVE_HEADERS_AFTER_TAX' => 'NOTIFY_ADMIN_INVOICE_HEADERS_AFTER_TAX',
    ];

    /**
     * Registry of observers keyed by event identifier and observer hash.
     */
    /**
     * @var array<string, array<string, array{obs: object, eventID: string}>>
     */
    private $registeredObservers = [];

    /**
     * Attach an observer to the supplied notifier events.
     */
    public function attach(&$observer, $eventIDArray): void
    {
        if (!is_object($observer)) {
            return;
        }

        $eventIDs = is_array($eventIDArray) ? $eventIDArray : [$eventIDArray];
        $hash = spl_object_hash($observer);

        foreach ($eventIDs as $eventID) {
            if (!is_string($eventID) || $eventID === '') {
                continue;
            }

            if (!isset($this->registeredObservers[$eventID])) {
                $this->registeredObservers[$eventID] = [];
            }

            if (!isset($this->registeredObservers[$eventID][$hash])) {
                $this->registeredObservers[$eventID][$hash] = [
                    'obs' => $observer,
                    'eventID' => $eventID,
                ];
            }
        }
    }

    /**
     * Detach an observer from one or more notifier events.
     */
    public function detach($observer, $eventIDArray): void
    {
        if (!is_object($observer)) {
            return;
        }

        $eventIDs = is_array($eventIDArray) ? $eventIDArray : [$eventIDArray];
        $hash = spl_object_hash($observer);

        if (empty($eventIDs)) {
            foreach (array_keys($this->registeredObservers) as $eventID) {
                unset($this->registeredObservers[$eventID][$hash]);
                if (empty($this->registeredObservers[$eventID])) {
                    unset($this->registeredObservers[$eventID]);
                }
            }

            return;
        }

        foreach ($eventIDs as $eventID) {
            if (!isset($this->registeredObservers[$eventID][$hash])) {
                continue;
            }

            unset($this->registeredObservers[$eventID][$hash]);

            if (empty($this->registeredObservers[$eventID])) {
                unset($this->registeredObservers[$eventID]);
            }
        }
    }

    /**
     * Notify listeners that an event has been triggered.
     */
    public function notify(
        $eventID,
        $param1 = [],
        &$param2 = null,
        &$param3 = null,
        &$param4 = null,
        &$param5 = null,
        &$param6 = null,
        &$param7 = null,
        &$param8 = null,
        &$param9 = null
    ) {
        $observers = $this->collectObservers($eventID);

        if (empty($observers)) {
            return;
        }

        foreach ($observers as $observerInfo) {
            $observer = $observerInfo['obs'];

            if (!is_object($observer)) {
                continue;
            }

            $observerEvent = $observerInfo['eventID'] === '*' ? $eventID : $observerInfo['eventID'];
            $methodsToCheck = $this->determineObserverMethods($observer, $observerEvent);

            foreach ($methodsToCheck as $method) {
                if (!method_exists($observer, $method)) {
                    continue;
                }

                $observer->{$method}(
                    $this,
                    $observerEvent,
                    $param1,
                    $param2,
                    $param3,
                    $param4,
                    $param5,
                    $param6,
                    $param7,
                    $param8,
                    $param9
                );

                continue 2;
            }
        }
    }

    /**
     * Return the observer registry in a format similar to the core notifier.
     */
    public function getRegisteredObservers(): array
    {
        $flattened = [];

        foreach ($this->registeredObservers as $eventID => $observers) {
            foreach ($observers as $observer) {
                $flattened[] = [
                    'obs' => $observer['obs'],
                    'eventID' => $eventID,
                ];
            }
        }

        return $flattened;
    }

    /**
     * Allow modules to add their own alias mappings.
     */
    public function registerObserverAlias(string $oldEventId, string $newEventId): void
    {
        if ($this->eventIdHasAlias($oldEventId)) {
            return;
        }

        $this->observerAliases[$oldEventId] = $newEventId;
    }

    private function collectObservers(string $eventID): array
    {
        $collected = [];

        $candidateEvents = [$eventID];
        $alias = $this->substituteAlias($eventID);

        if ($alias !== false && $alias !== $eventID) {
            $candidateEvents[] = $alias;
        }

        foreach ($candidateEvents as $candidate) {
            if (empty($this->registeredObservers[$candidate])) {
                continue;
            }

            foreach ($this->registeredObservers[$candidate] as $hash => $observer) {
                $collected[$hash . '|' . $observer['eventID']] = $observer;
            }
        }

        if (!empty($this->registeredObservers['*'])) {
            foreach ($this->registeredObservers['*'] as $hash => $observer) {
                $collected[$hash . '|*'] = [
                    'obs' => $observer['obs'],
                    'eventID' => '*',
                ];
            }
        }

        return array_values($collected);
    }

    private function determineObserverMethods(object $observer, string $eventID): array
    {
        $methods = [];
        $snakeCase = strtolower($eventID);

        if (preg_match('/^notif(y|ier)_/', $snakeCase) && method_exists($observer, $snakeCase)) {
            $methods[] = $snakeCase;
        }

        $methods[] = 'update' . $this->compatibilityCamelize(strtolower($eventID), true);
        $methods[] = 'update';

        return $methods;
    }

    private function compatibilityCamelize(string $rawName, bool $camelFirst = false): string
    {
        if ($rawName === '') {
            return $rawName;
        }

        if ($camelFirst) {
            $rawName[0] = strtoupper($rawName[0]);
        }

        return preg_replace_callback('/[_-]([0-9a-z])/', static function (array $matches) {
            return strtoupper($matches[1]);
        }, $rawName);
    }

    private function eventIdHasAlias(string $eventId): bool
    {
        return array_key_exists($eventId, $this->observerAliases);
    }

    /**
     * @return string|false
     */
    private function substituteAlias(string $eventId)
    {
        if ($this->eventIdHasAlias($eventId)) {
            return $this->observerAliases[$eventId];
        }

        $legacyEvent = array_search($eventId, $this->observerAliases, true);

        return $legacyEvent === false ? false : $legacyEvent;
    }
}

if (class_exists('base')) {
    class notifier extends base
    {
        use PayPalAdvancedCheckoutLegacyNotifierTrait;
    }
} else {
    class notifier
    {
        use PayPalAdvancedCheckoutLegacyNotifierTrait;
    }
}

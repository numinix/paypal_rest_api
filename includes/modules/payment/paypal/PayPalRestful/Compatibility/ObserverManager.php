<?php

namespace Zencart\Traits;

if (!trait_exists('Zencart\\Traits\\ObserverManager')) {
    trait ObserverManager
    {
        /**
         * Determine which notifier implementation is available.
         *
         * Zen Cart 2.0+ ships with the namespaced EventDto class, while
         * earlier versions (e.g. 1.5.7) use the legacy global notifier.
         * This helper caches the detection to avoid repeated checks.
         */
        private function observerManagerUsesEventDto(): bool
        {
            static $supportsEventDto = null;

            if ($supportsEventDto === null) {
                $supportsEventDto = class_exists('\\Zencart\\Events\\EventDto');
            }

            return $supportsEventDto;
        }

        /**
         * method used to an attach an observer to the notifier object
         *
         * NB. We have to get a little sneaky here to stop session based classes adding events ad infinitum
         * To do this we first concatenate the class name with the event id, as a class is only ever going to attach to an
         * event id once, this provides a unique key. To ensure there are no naming problems with the array key, we md5 the
         * unique name to provide a unique hashed key.
         *
         * @param object Reference to the observer class
         * @param array An array of eventId's to observe
         */
        public function attach(&$observer, $eventIDArray)
        {
            if ($this->observerManagerUsesEventDto()) {
                foreach ($eventIDArray as $eventID) {
                    $nameHash = md5(get_class($observer) . $eventID);
                    \Zencart\Events\EventDto::getInstance()->setObserver($nameHash, array('obs' => &$observer, 'eventID' => $eventID));
                }

                return;
            }

            global $zco_notifier;
            if (!is_object($zco_notifier)) {
                return;
            }

            foreach ($eventIDArray as $eventID) {
                $zco_notifier->attach($observer, array($eventID));
            }
        }

        /**
         * method used to detach an observer from the notifier object
         * @param object
         * @param array
         */
        public function detach($observer, $eventIDArray)
        {
            if ($this->observerManagerUsesEventDto()) {
                foreach ($eventIDArray as $eventID) {
                    $nameHash = md5(get_class($observer) . $eventID);
                    \Zencart\Events\EventDto::getInstance()->removeObserver($nameHash);
                }

                return;
            }

            global $zco_notifier;
            if (!is_object($zco_notifier)) {
                return;
            }

            foreach ($eventIDArray as $eventID) {
                $zco_notifier->detach($observer, array($eventID));
            }
        }
    }
}

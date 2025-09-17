<?php

namespace Zencart\Traits;

use Zencart\Events\EventDto;

if (!trait_exists('Zencart\\Traits\\ObserverManager')) {
    trait ObserverManager
    {
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
        function attach(&$observer, $eventIDArray)
        {
            foreach ($eventIDArray as $eventID) {
                $nameHash = md5(get_class($observer) . $eventID);
                EventDto::getInstance()->setObserver($nameHash, array('obs' => &$observer, 'eventID' => $eventID));
            }
        }

        /**
         * method used to detach an observer from the notifier object
         * @param object
         * @param array
         */
        function detach($observer, $eventIDArray)
        {
            foreach ($eventIDArray as $eventID) {
                $nameHash = md5(get_class($observer) . $eventID);
                EventDto::getInstance()->removeObserver($nameHash);
            }
        }
    }
}

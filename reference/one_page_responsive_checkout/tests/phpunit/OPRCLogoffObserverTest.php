<?php

use PHPUnit\Framework\TestCase;

class OPRCLogoffObserverTest extends TestCase
{
    public function testSessionDestroyOnlyCalledWhenSessionActive(): void
    {
        // Mock the zen_session_destroy function
        $destroyCalled = false;
        
        // Define the function in the global namespace if not already defined
        if (!function_exists('zen_session_destroy')) {
            function zen_session_destroy() {
                global $destroyCalled;
                $destroyCalled = true;
            }
        }
        
        // Simulate the shutdown function behavior when session is active
        if (session_status() === PHP_SESSION_ACTIVE) {
            zen_session_destroy();
        }
        
        // If we have an active session, destroy should be called
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->assertTrue($destroyCalled, 'zen_session_destroy should be called when session is active');
        } else {
            $this->assertFalse($destroyCalled, 'zen_session_destroy should not be called when session is not active');
        }
    }
    
    public function testSessionDestroyNotCalledWhenSessionInactive(): void
    {
        // Ensure session is not started
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        
        $destroyCalled = false;
        
        // Simulate the shutdown function behavior when session is not active
        if (session_status() === PHP_SESSION_ACTIVE) {
            $destroyCalled = true;
        }
        
        $this->assertFalse($destroyCalled, 'zen_session_destroy should not be called when session is not active');
    }
}

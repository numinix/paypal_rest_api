<?php
/**
 * PayPal Signup Language Pack
 *
 * Defines all user-facing strings used by the PayPal Commerce Platform onboarding
 * experience. Strings are organized into structured arrays so that they can be
 * reused by both the storefront and the admin interface, and to simplify future
 * translation efforts.
 */

return [
    'meta' => [
        'title' => 'PayPal Setup for Zen Cart | Numinix',
        'description' => 'Launch PayPal in Zen Cart with Numinix—compliant configuration, fraud controls, dispute playbooks, and a faster go-live plan.'
    ],

    'buttons' => [
        'start' => 'Connect with PayPal',
        'continue' => 'Continue Setup',
        'retry' => 'Try Again',
        'cancel' => 'Cancel Setup',
        'close' => 'Close'
    ],

    'states' => [
        'start' => [
            'heading' => 'Sell with PayPal Commerce Platform',
            'body' => [
                'Launch PayPal\'s secure onboarding flow to connect your account.',
                'We will walk you through the process and keep your store in sync.'
            ],
            'cta_hint' => 'You will be redirected to PayPal in a secure popup window.'
        ],
        'waiting' => [
            'heading' => 'Complete the steps with PayPal',
            'body' => [
                'Finish the signup in the PayPal window to continue.',
                'We are securely waiting for PayPal to confirm your account.'
            ],
            'status' => [
                'polling' => 'Checking status…',
                'pending' => 'Still waiting on PayPal…',
                'processing' => 'Processing your account details…'
            ]
        ],
        'success' => [
            'heading' => 'PayPal account connected',
            'intro' => 'PayPal approved your onboarding request.',
            'next_steps' => [
                'Sign in to PayPal to download your API credentials.',
                'Add the credentials to your platform or share them with your developers.'
            ]
        ],
        'error' => [
            'heading' => 'We couldn\'t finish connecting your account',
            'intro' => 'Something went wrong while finalizing the connection.',
            'retry_hint' => 'Please try again or contact Numinix support if the issue continues.'
        ],
        'cancelled' => [
            'heading' => 'You left before finishing',
            'intro' => 'The PayPal signup flow was closed before completion.',
            'resume_hint' => 'You can resume the process at any time to finish connecting your account.'
        ]
    ],

    'instructions' => [
        'heading' => 'How onboarding works',
        'steps' => [
            'Start the secure PayPal onboarding flow.',
            'Follow the prompts in the PayPal window and submit required information.',
            'Watch for the success confirmation on this page after PayPal approves your details.',
            'Retrieve your API credentials from the PayPal dashboard once your account is ready.'
        ],
        'support' => [
            'title' => 'Need help?',
            'body' => [
                'Visit the Numinix support center or email support@numinix.com for assistance.',
                'Include your tracking ID so we can review the onboarding attempt quickly.'
            ]
        ]
    ],

    'notices' => [
        'popup_blocked' => 'Allow popups for this site to continue with PayPal onboarding.',
        'non_secure' => 'A secure (HTTPS) connection is required to connect to PayPal.',
        'missing_tracking' => 'We could not find a valid tracking ID. Please restart the signup process from your store.',
        'status_timeout' => 'We did not receive a response from PayPal in time. Please retry the onboarding process.',
    ],

    'accessibility' => [
        'live_region' => 'Status updates will appear here.',
        'focus_trap_exit' => 'Close this dialog to return to the main page'
    ]
];

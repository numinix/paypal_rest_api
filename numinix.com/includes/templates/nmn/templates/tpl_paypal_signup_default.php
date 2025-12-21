<?php
/**
 * PayPal setup landing page for the Numinix "nmn" storefront theme.
 */

if (!isset($nxpPayPalSession) || !is_array($nxpPayPalSession)) {
    $nxpPayPalSession = [];
}

$environment = isset($nxpPayPalSession['env']) ? (string)$nxpPayPalSession['env'] : 'sandbox';
$nonce = isset($nxpPayPalSession['nonce']) ? (string)$nxpPayPalSession['nonce'] : '';
$trackingId = isset($nxpPayPalSession['tracking_id']) ? (string)$nxpPayPalSession['tracking_id'] : '';
$step = isset($nxpPayPalSession['step']) ? (string)$nxpPayPalSession['step'] : 'start';
$authCode = isset($nxpPayPalSession['code']) ? (string)$nxpPayPalSession['code'] : '';
$merchantId = isset($nxpPayPalSession['merchant_id']) ? (string)$nxpPayPalSession['merchant_id'] : '';
$authCodeExchange = isset($nxpPayPalSession['auth_code']) ? (string)$nxpPayPalSession['auth_code'] : '';
$sharedId = isset($nxpPayPalSession['shared_id']) ? (string)$nxpPayPalSession['shared_id'] : '';

$pageName = defined('FILENAME_PAYPAL_SIGNUP') ? FILENAME_PAYPAL_SIGNUP : 'paypal_signup';
$actionUrl = zen_href_link($pageName, '', 'SSL');
$pageUrl = $actionUrl;
$logoPath = $template->get_template_dir('numinix.svg', DIR_WS_TEMPLATE, $current_page_base, 'images') . '/numinix.svg';

$sessionPayload = json_encode([
    'env' => $environment,
    'nonce' => $nonce,
    'tracking_id' => $trackingId,
    'step' => $step,
    'code' => $authCode,
    'merchant_id' => $merchantId,
    'authCode' => $authCodeExchange,
    'sharedId' => $sharedId,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$faqItems = [
    [
        'question' => 'Which ecommerce platforms do you support?',
        'answer' => 'Numinix supports PayPal onboarding for WooCommerce, Shopify, BigCommerce, Lightspeed, OpenCart, Magento, Zen Cart, and custom ecommerce platforms.'
    ],
    [
        'question' => 'Do I need a specific PayPal account type?',
        'answer' => 'We guide you to the right PayPal Business account for your region and platform, making sure it connects cleanly to your storefront and tech stack.'
    ],
    [
        'question' => 'How long does approval and go-live take?',
        'answer' => 'Most merchants see approvals within two business days. We prep credentials, sandbox/live parity, and launch assets so you can flip the switch immediately.'
    ],
    [
        'question' => 'Will this affect my existing payment processors?',
        'answer' => 'No. We map PayPal alongside Braintree, Stripe, Authorize.net, and other gateways so your routing, reporting, and subscriptions continue uninterrupted.'
    ],
    [
        'question' => 'Can you configure webhooks, IPN, and platform settings?',
        'answer' => 'Yes. We configure PayPal webhooks, IPN, smart buttons, and platform-specific modules so your developers can test safely while production keeps taking orders.'
    ],
    [
        'question' => 'How are multi-currency and settlement handled?',
        'answer' => 'We confirm currency mapping, settlement accounts, and any automatic conversions so payouts arrive without surprises across regions.'
    ],
    [
        'question' => 'What about chargebacks and dispute workflows?',
        'answer' => 'You get a documented dispute playbook plus automation for evidence packets so your team knows exactly what to do when PayPal pings you.'
    ],
    [
        'question' => 'Can you audit my current PayPal configuration?',
        'answer' => 'Absolutely. If you already have PayPal connected, we can run a fast audit to highlight risk areas, settlement delays, and missed optimization wins.'
    ],
    [
        'question' => 'How quickly do funds reach my bank?',
        'answer' => 'With the recommended settings, PayPal typically settles to your bank in 1–2 business days. We confirm funding schedules during setup.'
    ],
    [
        'question' => 'Will you help train our staff?',
        'answer' => 'Yes. Each launch includes a handoff session and recorded walkthrough so support and finance teams know how to manage PayPal day to day.'
    ],
];

$faqSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(function ($item) {
        return [
            '@type' => 'Question',
            'name' => $item['question'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $item['answer'],
            ],
        ];
    }, $faqItems),
];

$organizationSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => 'Numinix',
    'url' => zen_href_link(FILENAME_DEFAULT, '', 'SSL'),
    'sameAs' => [
        'https://www.facebook.com/numinix',
        'https://www.linkedin.com/company/numinix',
        'https://twitter.com/numinix',
    ],
];
?>
<div
    class="nxp-ps-page"
    data-env="<?php echo htmlspecialchars($environment, ENT_QUOTES, 'UTF-8'); ?>"
    data-action-url="<?php echo htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8'); ?>"
>
    <a href="#nxp-ps-main" class="nxp-ps-skip">Skip to content</a>

    <main id="nxp-ps-main" class="nxp-ps-main" role="main">
        <section class="nxp-ps-hero" aria-labelledby="nxp-ps-hero-title">
            <div class="nxp-ps-hero__content">
                <div class="nxp-ps-hero__env">
                    <div class="nxp-ps-env-toggle" data-env-toggle role="group" aria-label="Select PayPal environment">
                        <div class="nxp-ps-env-toggle__controls">
                            <button
                                type="button"
                                class="nxp-ps-env-toggle__option<?php echo $environment === 'sandbox' ? ' is-active' : ''; ?>"
                                data-env-option="sandbox"
                                aria-pressed="<?php echo $environment === 'sandbox' ? 'true' : 'false'; ?>"
                            >Sandbox</button>
                            <button
                                type="button"
                                class="nxp-ps-env-toggle__option<?php echo $environment === 'live' ? ' is-active' : ''; ?>"
                                data-env-option="live"
                                aria-pressed="<?php echo $environment === 'live' ? 'true' : 'false'; ?>"
                            >Live</button>
                        </div>
                    </div>
                </div>
                <p class="nxp-ps-eyebrow">Ecommerce PayPal onboarding experts</p>
                <h1 id="nxp-ps-hero-title" data-variant="headline">
                    Launch PayPal the right way for your store.
                </h1>
                <p class="nxp-ps-hero__subtitle" data-variant="subhead">
                    We orchestrate PayPal onboarding for WooCommerce, Shopify, BigCommerce, Lightspeed, OpenCart, Magento, Zen Cart, and custom builds—so you launch fast without sacrificing compliance or conversion.
                </p>
                <div class="nxp-ps-hero__actions">
                    <a
                        href="<?php echo htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8'); ?>#nxp-ps-onboarding"
                        class="nxp-ps-cta"
                        data-analytics-id="hero-primary"
                        data-scroll-target="#nxp-ps-onboarding"
                        data-variant="primary-cta"
                        data-onboarding-start
                    >Start PayPal Signup</a>
                    <a
                        href="https://www.numinix.com/support/"
                        class="nxp-ps-hero__link"
                        data-analytics-id="hero-secondary"
                        rel="noopener"
                    >Talk to an Expert</a>
                </div>
                <div
                    class="nxp-ps-proof"
                    data-component="carousel"
                    data-carousel-viewport=".nxp-ps-proof__viewport"
                    data-carousel-track=".nxp-ps-proof__track"
                    data-carousel-control=".nxp-ps-proof__control"
                >
                    <button
                        type="button"
                        class="nxp-ps-proof__control nxp-ps-cases__control"
                        data-direction="prev"
                        aria-label="Previous proof point"
                        disabled
                    >
                        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" focusable="false">
                            <path d="M11.5 3.5 6.75 9l4.75 5.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                    <div class="nxp-ps-proof__viewport">
                        <div class="nxp-ps-proof__track" role="list">
                            <div class="nxp-ps-proof__item" role="listitem">
                                <span class="nxp-ps-proof__icon" aria-hidden="true"></span>
                                <div class="nxp-ps-proof__body">
                                    <span class="nxp-ps-proof__label">Launch velocity</span>
                                    <span class="nxp-ps-proof__text">Go live in as little as 48 hours with dedicated PayPal specialists guiding every step.</span>
                                </div>
                            </div>
                            <div class="nxp-ps-proof__item" role="listitem">
                                <span class="nxp-ps-proof__icon" aria-hidden="true"></span>
                                <div class="nxp-ps-proof__body">
                                    <span class="nxp-ps-proof__label">Proven track record</span>
                                    <span class="nxp-ps-proof__text">Trusted by 400+ ecommerce brands building high-performing checkout experiences.</span>
                                </div>
                            </div>
                            <div class="nxp-ps-proof__item" role="listitem">
                                <span class="nxp-ps-proof__icon" aria-hidden="true"></span>
                                <div class="nxp-ps-proof__body">
                                    <span class="nxp-ps-proof__label">Operational playbooks</span>
                                    <span class="nxp-ps-proof__text">Dispute response kits, KPI dashboards, and compliance documentation included.</span>
                                </div>
                            </div>
                            <div class="nxp-ps-proof__item" role="listitem">
                                <span class="nxp-ps-proof__icon" aria-hidden="true"></span>
                                <div class="nxp-ps-proof__body">
                                    <span class="nxp-ps-proof__label">Priority advocacy</span>
                                    <span class="nxp-ps-proof__text">PayPal Partner Program member with direct escalation paths for your team.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="nxp-ps-proof__control nxp-ps-cases__control"
                        data-direction="next"
                        aria-label="Next proof point"
                    >
                        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" focusable="false">
                            <path d="m6.5 3.5 4.75 5.5L6.5 14.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>
                <div class="nxp-ps-payment-logos" aria-label="Payment methods supported">
                    <svg width="108" height="32" viewBox="0 0 108 32" role="img" aria-label="PayPal" class="nxp-ps-payment-logo">
                        <defs>
                            <linearGradient id="nxp-paypal-gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#003087" />
                                <stop offset="100%" stop-color="#009cde" />
                            </linearGradient>
                        </defs>
                        <rect width="108" height="32" rx="6" fill="url(#nxp-paypal-gradient)" />
                        <text x="50%" y="50%" text-anchor="middle" fill="#ffffff" font-family="Manrope, Arial, sans-serif" font-weight="700" font-size="16" dominant-baseline="middle">PayPal</text>
                    </svg>
                    <svg width="72" height="32" viewBox="0 0 72 32" role="img" aria-label="Visa"><rect width="72" height="32" rx="6" fill="#1434CB"/><text x="50%" y="50%" text-anchor="middle" fill="#fff" font-family="Manrope, Arial, sans-serif" font-weight="700" font-size="14" dominant-baseline="middle">VISA</text></svg>
                    <svg width="72" height="32" viewBox="0 0 72 32" role="img" aria-label="Mastercard"><rect width="72" height="32" rx="6" fill="#15141A"/><g transform="translate(20,8)"><circle cx="8" cy="8" r="8" fill="#EB001B"/><circle cx="20" cy="8" r="8" fill="#F79E1B"/><path d="M12 0a8 8 0 0 1 0 16a8 8 0 0 1 0-16z" fill="#FF5F00"/></g></svg>
                </div>
                <p class="nxp-ps-hero__supporting">From sandbox setup to risk reviews, we pair compliance rigor with conversion-minded UX so PayPal elevates your entire checkout.</p>
            </div>
            <div class="nxp-ps-hero__visual" aria-hidden="true">
                <div class="nxp-ps-hero__card">
                    <span class="nxp-ps-hero__badge">Live in days, not weeks</span>
                    <p>“Numinix launched PayPal for us in 48 hours and handed over a dispute playbook our team could run with.”</p>
                    <span class="nxp-ps-hero__author">— Director of Ecommerce, Shopify Plus brand</span>
                </div>
            </div>
        </section>

        <section class="nxp-ps-journey" aria-labelledby="nxp-ps-journey-title">
            <div class="nxp-ps-section-heading">
                <h2 id="nxp-ps-journey-title">Your Numinix Journey</h2>
                <p>Consulting to optimization—trusted by ecommerce teams across WooCommerce, Shopify, BigCommerce, Lightspeed, OpenCart, Magento, and Zen Cart.</p>
            </div>
            <div class="nxp-ps-journey__strip" role="list">
                <article class="nxp-ps-journey__item" role="listitem">
                    <svg width="36" height="36" viewBox="0 0 36 36" aria-hidden="true"><g fill="none" stroke="#0a2a63" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="18" r="12"/><path d="M12 18h12M18 12v12"/></g></svg>
                    <h3>Consulting</h3>
                    <p>Strategy session on compliance, risk, and KPIs.</p>
                </article>
                <article class="nxp-ps-journey__item" role="listitem">
                    <svg width="36" height="36" viewBox="0 0 36 36" aria-hidden="true"><g fill="none" stroke="#0a2a63" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="10" width="20" height="16" rx="3"/><path d="M12 14h12M12 18h8"/></g></svg>
                    <h3>Design</h3>
                    <p>Checkout UX tailored to PayPal behaviors.</p>
                </article>
                <article class="nxp-ps-journey__item" role="listitem">
                    <svg width="36" height="36" viewBox="0 0 36 36" aria-hidden="true"><g fill="none" stroke="#0a2a63" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 26h16l-2-12H12l-2 12z"/><path d="M14 14V10a4 4 0 0 1 8 0v4"/></g></svg>
                    <h3>Development</h3>
                    <p>Platform engineering, credentialing, and QA across WooCommerce, Shopify, BigCommerce, Lightspeed, OpenCart, Magento, and Zen Cart.</p>
                </article>
                <article class="nxp-ps-journey__item" role="listitem">
                    <svg width="36" height="36" viewBox="0 0 36 36" aria-hidden="true"><g fill="none" stroke="#0a2a63" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 24l4 4l8-12"/><circle cx="18" cy="18" r="12"/></g></svg>
                    <h3>Optimization</h3>
                    <p>Performance tuning and dispute insights.</p>
                </article>
            </div>
        </section>

        <section id="nxp-ps-benefits" class="nxp-ps-benefits" aria-labelledby="nxp-ps-benefits-title">
            <div class="nxp-ps-section-heading">
                <h2 id="nxp-ps-benefits-title">What you get with Numinix</h2>
                <p>Outcomes over features—built from hundreds of ecommerce rollouts across the platforms you rely on.</p>
            </div>
            <div class="nxp-ps-card-grid">
                <article class="nxp-ps-card">
                    <h3>Proper account setup</h3>
                    <p>Business profiles, permissions, and settlement preferences built to PayPal’s latest checklist so compliance is locked in.</p>
                </article>
                <article class="nxp-ps-card">
                    <h3>Fraud &amp; dispute tools</h3>
                    <p>Fraud filters tuned for your platform plus a dispute response playbook to keep win rates high.</p>
                </article>
                <article class="nxp-ps-card">
                    <h3>Checkout testing</h3>
                    <p>Device lab testing across PayPal buttons, funding sources, and language packs on WooCommerce, Shopify, BigCommerce, Lightspeed, OpenCart, Magento, and Zen Cart.</p>
                </article>
                <article class="nxp-ps-card">
                    <h3>PSD2/3DS readiness</h3>
                    <p>Safeguards for EU shoppers with minimal friction for the rest of your traffic.</p>
                </article>
                <article class="nxp-ps-card">
                    <h3>Analytics &amp; telemetry</h3>
                    <p>Event tracking wired into GA4 and heatmaps so you can see exactly where visitors convert or drop.</p>
                </article>
                <article class="nxp-ps-card">
                    <h3>Launch support</h3>
                    <p>Go-live checklist, rollback plan, and direct line to the PayPal team we partner with.</p>
                </article>
            </div>
        </section>

        <section id="nxp-ps-comparison" class="nxp-ps-comparison" aria-labelledby="nxp-ps-comparison-title">
            <div class="nxp-ps-section-heading">
                <h2 id="nxp-ps-comparison-title">DIY vs. Numinix</h2>
                <p>See the impact before you invest the hours.</p>
            </div>
            <div class="nxp-ps-toggle" role="group" aria-label="Compare setup approaches">
                <button type="button" class="nxp-ps-toggle__button is-active" data-view="numinix" aria-pressed="true">Numinix guided</button>
                <button type="button" class="nxp-ps-toggle__button" data-view="diy" aria-pressed="false">DIY setup</button>
            </div>
            <div class="nxp-ps-comparison__panels">
                <article class="nxp-ps-comparison__panel is-active" data-view="numinix" aria-label="Numinix guided outcomes">
                    <ul>
                        <li><strong>12</strong> hours average to launch with our playbook.</li>
                        <li><strong>&lt;1%</strong> misconfiguration rate verified by QA checklist.</li>
                        <li><strong>48 hrs</strong> to first live transaction on average.</li>
                    </ul>
                </article>
                <article class="nxp-ps-comparison__panel" data-view="diy" aria-label="DIY risks">
                    <ul>
                        <li><strong>40+</strong> hours in PayPal support queues and forums.</li>
                        <li><strong>22%</strong> risk of dispute escalations due to missed evidence rules.</li>
                        <li><strong>7+ days</strong> delay when credentials are provisioned incorrectly.</li>
                    </ul>
                </article>
            </div>
        </section>

        <section id="nxp-ps-process" class="nxp-ps-process" aria-labelledby="nxp-ps-process-title">
            <div class="nxp-ps-section-heading">
                <h2 id="nxp-ps-process-title">Your PayPal setup in 3 steps</h2>
                <p>Discovery to go-live—transparent and repeatable.</p>
            </div>
            <div class="nxp-ps-process__grid" role="list">
                <article class="nxp-ps-process__item" role="listitem">
                    <span class="nxp-ps-process__step">1</span>
                    <h3>Discovery</h3>
                    <p>We audit your ecommerce platform, payment stack, and risk policies while capturing requirements in one call.</p>
                </article>
                <article class="nxp-ps-process__item" role="listitem">
                    <span class="nxp-ps-process__step">2</span>
                    <h3>Configure</h3>
                    <p>Credential provisioning, platform module/app configuration, and sandbox/live parity testing alongside your existing integrations.</p>
                </article>
                <article class="nxp-ps-process__item" role="listitem">
                    <span class="nxp-ps-process__step">3</span>
                    <h3>Verify &amp; Go-live</h3>
                    <p>Final smoke tests, staff training, and dispute/settlement handoff so you launch with confidence.</p>
                </article>
            </div>
        </section>

        <section class="nxp-ps-case-studies" aria-labelledby="nxp-ps-case-title">
            <div class="nxp-ps-section-heading">
                <h2 id="nxp-ps-case-title">Read our case studies</h2>
                <p>See how ecommerce merchants across leading platforms scale with PayPal and Numinix.</p>
            </div>
            <div class="nxp-ps-cases" data-component="cases-carousel">
                <button type="button" class="nxp-ps-cases__control" data-direction="prev" aria-label="Previous case study" disabled>
                    <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" focusable="false">
                        <path d="M11.5 3.5 6.75 9l4.75 5.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
                <div class="nxp-ps-cases__viewport">
                    <div class="nxp-ps-cases__track" role="list">
                        <article class="nxp-ps-case" role="listitem">
                            <a href="/case-studies/outdoor-gear" aria-label="Outdoor gear retailer case study">
                                <div class="nxp-ps-case__logo nxp-ps-case__logo--outdoor" aria-hidden="true"></div>
                                <h3>Outdoor Gear</h3>
                                <p>+28% checkout conversion after PayPal optimization.</p>
                            </a>
                        </article>
                        <article class="nxp-ps-case" role="listitem">
                            <a href="/case-studies/beauty" aria-label="Beauty retailer case study">
                                <div class="nxp-ps-case__logo nxp-ps-case__logo--beauty" aria-hidden="true"></div>
                                <h3>Beauty &amp; Wellness</h3>
                                <p>60% faster dispute resolution with automation workflows.</p>
                            </a>
                        </article>
                        <article class="nxp-ps-case" role="listitem">
                            <a href="/case-studies/b2b" aria-label="B2B manufacturer case study">
                                <div class="nxp-ps-case__logo nxp-ps-case__logo--b2b" aria-hidden="true"></div>
                                <h3>B2B Manufacturing</h3>
                                <p>USD 1.2M unlocked in PayPal revenue in quarter one.</p>
                            </a>
                        </article>
                    </div>
                </div>
                <button type="button" class="nxp-ps-cases__control" data-direction="next" aria-label="Next case study">
                    <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" focusable="false">
                        <path d="m6.5 3.5 4.75 5.5L6.5 14.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </div>
        </section>

        <section id="nxp-ps-faq" class="nxp-ps-faq" aria-labelledby="nxp-ps-faq-title">
            <div class="nxp-ps-section-heading">
                <h2 id="nxp-ps-faq-title">PayPal setup FAQs</h2>
                <p>Answers to the questions ecommerce teams ask us most across WooCommerce, Shopify, BigCommerce, Lightspeed, OpenCart, Magento, and Zen Cart.</p>
            </div>
            <div class="nxp-ps-accordion" data-component="accordion">
                <?php foreach ($faqItems as $index => $item): $questionId = 'nxp-ps-faq-q' . $index; $panelId = 'nxp-ps-faq-a' . $index; ?>
                    <div class="nxp-ps-accordion__item">
                        <h3>
                            <button
                                class="nxp-ps-accordion__trigger"
                                type="button"
                                aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                                aria-controls="<?php echo $panelId; ?>"
                                id="<?php echo $questionId; ?>"
                            >
                                <?php echo htmlspecialchars($item['question'], ENT_QUOTES, 'UTF-8'); ?>
                                <span class="nxp-ps-accordion__icon" aria-hidden="true"></span>
                            </button>
                        </h3>
                        <div
                            id="<?php echo $panelId; ?>"
                            class="nxp-ps-accordion__panel"
                            role="region"
                            aria-labelledby="<?php echo $questionId; ?>"
                            <?php echo $index === 0 ? '' : 'hidden'; ?>
                        >
                            <p><?php echo htmlspecialchars($item['answer'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="nxp-ps-form" class="nxp-ps-form" aria-labelledby="nxp-ps-form-title">
            <div class="nxp-ps-form__inner">
                <div class="nxp-ps-form__copy">
                    <h2 id="nxp-ps-form-title">Ready to start your PayPal setup?</h2>
                    <p>Launch the integrated PayPal signup flow to connect your account without leaving your store.</p>
                    <ul>
                        <li>Open PayPal's secure onboarding experience in a guided popup.</li>
                        <li>Receive confirmation from PayPal as soon as your business profile is approved.</li>
                        <li>Return here anytime to resume onboarding or request additional help.</li>
                    </ul>
                </div>
                <div class="nxp-ps-onboarding" id="nxp-ps-onboarding" data-component="onboarding">
                    <div class="nxp-ps-onboarding__status" data-onboarding-status role="status" aria-live="polite"></div>
                    <div class="nxp-ps-onboarding__actions">
                        <button type="button" class="nxp-ps-cta" data-onboarding-start data-analytics-id="onboarding-start">Start PayPal Signup</button>
                        <p class="nxp-ps-onboarding__hint">We'll open a secure PayPal window to finish connecting your account.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<script id="nxp-paypal-session" type="application/json"><?php echo $sessionPayload; ?></script>
<script type="application/ld+json"><?php echo json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<script type="application/ld+json"><?php echo json_encode($organizationSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>

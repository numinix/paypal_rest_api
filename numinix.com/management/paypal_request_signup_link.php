<?php
require 'includes/application_top.php';

/**
 * Validates the admin security token using available helpers.
 *
 * @param string $token
 * @return bool
 */
function nxp_validate_admin_token(string $token): bool
{
    if ($token === '') {
        return false;
    }

    if (function_exists('zen_validate_token')) {
        try {
            if (zen_validate_token($token, false)) {
                return true;
            }
        } catch (Throwable $ignored) {
            // Fall through to manual comparison.
        }
    }

    $expected = $_SESSION['securityToken'] ?? '';
    if ($expected === '') {
        return false;
    }

    if (function_exists('hash_equals')) {
        return hash_equals((string) $expected, $token);
    }

    return (string) $expected === $token;
}

/**
 * Normalizes a referral link value so only valid URLs are returned.
 *
 * @param mixed $value
 * @return string
 */
function nxp_normalize_referral_link($value): string
{
    if (!is_string($value)) {
        return '';
    }

    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (filter_var($value, FILTER_VALIDATE_URL) === false) {
        return '';
    }

    return $value;
}

$messages = [];
$result = [];
$debugSnapshot = null;
$environment = defined('NUMINIX_PPCP_ENVIRONMENT') ? (string) NUMINIX_PPCP_ENVIRONMENT : 'sandbox';
$environment = strtolower($environment) === 'live' ? 'live' : 'sandbox';

$referralLinkKey = 'NUMINIX_PPCP_PARTNER_REFERRAL_LINK';
$storedReferralLink = '';

if (defined($referralLinkKey)) {
    $storedReferralLink = nxp_normalize_referral_link(constant($referralLinkKey));
}

if ($storedReferralLink === '' && function_exists('zen_get_configuration_key_value')) {
    $configured = zen_get_configuration_key_value($referralLinkKey);
    if ($configured !== false && $configured !== null) {
        $storedReferralLink = nxp_normalize_referral_link($configured);
    }
}

$servicePath = DIR_WS_INCLUDES . 'classes/Numinix/PaypalIsu/SignupLinkService.php';
if (file_exists($servicePath)) {
    require_once $servicePath;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['securityToken'] ?? '');

    if (!nxp_validate_admin_token($token)) {
        $messages[] = ['type' => 'error', 'text' => 'Security token validation failed. Please refresh the page and try again.'];
    } else {
        if (!class_exists('NuminixPaypalIsuSignupLinkService')) {
            $messages[] = ['type' => 'error', 'text' => 'Signup link service is unavailable.'];
        } else {
            try {
                $service = new NuminixPaypalIsuSignupLinkService();
                $result = $service->request([
                    'environment' => $environment,
                ]);
                $environment = strtolower((string) ($result['environment'] ?? $environment)) === 'live' ? 'live' : 'sandbox';
                $storedReferralLink = nxp_normalize_referral_link($result['action_url'] ?? '');
                $messages[] = ['type' => 'success', 'text' => 'Signup link generated successfully.'];
            } catch (Throwable $exception) {
                if (function_exists('zen_record_admin_activity')) {
                    zen_record_admin_activity('Numinix PayPal signup link request failed: ' . $exception->getMessage(), 'error');
                }

                $messages[] = ['type' => 'error', 'text' => 'Unable to generate signup link: ' . $exception->getMessage()];

                if (isset($service) && method_exists($service, 'getLastDebugSnapshot')) {
                    $snapshot = $service->getLastDebugSnapshot();
                    if (is_array($snapshot) && !empty($snapshot)) {
                        $debugSnapshot = $snapshot;
                    }
                }
            }
        }
    }
}

?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
    <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
    <link rel="stylesheet" href="includes/css/nxp-paypal-signup.css" media="all" />
</head>
<body class="nxp-signup-page">
<?php require DIR_WS_INCLUDES . 'header.php'; ?>

<section class="nxp-hero">
    <div class="nxp-hero-inner">
        <div class="nxp-hero-brand">
            <img
                src="https://www.paypalobjects.com/images/shared/paypal-logo-129x32.svg"
                alt="PayPal"
                class="nxp-hero-logo"
                width="129"
                height="32"
                loading="lazy"
            />
            <span class="nxp-hero-badge">Commerce Platform</span>
        </div>
        <span class="nxp-hero-eyebrow">PayPal ISU Toolkit</span>
        <h1>Partner Signup Link Generator</h1>
        <p class="nxp-hero-subtitle">
            Provide merchants with a polished onboarding experience by generating a reusable partner referral link
            aligned with PayPal Complete Payments standards.
        </p>
        <div class="nxp-hero-actions">
            <a class="nxp-primary-btn" href="#generate-link">Generate link</a>
            <div class="nxp-hero-support">
                <span class="nxp-hero-support__label">Need a refresher?</span>
                <a
                    class="nxp-hero-support__link"
                    href="https://www.numinix.com/paypal-integrated-signup"
                    target="_blank"
                    rel="noopener noreferrer"
                >View onboarding guide</a>
            </div>
        </div>
    </div>
</section>

<div class="nxp-content-wrapper">
    <div class="nxp-badge">Guided Setup</div>

    <div class="nxp-layout">
        <main class="nxp-main" id="main-content">
            <article class="nxp-card">
                <span class="nxp-card__eyebrow">Overview</span>
                <h2 class="nxp-card__title">How to use this generator</h2>
                <p>
                    PayPal allows reusing the same onboarding link for multiple merchants. Use the button below to fetch the
                    partner referral link associated with your configured API credentials, then share it wherever merchants begin
                    their onboarding journey.
                </p>
                <ul class="nxp-checklist">
                    <li>Confirm the partner account you wish to onboard merchants under.</li>
                    <li>Click <strong>Generate Signup Link</strong> to retrieve the reusable referral URL.</li>
                    <li>Share the URL in documentation, marketing materials, or directly with merchants.</li>
                </ul>
            </article>

            <?php foreach ($messages as $message): ?>
                <?php
                $alertClass = $message['type'] === 'success' ? 'alert-success' : 'alert-danger';
                ?>
                <div class="alert nxp-alert <?php echo $alertClass; ?>" role="alert">
                    <?php echo zen_output_string($message['text']); ?>
                </div>
            <?php endforeach; ?>

            <?php if (!empty($debugSnapshot)): ?>
                <?php
                $debugJson = json_encode($debugSnapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                if ($debugJson === false) {
                    $debugJson = var_export($debugSnapshot, true);
                }
                ?>
                <section class="nxp-card" aria-labelledby="debug-details-heading">
                    <span class="nxp-card__eyebrow">Diagnostics</span>
                    <h2 class="nxp-card__title" id="debug-details-heading">PayPal API request details</h2>
                    <p class="nxp-muted">
                        Share the sanitized payload below with PayPal partner support to help them troubleshoot the
                        onboarding error. Sensitive values such as client secrets are automatically removed.
                    </p>
                    <div class="nxp-debug-block">
                        <pre class="nxp-debug-block__output" aria-label="PayPal API request log"><?php echo htmlspecialchars($debugJson, ENT_QUOTES, 'UTF-8'); ?></pre>
                        <div class="nxp-debug-block__actions">
                            <button
                                type="button"
                                class="btn nxp-copy-button"
                                data-copy-text="<?php echo htmlspecialchars($debugJson, ENT_QUOTES, 'UTF-8'); ?>"
                                data-copy-feedback="Copied diagnostics!"
                                data-copy-error="Copy failed"
                                data-copy-label="Copy diagnostics"
                            >Copy diagnostics</button>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <section class="nxp-card" id="generate-link" aria-labelledby="generate-link-heading">
                <span class="nxp-card__eyebrow">Action</span>
                <h2 class="nxp-card__title" id="generate-link-heading">Generate a signup link</h2>

                <?php if ($storedReferralLink !== ''): ?>
                    <div class="nxp-current-link" aria-live="polite">
                        <div class="nxp-current-link__body">
                            <span class="nxp-current-link__eyebrow">Current referral link</span>
                            <a class="nxp-current-link__url" href="<?php echo zen_output_string($storedReferralLink); ?>" target="_blank" rel="noopener noreferrer"><?php echo zen_output_string($storedReferralLink); ?></a>
                        </div>
                        <button type="button" class="btn nxp-copy-button" data-copy-text="<?php echo htmlspecialchars($storedReferralLink, ENT_QUOTES, 'UTF-8'); ?>" data-copy-feedback="Copied!" data-copy-error="Copy failed" data-copy-label="Copy">Copy</button>
                    </div>
                <?php endif; ?>

                <p class="nxp-muted">
                    The generator uses the <strong><?php echo ($environment === 'live') ? 'Live' : 'Sandbox'; ?></strong> partner credentials
                    configured for this store. Click the button below to confirm the credentials and retrieve the reusable onboarding URL.
                </p>

                <form class="nxp-form" method="post" action="<?php echo zen_href_link(FILENAME_PAYPAL_REQUEST_SIGNUP_LINK); ?>">
                    <input type="hidden" name="securityToken" value="<?php echo zen_output_string($_SESSION['securityToken'] ?? ''); ?>" />

                    <button type="submit" class="btn nxp-primary-btn">Generate Signup Link</button>
                </form>

                <ul class="nxp-meta-list">
                    <li><strong>Live mode</strong> triggers the PayPal partner experience for production merchants.</li>
                    <li><strong>Sandbox mode</strong> is ideal for testing or documentation screenshots.</li>
                </ul>
            </section>

            <?php if (!empty($result)): ?>
                <section class="nxp-card" aria-labelledby="link-details-heading">
                    <span class="nxp-card__eyebrow">Details</span>
                    <h2 class="nxp-card__title" id="link-details-heading">Signup link details</h2>
                    <dl class="nxp-definition-grid">
                        <div class="nxp-definition-grid__item">
                            <dt>Environment</dt>
                            <dd><?php echo ($environment === 'live') ? 'Live' : 'Sandbox'; ?></dd>
                        </div>

                        <div class="nxp-definition-grid__item">
                            <dt>Tracking ID</dt>
                            <dd><?php echo zen_output_string((string) ($result['tracking_id'] ?? '')); ?></dd>
                        </div>

                        <div class="nxp-definition-grid__item">
                            <dt>Partner Referral ID</dt>
                            <dd><?php echo zen_output_string((string) ($result['partner_referral_id'] ?? '')); ?></dd>
                        </div>

                        <div class="nxp-definition-grid__item">
                            <dt>Action URL</dt>
                            <dd class="nxp-link">
                                <?php $actionUrl = (string) ($result['action_url'] ?? ''); ?>
                                <?php if ($actionUrl !== ''): ?>
                                    <div class="nxp-link-display">
                                        <a class="nxp-link-display__url" href="<?php echo zen_output_string($actionUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo zen_output_string($actionUrl); ?></a>
                                        <button type="button" class="btn nxp-copy-button" data-copy-text="<?php echo htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8'); ?>" data-copy-feedback="Copied!" data-copy-error="Copy failed" data-copy-label="Copy">Copy</button>
                                    </div>
                                <?php else: ?>
                                    <em>Not provided by PayPal.</em>
                                <?php endif; ?>
                            </dd>
                        </div>
                    </dl>
                </section>
            <?php endif; ?>
        </main>

        <aside class="nxp-aside" aria-labelledby="nxp-aside-heading">
            <h2 class="sr-only" id="nxp-aside-heading">Helpful resources</h2>
            <div class="nxp-aside-card">
                <span class="nxp-aside-card__eyebrow">Why it matters</span>
                <h3 class="nxp-aside-card__title">Deliver a premium onboarding journey</h3>
                <p>
                    A single referral link keeps your partner program consistent and ensures merchants complete the most recent PayPal onboarding flow.
                </p>
                <ul class="nxp-aside-list">
                    <li>Aligns with PayPal Complete Payments branding.</li>
                    <li>Reduces manual steps for your support team.</li>
                    <li>Improves conversion when shared in documentation.</li>
                </ul>
            </div>

            <div class="nxp-aside-card">
                <span class="nxp-aside-card__eyebrow">Need help?</span>
                <h3 class="nxp-aside-card__title">We've got your back</h3>
                <p>
                    If merchants encounter issues while onboarding, review your API credentials or reach out to Numinix support for additional guidance.
                </p>
                <a class="nxp-aside-link" href="mailto:support@numinix.com">Contact Numinix Support</a>
            </div>
        </aside>
    </div>
</div>

<script>
(function () {
    function copyToClipboard(text) {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            return navigator.clipboard.writeText(text);
        }

        return new Promise(function (resolve, reject) {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                var successful = document.execCommand('copy');
                document.body.removeChild(textarea);
                if (successful) {
                    resolve();
                } else {
                    reject();
                }
            } catch (err) {
                document.body.removeChild(textarea);
                reject(err);
            }
        });
    }

    function restoreLabel(button, originalText) {
        button.textContent = originalText;
        button.classList.remove('nxp-copy-button--copied');
        button.classList.remove('nxp-copy-button--error');
    }

    function handleCopy(event) {
        var button = event.currentTarget;
        var text = button.getAttribute('data-copy-text') || '';
        if (text === '') {
            return;
        }

        var originalText = button.getAttribute('data-copy-label') || button.textContent;
        var successText = button.getAttribute('data-copy-feedback') || 'Copied!';
        var errorText = button.getAttribute('data-copy-error') || 'Copy failed';

        copyToClipboard(text).then(function () {
            button.textContent = successText;
            button.classList.add('nxp-copy-button--copied');
            setTimeout(function () {
                restoreLabel(button, originalText);
            }, 2000);
        }).catch(function () {
            button.textContent = errorText;
            button.classList.add('nxp-copy-button--error');
            setTimeout(function () {
                restoreLabel(button, originalText);
            }, 2000);
        });
    }

    var buttons = document.querySelectorAll('.nxp-copy-button[data-copy-text]');
    if (!buttons.length) {
        return;
    }

    buttons.forEach(function (button) {
        if (!button.getAttribute('data-copy-label')) {
            button.setAttribute('data-copy-label', button.textContent);
        }

        button.addEventListener('click', handleCopy);
    });
})();
</script>

<?php require DIR_WS_INCLUDES . 'footer.php'; ?>
</body>
</html>
<?php require 'includes/application_bottom.php'; ?>

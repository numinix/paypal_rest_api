<?php
/**
 * A token-caching class for the PayPalRestful (paypalr) Payment Module
 *
 * @copyright Copyright 2023 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 16 Modified in v2.0.0 $
 */

namespace PayPalRestful\Token;

use PayPalRestful\Common\Logger;

class TokenCache
{
    // -----
    // Constants used to encrypt the session-based copy of the access-token.  Used by
    // the getSavedToken/saveToken methods.
    //
    private const ENCRYPT_ALGO = 'AES-256-CBC';

    // -----
    // Variable that holds the selected cryptographic algorithm and its IV length.
    // Set during construction.
    //
    private $encryptionAlgorithm;
    private $encryptionAlgoIvLen;
    private $clientSecret;

    // -----
    // Contains an instance of the common Logger class.
    //
    protected $log;

    public function __construct(string $client_secret)
    {
        $this->log = new Logger();

        $this->encryptionAlgorithm = $this->setEncryptionAlgorithm();
        $this->encryptionAlgoIvLen = openssl_cipher_iv_length($this->encryptionAlgorithm);
        $this->clientSecret = $client_secret;
    }
    protected function setEncryptionAlgorithm(): string
    {
        return self::ENCRYPT_ALGO;
    }

    public function get(): string
    {
        if (!isset($_SESSION['PayPalRestful']['token_expires_ts'], $_SESSION['PayPalRestful']['saved_token']) || time() > $_SESSION['PayPalRestful']['token_expires_ts']) {
            $this->clear();
            return '';
        }

        $this->log->write('getSavedToken: Using saved access-token.');

        $encrypted_token = $_SESSION['PayPalRestful']['saved_token'];
        $iv = substr($encrypted_token, 0, $this->encryptionAlgoIvLen);
        $saved_token = openssl_decrypt(substr($encrypted_token, $this->encryptionAlgoIvLen), $this->encryptionAlgorithm, $this->clientSecret, 0, $iv);
        if ($saved_token === false) {
            $saved_token = '';
            $this->log->write('getSavedToken: Failed decryption.');
            $this->clear();
        }
        return $saved_token;
    }

    public function save(string $access_token, int $seconds_to_expiration)
    {
        $iv = openssl_random_pseudo_bytes($this->encryptionAlgoIvLen);
        $_SESSION['PayPalRestful']['saved_token'] = $iv . openssl_encrypt($access_token, $this->encryptionAlgorithm, $this->clientSecret, 0, $iv);
        $_SESSION['PayPalRestful']['token_expires_ts'] = time() + $seconds_to_expiration;
    }

    public function clear()
    {
        unset($_SESSION['PayPalRestful']['token_expires_ts'], $_SESSION['PayPalRestful']['saved_token']);
    }
}

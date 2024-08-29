<?php

class SecureEncryption
{
    private $algorithm_for_encrpt;
    private $path;
    private $key;
    private $iv_sep;
    public static $instance = NULL;

    function __construct()
    {
        $this->algorithm_for_encrpt = 'aes-256-cbc';    
        $this->path = plugin_dir_path(__FILE__).'/encryption/key.encrypted.txt';
        $this->iv_sep = '|:|';    

        // Get encryption key from secure location
        if (file_exists($this->path) && is_readable($this->path)) {
            $key_for_encrypt_data = file_get_contents($this->path);
        } else {
            $key_for_encrypt_data = $this->generate_encrypt_key();
        }

        // If no key found, generate a new one
        if (empty($key_for_encrypt_data)) {
            $key_for_encrypt_data = $this->generate_encrypt_key();
        }
        
        $this->key = $this->hex_to_bin($key_for_encrypt_data);
    }    
    
    public function generate_encrypt_key()
    {
        $encrypt_key = openssl_random_pseudo_bytes(32);
        $hex_encrypt_key = bin2hex($encrypt_key);
        
        if (file_put_contents($this->path, $hex_encrypt_key) === false) {
            $this->generate_log('Failed to write encryption key to file');
            return null;
        }

        return $hex_encrypt_key;
    }

    private function hex_to_bin($data)
    {
        return function_exists('hex2bin') ? hex2bin($data) : pack("H*" , $data);
    }

    public function generate_iv($length = null)
    {
        $iv_length = $length ? $length : openssl_cipher_iv_length($this->algorithm_for_encrpt);
        return openssl_random_pseudo_bytes($iv_length);
    }

    public static function instance() 
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function accept_payment_offline_encrypt($plain_text)
    {
        if (function_exists('openssl_encrypt')) {
            $iv = $this->generate_iv();
            $encrypted = openssl_encrypt($plain_text, $this->algorithm_for_encrpt, $this->key, 0, $iv);
            if ($encrypted === false) {
                $this->generate_log('Encryption failed');
                return false;
            }
            $hex_iv = bin2hex($iv);
            return $encrypted . $this->iv_sep . $hex_iv;
        } else {
            $this->generate_log('openssl_encrypt function not supported');
        }

        return false;
    }

    public function accept_payment_offline_decrypt($cipher_text)
    {
        if (function_exists('openssl_decrypt')) {
            list($encrypted, $iv) = explode($this->iv_sep, $cipher_text);
            $bin_iv = $this->hex_to_bin($iv);
            $decrypted = openssl_decrypt($encrypted, $this->algorithm_for_encrpt, $this->key, 0, $bin_iv);
            if ($decrypted === false) {
                $this->generate_log('Decryption failed');
                return false;
            }
            return $decrypted;
        } else {
            $this->generate_log('openssl_decrypt function not supported');
        }

        return false;
    }
    
    public function generate_log($msg)
    {
        $log_date = date('Y-m-d H:i:s');
        $log_msg = '[' . $log_date . '] ' . $msg . PHP_EOL;
        $log_path = plugin_dir_path(__FILE__).'/log/accept_payment_offline.log';
        
        // Ensure the log directory exists and is writable
        if (!is_dir(dirname($log_path))) {
            mkdir(dirname($log_path), 0755, true);
        }

        if (!file_exists($log_path)) {
            if (!file_put_contents($log_path, '')) {
                error_log('Unable to create log file: ' . $log_path);
                return;
            }
        }

        if (file_put_contents($log_path, $log_msg, FILE_APPEND) === false) {
            error_log('Unable to write to log file: ' . $log_path);
        }
    }    
}
?>

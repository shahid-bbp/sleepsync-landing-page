<?php
// Ephemeral certificate for the loopback SMTP fixture, never used in production.
$options = ['private_key_bits' => 2048, 'digest_alg' => 'sha256'];
if (getenv('OPENSSL_CONF')) $options['config'] = getenv('OPENSSL_CONF');
$key = openssl_pkey_new($options);
$csr = openssl_csr_new(['commonName' => 'localhost'], $key, $options);
$cert = openssl_csr_sign($csr, null, $key, 1, $options);
openssl_pkey_export_to_file($key, $argv[1] . '/key.pem', null, $options);
openssl_x509_export_to_file($cert, $argv[1] . '/cert.pem');

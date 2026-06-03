<?php
session_start();

if (!isset($_SESSION['idusuario'])) {
    http_response_code(403);
    exit;
}

$toSign = file_get_contents('php://input');
if (empty($toSign)) {
    http_response_code(400);
    exit;
}

$keyFile = __DIR__ . '/../config/qz_private.key';
if (!file_exists($keyFile)) {
    http_response_code(503);
    exit;
}

// Intento 1: PHP nativo
$privateKey = openssl_pkey_get_private(file_get_contents($keyFile));
if ($privateKey) {
    openssl_sign($toSign, $signature, $privateKey, OPENSSL_ALGO_SHA512);
    if (!empty($signature)) {
        echo base64_encode($signature);
        exit;
    }
}

// Intento 2: openssl.exe (fallback para OpenSSL 3.x en Windows)
$opensslBin = 'C:\\xampp\\apache\\bin\\openssl.exe';
if (!file_exists($opensslBin)) {
    http_response_code(500);
    exit;
}

$tmpIn  = tempnam(sys_get_temp_dir(), 'qzin_');
$tmpOut = tempnam(sys_get_temp_dir(), 'qzout_');
file_put_contents($tmpIn, $toSign);

$cmd = '"' . $opensslBin . '" dgst -sha512 -sign "' . $keyFile . '" -out "' . $tmpOut . '" "' . $tmpIn . '" 2>&1';
exec($cmd, $cmdOut, $ret);

if ($ret === 0 && file_exists($tmpOut)) {
    $rawSig = file_get_contents($tmpOut);
    @unlink($tmpIn);
    @unlink($tmpOut);
    if (!empty($rawSig)) {
        echo base64_encode($rawSig);
        exit;
    }
}

@unlink($tmpIn);
@unlink($tmpOut);
http_response_code(500);

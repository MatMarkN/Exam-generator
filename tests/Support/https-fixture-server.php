<?php

declare(strict_types=1);

// Servidor de UMA requisição, exclusivamente em loopback do container descartável.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || $argc !== 3) {
    exit(2);
}
$context = stream_context_create(['ssl' => ['local_cert' => $argv[1], 'verify_peer' => false]]);
$server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if ($server === false) {
    exit(3);
}
echo substr(strrchr(stream_socket_get_name($server, false), ':'), 1) . "\n";
flush();
$connection = stream_socket_accept($server, 8);
if ($connection === false) {
    exit(4);
}
stream_set_timeout($connection, 4);
if (@stream_socket_enable_crypto($connection, true, STREAM_CRYPTO_METHOD_TLS_SERVER) !== true) {
    exit(0); // Esperado no teste que rejeita o certificado não confiável.
}
$rawHeaders = '';
while (($line = fgets($connection)) !== false && $line !== "\r\n") {
    $rawHeaders .= $line;
}
preg_match('/Content-Length: ([0-9]+)/i', $rawHeaders, $match);
$body = '';
$size = (int) ($match[1] ?? 0);
while (strlen($body) < $size && !feof($connection)) {
    $body .= fread($connection, $size - strlen($body));
}
if ($argv[2] === 'slow') {
    sleep(4);
}
$responseBody = $argv[2] === 'large' ? str_repeat('x', 1048577) : json_encode(['headers' => $rawHeaders, 'body' => $body], JSON_THROW_ON_ERROR);
$status = $argv[2] === 'redirect' ? '302 Found' : '200 OK';
$response = "HTTP/1.1 $status\r\nContent-Type: application/json\r\nRetry-After: 7\r\n"
    . ($argv[2] === 'redirect' ? "Location: https://127.0.0.1:1/never\r\n" : '')
    . 'Content-Length: ' . strlen($responseBody) . "\r\nConnection: close\r\n\r\n" . $responseBody;
while ($response !== '') {
    $written = @fwrite($connection, $response);
    if ($written === false || $written === 0) {
        break;
    }
    $response = substr($response, $written);
}
fclose($connection);
fclose($server);

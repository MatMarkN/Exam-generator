<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Application\AI\AIErrorReason;
use App\Application\AI\AIProviderException;
use App\Infrastructure\AI\Http\CurlHttpTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CurlHttpTransportTest extends TestCase
{
    #[DataProvider('httpsCases')]
    public function testActualCurlAgainstLocalTlsServer(string $mode, ?string $error): void
    {
        $directory = sys_get_temp_dir() . '/exam-https-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        $privateKey = openssl_pkey_new(['private_key_bits' => 2048]);
        $csr = openssl_csr_new(['commonName' => 'localhost'], $privateKey, ['digest_alg' => 'sha256']);
        $certificate = openssl_csr_sign($csr, null, $privateKey, 1, ['digest_alg' => 'sha256']);
        openssl_x509_export($certificate, $certificatePem);
        openssl_pkey_export($privateKey, $privatePem);
        file_put_contents($directory . '/server.pem', $certificatePem . $privatePem);
        file_put_contents($directory . '/ca.pem', $certificatePem);
        $server = $client = null;
        $pipes = [];
        try {
            $server = proc_open([PHP_BINARY, __DIR__ . '/../Support/https-fixture-server.php', $directory . '/server.pem', $mode],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($server);
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 8);
            $port = trim((string) fgets($pipes[1]));
            self::assertMatchesRegularExpression('/^[0-9]+$/', $port);
            // Trust store apenas deste subprocesso de teste; o transporte de produção mantém TLS obrigatório.
            $command = [PHP_BINARY];
            if ($mode !== 'untrusted') {
                array_push($command, '-d', 'curl.cainfo=' . $directory . '/ca.pem');
            }
            array_push($command, __DIR__ . '/../Support/curl-fixture-client.php', $port, $mode === 'slow' ? '1' : '3');
            $client = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $clientPipes);
            self::assertIsResource($client);
            fclose($clientPipes[0]);
            $output = stream_get_contents($clientPipes[1]);
            $stderr = stream_get_contents($clientPipes[2]);
            fclose($clientPipes[1]);
            fclose($clientPipes[2]);
            self::assertSame(0, proc_close($client), $stderr);
            $client = null;
            $data = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
            if ($error !== null) {
                self::assertSame(['error' => $error], $data);
            } else {
                self::assertSame($mode === 'redirect' ? 302 : 200, $data['status']);
                self::assertSame('7', $data['headers']['retry-after']);
                $echo = json_decode($data['body'], true, flags: JSON_THROW_ON_ERROR);
                self::assertStringContainsString('POST /fixture HTTP/', $echo['headers']);
                self::assertStringContainsString('x-goog-api-key: synthetic-key', $echo['headers']);
                self::assertSame('{"test":"local"}', $echo['body']);
            }
        } finally {
            if (is_resource($client)) {
                proc_terminate($client);
                proc_close($client);
            }
            if (is_resource($server)) {
                proc_terminate($server);
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($server);
            }
            unlink($directory . '/server.pem');
            unlink($directory . '/ca.pem');
            rmdir($directory);
        }
    }

    public static function httpsCases(): array
    {
        return [['success', null], ['redirect', null], ['large', 'invalid_response'], ['slow', 'timeout'], ['untrusted', 'unavailable']];
    }

    public function testPlainHttpAndHeaderInjectionAreRejectedWithoutSendingAnything(): void
    {
        foreach ([['http://localhost/', []], ['https://localhost/', ['x-goog-api-key' => "secret\r\nInjected: yes"]]] as [$url, $headers]) {
            try {
                (new CurlHttpTransport())->post($url, $headers, '{}', 1);
                self::fail('Transporte deveria rejeitar o pedido.');
            } catch (AIProviderException $exception) {
                self::assertSame(AIErrorReason::InvalidRequest, $exception->reason);
            }
        }
    }
}

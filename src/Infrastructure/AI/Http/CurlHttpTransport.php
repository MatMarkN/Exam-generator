<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Http;

use App\Application\AI\AIErrorReason;
use App\Application\AI\AIProviderException;

/** Uma única tentativa HTTPS, sem redirects e sem logs de headers ou corpo. */
final class CurlHttpTransport implements HttpTransportInterface
{
    public const MAX_RESPONSE_BYTES = 1024 * 1024;

    public function post(string $url, #[\SensitiveParameter] array $headers, string $json, int $timeoutSeconds): HttpResponse
    {
        $parts = parse_url($url);
        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || $timeoutSeconds < 1 || $timeoutSeconds > 120) {
            throw new AIProviderException(AIErrorReason::InvalidRequest);
        }
        $lines = [];
        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_string($value) || !preg_match('/^[A-Za-z0-9-]+$/D', $name)
                || preg_match('/[\x00-\x1f\x7f]/', $value)) {
                throw new AIProviderException(AIErrorReason::InvalidRequest);
            }
            $lines[] = $name . ': ' . $value;
        }
        $handle = curl_init($url);
        if ($handle === false) {
            throw new AIProviderException(AIErrorReason::Unavailable);
        }
        $body = '';
        $responseHeaders = [];
        $headerBytes = 0;
        $tooLarge = false;
        try {
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_HTTPHEADER => $lines,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_CONNECTTIMEOUT => min(10, $timeoutSeconds),
                CURLOPT_TIMEOUT => $timeoutSeconds,
                CURLOPT_NOSIGNAL => true,
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge): int {
                    if (strlen($body) + strlen($chunk) > self::MAX_RESPONSE_BYTES) {
                        $tooLarge = true;
                        return 0;
                    }
                    $body .= $chunk;
                    return strlen($chunk);
                },
                CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders, &$headerBytes, &$tooLarge): int {
                    $headerBytes += strlen($line);
                    if ($headerBytes > 32768) {
                        $tooLarge = true;
                        return 0;
                    }
                    if (str_starts_with($line, 'HTTP/')) {
                        $responseHeaders = [];
                    } elseif (str_contains($line, ':')) {
                        [$name, $value] = explode(':', $line, 2);
                        $responseHeaders[strtolower(trim($name))] = trim($value);
                    }
                    return strlen($line);
                },
            ]);
            $ok = curl_exec($handle);
            if ($tooLarge) {
                throw new AIProviderException(AIErrorReason::InvalidResponse);
            }
            if ($ok === false) {
                // curl_error() pode conter detalhes sensíveis; não anexar à exceção.
                throw new AIProviderException(curl_errno($handle) === CURLE_OPERATION_TIMEDOUT
                    ? AIErrorReason::Timeout : AIErrorReason::Unavailable);
            }
            return new HttpResponse((int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $body, $responseHeaders);
        } finally {
            curl_close($handle);
        }
    }
}

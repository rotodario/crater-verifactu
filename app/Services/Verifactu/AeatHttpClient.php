<?php

namespace Crater\Services\Verifactu;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;

/**
 * Sends a VERI*FACTU SOAP request to the AEAT endpoint using mutual TLS
 * (client certificate authentication).
 *
 * Supported certificate formats:
 *   - PKCS12 (.p12 / .pfx) — extracted to temp PEM files at call time
 *   - PEM    (.pem)        — used directly by Guzzle
 */
class AeatHttpClient
{
    public function __construct(
        protected string  $endpointUrl,
        protected string  $certPath,
        protected string  $certPassword = '',
        protected int     $timeoutSeconds = 30
    ) {}

    /**
     * Send the SOAP XML and return the raw response body string.
     *
     * @throws RuntimeException on network error or non-2xx HTTP status
     */
    public function send(string $soapXml): string
    {
        [$certFile, $keyFile, $keyPassword, $cleanup] = $this->prepareCert();

        try {
            $client = new Client([
                'timeout'         => $this->timeoutSeconds,
                'connect_timeout' => 10,
                'verify'          => true,   // verify AEAT server certificate
                'cert'            => $certFile,
                // Key extracted from PKCS12 is already unencrypted PEM; PEM files
                // may carry a password. Only pass password when non-empty.
                'ssl_key'         => $keyPassword ? [$keyFile, $keyPassword] : $keyFile,
            ]);

            $response = $client->post($this->endpointUrl, [
                'body'    => $soapXml,
                'headers' => [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction'   => '',
                ],
            ]);

            return (string) $response->getBody();
        } catch (RequestException $e) {
            $body = $e->hasResponse() ? (string) $e->getResponse()->getBody() : '';
            throw new RuntimeException(
                'AEAT HTTP error: ' . $e->getMessage() . ($body ? " | Response: {$body}" : ''),
                0,
                $e
            );
        } finally {
            $cleanup();
        }
    }

    /**
     * Prepare certificate files for Guzzle and return [certFile, keyFile, cleanup].
     *
     * For PKCS12: extracts cert + key to temp PEM files.
     * For PEM:    returns the path directly (cert and key assumed in same file).
     *
     * @return array{0: string, 1: string, 2: callable}
     */
    /**
     * Prepare certificate files for Guzzle.
     * Returns [certFile, keyFile, keyPassword, cleanup].
     *
     * - PKCS12 (.p12/.pfx): extracts cert + key to unencrypted temp PEM files.
     *   The extracted key needs NO password (already decrypted by openssl_pkcs12_read).
     * - PEM (.pem): used directly; the certPassword is passed as the key password.
     *
     * @return array{0: string, 1: string, 2: string|null, 3: callable}
     */
    private function prepareCert(): array
    {
        if (! file_exists($this->certPath)) {
            throw new RuntimeException("Certificate file not found: {$this->certPath}");
        }

        $ext = strtolower(pathinfo($this->certPath, PATHINFO_EXTENSION));

        if (in_array($ext, ['p12', 'pfx'])) {
            return $this->extractPkcs12();
        }

        // PEM — cert and key in the same file; pass certPassword for encrypted keys
        return [
            $this->certPath,
            $this->certPath,
            $this->certPassword ?: null,
            fn() => null,
        ];
    }

    /**
     * Read a PKCS12 file and write separate temp PEM files for cert and key.
     * The extracted private key is unencrypted PEM — no password needed.
     *
     * @return array{0: string, 1: string, 2: null, 3: callable}
     */
    private function extractPkcs12(): array
    {
        $pkcs12 = file_get_contents($this->certPath);

        $certs = [];
        if (! openssl_pkcs12_read($pkcs12, $certs, $this->certPassword)) {
            throw new RuntimeException(
                'Failed to read PKCS12 certificate. Check the file and password.'
            );
        }

        $tmpDir   = sys_get_temp_dir();
        $prefix   = $tmpDir . DIRECTORY_SEPARATOR . 'verifactu_' . uniqid();
        $certFile = $prefix . '_cert.pem';
        $keyFile  = $prefix . '_key.pem';

        // Write entity cert; include extra certs (CA chain) when present
        $certPem = $certs['cert'];
        if (! empty($certs['extracerts'])) {
            foreach ($certs['extracerts'] as $extra) {
                $certPem .= "\n" . $extra;
            }
        }
        file_put_contents($certFile, $certPem);
        file_put_contents($keyFile,  $certs['pkey']); // unencrypted PEM

        $cleanup = function () use ($certFile, $keyFile) {
            @unlink($certFile);
            @unlink($keyFile);
        };

        // keyPassword = null because extracted PEM key is NOT encrypted
        return [$certFile, $keyFile, null, $cleanup];
    }
}

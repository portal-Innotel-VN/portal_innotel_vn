<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Reminder_delivery_whatsapp_adapter
{
    private $CI;
    private $httpClient;

    public function __construct($client = null)
    {
        $this->CI = &get_instance();
        if ($client !== null) {
            $this->httpClient = $client;
        } else {
            if (class_exists('GuzzleHttp\Client')) {
                $this->httpClient = new GuzzleHttp\Client();
            }
        }
    }

    /**
     * Send a WhatsApp message via Baileys Gateway with fixed idempotency key.
     *
     * @param string $recipient Target JID (e.g. 1203630281928371@g.us or 84901234567@s.whatsapp.net)
     * @param string $text Formatted message text
     * @param int $deliveryId Delivery outbox row ID
     * @param array $extraOptions Optional configuration overrides
     * @return array Result descriptor
     */
    public function send($recipient, $text, $deliveryId, array $extraOptions = [])
    {
        $endpoint = !empty($extraOptions['endpoint']) ? $extraOptions['endpoint'] : trim((string) get_option('sp_reminder_whatsapp_endpoint'));
        if ($endpoint === '') {
            $endpoint = 'http://127.0.0.1:3050/api/v1/messages/send';
        }
        $secretKey = isset($extraOptions['secret_key']) ? (string) $extraOptions['secret_key'] : (string) get_option('sp_reminder_whatsapp_secret_key');
        $timeoutSeconds = isset($extraOptions['timeout']) ? (float) $extraOptions['timeout'] : max(1, (int) get_option('sp_reminder_whatsapp_timeout_seconds'));

        if (!empty($extraOptions['idempotency_key'])) {
            $idempotencyKey = (string) $extraOptions['idempotency_key'];
        } else {
            $idempotencyKey = $this->buildIdempotencyKey($deliveryId);
        }
        $payload = [
            'idempotency_key' => $idempotencyKey,
            'recipient'       => trim((string) $recipient),
            'text'            => (string) $text,
        ];

        $headers = [
            'Content-Type'     => 'application/json',
            'Accept'           => 'application/json',
            'X-Gateway-Secret' => $secretKey,
        ];

        try {
            $response = $this->getHttpClient()->request('POST', $endpoint, [
                'headers'         => $headers,
                'json'            => $payload,
                'connect_timeout' => 3.0,
                'timeout'         => (float) $timeoutSeconds,
                'http_errors'     => false,
            ]);

            $statusCode = $response->getStatusCode();
            $rawBody = (string) $response->getBody();
            $body = json_decode($rawBody, true) ?: [];

            if ($statusCode >= 200 && $statusCode < 300) {
                return [
                    'success'             => true,
                    'uncertain'           => false,
                    'provider_message_id' => $body['message_id'] ?? ($body['data']['message_id'] ?? null),
                    'raw'                 => $body,
                ];
            }

            // 1. Highest priority: unconfirmed or retry_safe=false from gateway
            if ((isset($body['retry_safe']) && $body['retry_safe'] === false)
                || ($body['error'] ?? '') === 'send_failed_or_unconfirmed') {
                return [
                    'success'          => false,
                    'uncertain'        => true,
                    'last_error'       => 'Gateway error: ' . ($body['error'] ?? 'send_failed_or_unconfirmed') . ' (retry_safe=false)',
                    'last_error_code'  => 'whatsapp_delivery_uncertain',
                    'last_error_class' => 'uncertain',
                ];
            }

            if ($statusCode === 401 || $statusCode === 403) {
                return [
                    'success'          => false,
                    'uncertain'        => false,
                    'last_error'       => 'Unauthorized: Invalid Gateway secret key',
                    'last_error_code'  => 'gateway_authentication_failed',
                    'last_error_class' => 'permanent',
                ];
            }

            if ($statusCode === 429 || $statusCode === 409) {
                return [
                    'success'          => false,
                    'uncertain'        => false,
                    'last_error'       => $body['error'] ?? ('Gateway busy / in-progress (HTTP ' . $statusCode . ')'),
                    'last_error_code'  => 'gateway_http_' . $statusCode,
                    'last_error_class' => 'transient',
                ];
            }

            return [
                'success'          => false,
                'uncertain'        => false,
                'last_error'       => $body['error'] ?? ('Gateway HTTP ' . $statusCode . ': ' . mb_substr($rawBody, 0, 150)),
                'last_error_code'  => 'gateway_http_' . $statusCode,
                'last_error_class' => ($statusCode >= 500) ? 'transient' : 'permanent',
            ];
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            if (self::isTimeoutOrReceiveError($e)) {
                return [
                    'success'          => false,
                    'uncertain'        => true,
                    'last_error'       => 'Gateway response timed out after ' . $timeoutSeconds . 's (ConnectException in-flight uncertainty)',
                    'last_error_code'  => 'whatsapp_delivery_uncertain',
                    'last_error_class' => 'uncertain',
                ];
            }
            return [
                'success'          => false,
                'uncertain'        => false,
                'last_error'       => 'Gateway connection failed: ' . $e->getMessage(),
                'last_error_code'  => 'gateway_connection_failed',
                'last_error_class' => 'transient',
            ];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            if (self::isTimeoutOrReceiveError($e)) {
                return [
                    'success'          => false,
                    'uncertain'        => true,
                    'last_error'       => 'Gateway response timed out after ' . $timeoutSeconds . 's (RequestException in-flight uncertainty)',
                    'last_error_code'  => 'whatsapp_delivery_uncertain',
                    'last_error_class' => 'uncertain',
                ];
            }
            return [
                'success'          => false,
                'uncertain'        => false,
                'last_error'       => 'Gateway request exception: ' . $e->getMessage(),
                'last_error_code'  => 'gateway_request_exception',
                'last_error_class' => 'transient',
            ];
        } catch (\Throwable $e) {
            if (self::isTimeoutOrReceiveError($e)) {
                return [
                    'success'          => false,
                    'uncertain'        => true,
                    'last_error'       => 'Gateway response timed out: ' . $e->getMessage(),
                    'last_error_code'  => 'whatsapp_delivery_uncertain',
                    'last_error_class' => 'uncertain',
                ];
            }
            return [
                'success'          => false,
                'uncertain'        => false,
                'last_error'       => 'Unexpected error: ' . $e->getMessage(),
                'last_error_code'  => 'whatsapp_adapter_exception',
                'last_error_class' => 'transient',
            ];
        }
    }

    /**
     * Check status of a previously dispatched delivery using its persistent idempotency key.
     *
     * @param int $deliveryId
     * @param float $timeoutSeconds Max wait time
     * @return array [ 'status' => 'sent'|'failed'|'not_found'|'uncertain', 'message_id' => ... ]
     */
    public function checkStatus($deliveryId, $timeoutSeconds = 3.0)
    {
        $idempotencyKey = $this->buildIdempotencyKey($deliveryId);
        $endpoint = trim((string) get_option('sp_reminder_whatsapp_endpoint'));
        $baseUrl = preg_replace('#/api/v1/messages/send/?$#', '', $endpoint);
        if ($baseUrl === '' || $baseUrl === $endpoint) {
            $baseUrl = 'http://127.0.0.1:3050';
        }
        $statusUrl = rtrim($baseUrl, '/') . '/api/v1/messages/status/' . rawurlencode($idempotencyKey);
        $secretKey = (string) get_option('sp_reminder_whatsapp_secret_key');

        try {
            $response = $this->getHttpClient()->request('GET', $statusUrl, [
                'headers'         => [
                    'Accept'           => 'application/json',
                    'X-Gateway-Secret' => $secretKey,
                ],
                'connect_timeout' => 2.0,
                'timeout'         => (float) $timeoutSeconds,
                'http_errors'     => false,
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode((string) $response->getBody(), true) ?: [];

            if ($statusCode === 200 && !empty($body['status'])) {
                $status = (string) $body['status'];
                $knownStatuses = ['sent', 'failed', 'not_found', 'in_progress'];
                if (!in_array($status, $knownStatuses, true)) {
                    $status = 'uncertain';
                }
                return [
                    'status'     => $status,
                    'message_id' => $body['message_id'] ?? null,
                    'error'      => $body['error'] ?? null,
                    'retry_safe' => $body['retry_safe'] ?? null,
                    'raw'        => $body,
                ];
            }

            if ($statusCode === 404) {
                return ['status' => 'not_found'];
            }

            return ['status' => 'uncertain', 'code' => $statusCode];
        } catch (\Throwable $e) {
            return ['status' => 'uncertain', 'error' => $e->getMessage()];
        }
    }

    /**
     * Check health status of Gateway.
     *
     * @param string|null $endpoint
     * @param string|null $secretKey
     * @return array [ 'connected' => bool, 'status' => string, 'error' => null|string ]
     */
    public function checkHealth($endpoint = null, $secretKey = null)
    {
        if ($endpoint === null) {
            $endpoint = trim((string) get_option('sp_reminder_whatsapp_endpoint'));
        }
        if ($secretKey === null) {
            $secretKey = (string) get_option('sp_reminder_whatsapp_secret_key');
        }
        $baseUrl = preg_replace('#/api/v1/messages/send/?$#', '', $endpoint);
        if ($baseUrl === '' || $baseUrl === $endpoint) {
            $baseUrl = 'http://127.0.0.1:3050';
        }
        $healthUrl = rtrim($baseUrl, '/') . '/api/v1/health';

        try {
            $response = $this->getHttpClient()->request('GET', $healthUrl, [
                'headers'         => [
                    'Accept'           => 'application/json',
                    'X-Gateway-Secret' => $secretKey,
                ],
                'connect_timeout' => 3.0,
                'timeout'         => 4.0,
                'http_errors'     => false,
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode((string) $response->getBody(), true) ?: [];

            if ($statusCode === 200) {
                $status = strtoupper((string) ($body['status'] ?? ''));
                $connected = ($status === 'CONNECTED' || !empty($body['connected']));
                return [
                    'connected' => $connected,
                    'status'    => $status ?: 'ONLINE',
                    'error'     => null,
                ];
            }

            if ($statusCode === 401 || $statusCode === 403) {
                return [
                    'connected' => false,
                    'status'    => 'UNAUTHORIZED',
                    'error'     => 'Invalid Gateway secret key',
                ];
            }

            return [
                'connected' => false,
                'status'    => 'HTTP_' . $statusCode,
                'error'     => 'Gateway returned HTTP ' . $statusCode,
            ];
        } catch (\Throwable $e) {
            return [
                'connected' => false,
                'status'    => 'OFFLINE',
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Query all participating WhatsApp groups from Gateway.
     */
    public function fetchGroups($endpoint = null, $secretKey = null)
    {
        $targetEndpoint = $endpoint ?: (string) get_option('sp_reminder_whatsapp_endpoint');
        $targetSecret = $secretKey !== null ? $secretKey : (string) get_option('sp_reminder_whatsapp_secret_key');

        $groupsUrl = preg_replace('#/api/v1/messages/send/?$#', '/api/v1/groups', $targetEndpoint);
        if ($groupsUrl === $targetEndpoint) {
            $parsed = parse_url($targetEndpoint);
            $base = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? '127.0.0.1')
                . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
            $groupsUrl = rtrim($base, '/') . '/api/v1/groups';
        }

        try {
            $client = $this->getHttpClient();
            $response = $client->request('GET', $groupsUrl, [
                'headers' => [
                    'X-Gateway-Secret' => $targetSecret,
                    'Accept'           => 'application/json',
                ],
                'connect_timeout' => 3.0,
                'timeout'         => 8.0,
                'http_errors'     => false,
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode((string) $response->getBody(), true) ?: [];

            if ($statusCode === 200 && !empty($body['success'])) {
                return [
                    'success' => true,
                    'groups'  => $body['groups'] ?? [],
                    'error'   => null,
                ];
            }

            return [
                'success' => false,
                'groups'  => [],
                'error'   => $body['error'] ?? ('Gateway HTTP ' . $statusCode),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'groups'  => [],
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Check whether an exception indicates an in-flight network uncertainty
     * (timeout, disconnect, reset, connection drop during transfer).
     */
    public static function isTimeoutOrReceiveError(\Throwable $e)
    {
        // 1. Check curl handler context errno if available (Guzzle ConnectException / RequestException)
        if (method_exists($e, 'getHandlerContext')) {
            $ctx = $e->getHandlerContext();
            $errno = isset($ctx['errno']) ? (int) $ctx['errno'] : 0;
            // 28: CURLE_OPERATION_TIMEDOUT
            // 52: CURLE_GOT_NOTHING (empty reply from server after request sent)
            // 56: CURLE_RECV_ERROR (failure with receiving network data)
            if (in_array($errno, [28, 52, 56], true)) {
                return true;
            }
            // 7: CURLE_COULDNT_CONNECT, 6: CURLE_COULDNT_RESOLVE_HOST -> Definitely didn't reach gateway
            if (in_array($errno, [6, 7], true)) {
                return false;
            }
        }

        // 2. Fallback to message string analysis
        return self::isTimeoutMessage($e->getMessage());
    }

    /**
     * Check whether an exception message indicates an in-flight or connection timeout.
     */
    public static function isTimeoutMessage($message)
    {
        $msg = strtolower((string) $message);
        return strpos($msg, 'timed out') !== false
            || strpos($msg, 'timeout') !== false
            || strpos($msg, 'curl error 28') !== false
            || strpos($msg, 'curl error 52') !== false
            || strpos($msg, 'curl error 56') !== false
            || strpos($msg, 'connection reset') !== false
            || strpos($msg, 'empty reply from server') !== false;
    }

    /**
     * Build stable, persistent idempotency key tied to CRM instance and delivery row ID.
     */
    public static function formatIdempotencyKey($instanceId, $deliveryId)
    {
        return 'sp_' . $instanceId . '_del_' . (int) $deliveryId;
    }

    public function buildIdempotencyKey($deliveryId)
    {
        $instanceId = trim((string) get_option('sp_reminder_whatsapp_instance_id'));
        if ($instanceId === '') {
            $instanceId = bin2hex(random_bytes(6));
            update_option('sp_reminder_whatsapp_instance_id', $instanceId);
        }
        return self::formatIdempotencyKey($instanceId, $deliveryId);
    }

    private function getHttpClient()
    {
        if ($this->httpClient === null) {
            if (!class_exists('GuzzleHttp\Client')) {
                if (defined('FCPATH') && file_exists(FCPATH . 'application/vendor/autoload.php')) {
                    require_once FCPATH . 'application/vendor/autoload.php';
                } elseif (file_exists(dirname(__DIR__, 4) . '/application/vendor/autoload.php')) {
                    require_once dirname(__DIR__, 4) . '/application/vendor/autoload.php';
                }
            }
            if (class_exists('GuzzleHttp\Client')) {
                $this->httpClient = new GuzzleHttp\Client();
            } else {
                throw new RuntimeException('GuzzleHttp\Client is required for WhatsApp Baileys delivery');
            }
        }
        return $this->httpClient;
    }
}

<?php

namespace App\Services\Receipts\Adapters;

use Illuminate\Support\Facades\Http;
use Throwable;

class PaperlessNgxAdapter implements OcrEngineAdapterInterface
{
    public function getEngineName(): string
    {
        return 'Paperless-ngx';
    }

    public function checkAvailability(): array
    {
        $enabled = config('services.paperless.enabled', true);
        if (! $enabled) {
            return [
                'available' => false,
                'version' => null,
                'reason' => 'Paperless-ngx integration is disabled (PAPERLESS_ENABLED=false)',
            ];
        }

        $url = config('services.paperless.url', 'http://127.0.0.1:8000');
        $token = config('services.paperless.token', '');

        if (empty($token)) {
            return [
                'available' => false,
                'version' => null,
                'reason' => "PAPERLESS_API_TOKEN is not configured in .env file for {$url}",
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Token {$token}",
                'Accept' => 'application/json',
            ])->timeout(5)->get("{$url}/api/documents/");

            if ($response->status() === 401 || $response->status() === 403) {
                return [
                    'available' => false,
                    'version' => null,
                    'reason' => "Authentication failed (Invalid PAPERLESS_API_TOKEN at {$url})",
                ];
            }

            if ($response->successful()) {
                return [
                    'available' => true,
                    'version' => 'Paperless-ngx v2.x',
                    'reason' => null,
                ];
            }

            return [
                'available' => false,
                'version' => null,
                'reason' => "Paperless-ngx API returned HTTP {$response->status()} at {$url}",
            ];
        } catch (Throwable $e) {
            return [
                'available' => false,
                'version' => null,
                'reason' => "Connection refused to {$url} (Paperless-ngx service is offline)",
            ];
        }
    }

    public function scan(string $filePath): array
    {
        $start = microtime(true);
        $avail = $this->checkAvailability();

        if (! $avail['available']) {
            $durationMs = (int) round((microtime(true) - $start) * 1000);
            return [
                'engine' => 'Paperless-ngx',
                'status' => 'NOT_AVAILABLE',
                'raw_text' => '',
                'regions' => 0,
                'confidence' => null,
                'duration_ms' => $durationMs,
                'error' => $avail['reason'],
                'paperless_document_id' => null,
                'cleanup_status' => 'N/A',
            ];
        }

        $url = config('services.paperless.url', 'http://127.0.0.1:8000');
        $token = config('services.paperless.token', '');
        $timeout = (int) config('services.paperless.timeout', 90);

        $testTitle = 'AMIS_OCR_TEST_' . uniqid();

        try {
            // 1. Upload document to Paperless-ngx
            $uploadResponse = Http::withHeaders([
                'Authorization' => "Token {$token}",
                'Accept' => 'application/json',
            ])->timeout(30)->attach(
                'document',
                file_get_contents($filePath),
                basename($filePath)
            )->post("{$url}/api/documents/post_document/", [
                'title' => $testTitle,
            ]);

            if (! $uploadResponse->successful()) {
                $durationMs = (int) round((microtime(true) - $start) * 1000);
                return [
                    'engine' => 'Paperless-ngx',
                    'status' => 'FAILED',
                    'raw_text' => '',
                    'regions' => 0,
                    'confidence' => null,
                    'duration_ms' => $durationMs,
                    'error' => "Paperless upload failed (HTTP {$uploadResponse->status()}): {$uploadResponse->body()}",
                    'paperless_document_id' => null,
                    'cleanup_status' => 'N/A',
                ];
            }

            // Extract task ID (Response may be a task UUID string or JSON object)
            $taskData = $uploadResponse->json();
            $taskId = is_array($taskData) ? ($taskData['task_id'] ?? $taskData['task'] ?? null) : trim($uploadResponse->body(), '"');

            if (! $taskId) {
                $durationMs = (int) round((microtime(true) - $start) * 1000);
                return [
                    'engine' => 'Paperless-ngx',
                    'status' => 'FAILED',
                    'raw_text' => '',
                    'regions' => 0,
                    'confidence' => null,
                    'duration_ms' => $durationMs,
                    'error' => 'Paperless upload returned no task_id',
                    'paperless_document_id' => null,
                    'cleanup_status' => 'N/A',
                ];
            }

            // 2. Poll task status until consumption/OCR completes
            $documentId = null;
            $pollStart = time();

            while ((time() - $pollStart) < $timeout) {
                sleep(2);
                $taskCheck = Http::withHeaders([
                    'Authorization' => "Token {$token}",
                    'Accept' => 'application/json',
                ])->get("{$url}/api/tasks/?task_id={$taskId}");

                if ($taskCheck->successful()) {
                    $json = $taskCheck->json();
                    $taskList = is_array($json) ? ($json['results'] ?? (isset($json[0]) ? $json : [$json])) : [];
                    $taskInfo = $taskList[0] ?? [];
                    $status = strtoupper($taskInfo['status'] ?? '');

                    if ($status === 'SUCCESS') {
                        $documentId = $taskInfo['related_document']
                            ?? ($taskInfo['related_document_ids'][0] ?? null)
                            ?? ($taskInfo['result_data']['document_id'] ?? null)
                            ?? (is_numeric($taskInfo['result'] ?? null) ? (int) $taskInfo['result'] : null);

                        if ($documentId) {
                            break;
                        }
                    } elseif ($status === 'FAILURE') {
                        $durationMs = (int) round((microtime(true) - $start) * 1000);
                        return [
                            'engine' => 'Paperless-ngx',
                            'status' => 'FAILED',
                            'raw_text' => '',
                            'regions' => 0,
                            'confidence' => null,
                            'duration_ms' => $durationMs,
                            'error' => 'Paperless document consumption task failed: ' . ($taskInfo['result'] ?? 'Unknown error'),
                            'paperless_document_id' => null,
                            'cleanup_status' => 'N/A',
                        ];
                    }
                }
            }

            if (! $documentId) {
                $durationMs = (int) round((microtime(true) - $start) * 1000);
                return [
                    'engine' => 'Paperless-ngx',
                    'status' => 'FAILED',
                    'raw_text' => '',
                    'regions' => 0,
                    'confidence' => null,
                    'duration_ms' => $durationMs,
                    'error' => "Paperless consumption task timed out after {$timeout} seconds",
                    'paperless_document_id' => null,
                    'cleanup_status' => 'N/A',
                ];
            }

            // 3. Fetch processed document details & content
            $docResponse = Http::withHeaders([
                'Authorization' => "Token {$token}",
                'Accept' => 'application/json',
            ])->get("{$url}/api/documents/{$documentId}/");

            $rawText = '';
            if ($docResponse->successful()) {
                $docData = $docResponse->json();
                $rawText = (string) ($docData['content'] ?? '');
            }

            $lines = array_filter(array_map('trim', explode("\n", $rawText)));
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            // 4. Cleanup: Delete temporary test document from Paperless
            $cleanupStatus = 'FAILED TO DELETE';
            $deleteResponse = Http::withHeaders([
                'Authorization' => "Token {$token}",
                'Accept' => 'application/json',
            ])->delete("{$url}/api/documents/{$documentId}/");

            if ($deleteResponse->successful() || $deleteResponse->status() === 204) {
                $cleanupStatus = 'TEMP DOCUMENT DELETED';
            }

            return [
                'engine' => 'Paperless-ngx',
                'status' => 'SUCCESS',
                'raw_text' => implode("\n", $lines),
                'regions' => count($lines),
                'confidence' => null,
                'duration_ms' => $durationMs,
                'paperless_document_id' => $documentId,
                'cleanup_status' => $cleanupStatus,
                'error' => null,
            ];
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $start) * 1000);
            return [
                'engine' => 'Paperless-ngx',
                'status' => 'FAILED',
                'raw_text' => '',
                'regions' => 0,
                'confidence' => null,
                'duration_ms' => $durationMs,
                'error' => 'Paperless integration error: ' . $e->getMessage(),
                'paperless_document_id' => null,
                'cleanup_status' => 'N/A',
            ];
        }
    }
}

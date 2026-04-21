<?php
namespace app\services;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

/**
 * S3-compatible object storage for XML feeds (Stackhero MinIO).
 * Required env vars: STACKHERO_MINIO_HOST, STACKHERO_MINIO_ROOT_ACCESS_KEY, STACKHERO_MINIO_ROOT_SECRET_KEY
 * Optional env vars: MINIO_BUCKET (default: feeds)
 */
class FeedStorageService
{
    private S3Client $s3;
    private string $bucket;

    /** @var array<string,true>|null  Request-level cache of existing keys */
    private static ?array $keysCache = null;

    public function __construct(S3Client $s3, string $bucket)
    {
        $this->s3 = $s3;
        $this->bucket = $bucket;
    }

    public static function isConfigured(): bool
    {
        return (bool) getenv('STACKHERO_MINIO_HOST');
    }

    public static function create(): self
    {
        $host   = getenv('STACKHERO_MINIO_HOST');
        $key    = getenv('STACKHERO_MINIO_ROOT_ACCESS_KEY');
        $secret = getenv('STACKHERO_MINIO_ROOT_SECRET_KEY');
        $bucket = getenv('MINIO_BUCKET') ?: 'feeds';
        $region = 'us-east-1';

        if (!$host || !$key || !$secret) {
            throw new \RuntimeException('MinIO not configured. Set STACKHERO_MINIO_HOST, STACKHERO_MINIO_ROOT_ACCESS_KEY, STACKHERO_MINIO_ROOT_SECRET_KEY env vars.');
        }

        $endpoint = 'https://' . $host;

        $s3 = new S3Client([
            'version'                  => 'latest',
            'region'                   => $region,
            'endpoint'                 => $endpoint,
            'use_path_style_endpoint'  => true,
            'credentials'              => [
                'key'    => $key,
                'secret' => $secret,
            ],
        ]);

        return new self($s3, $bucket);
    }

    public function exists(string $key): bool
    {
        return $this->s3->doesObjectExist($this->bucket, $key);
    }

    /**
     * Cached version of exists() — loads all feed keys once per request.
     * Use this when checking many keys in a loop (e.g. admin user list).
     */
    public function existsCached(string $key): bool
    {
        if (self::$keysCache === null) {
            $this->warmCache();
        }
        return isset(self::$keysCache[$key]);
    }

    /**
     * Invalidate the request-level cache (call after put/delete in the same request).
     */
    public function invalidateCache(): void
    {
        self::$keysCache = null;
    }

    private function warmCache(): void
    {
        self::$keysCache = [];
        $types = ['product', 'order', 'customer', 'category'];

        foreach ($types as $type) {
            $params = ['Bucket' => $this->bucket, 'Prefix' => $type . '/'];

            do {
                $result = $this->s3->listObjectsV2($params);
                foreach ($result['Contents'] ?? [] as $object) {
                    self::$keysCache[$object['Key']] = true;
                }
                $params['ContinuationToken'] = $result['NextContinuationToken'] ?? null;
            } while ($result['IsTruncated']);
        }
    }

    /**
     * Upload a single page chunk. Key format: {baseKey}.chunk.{05d-padded-index}
     */
    public function putChunk(string $baseKey, int $chunk, string $content): void
    {
        $this->s3->putObject([
            'Bucket' => $this->bucket,
            'Key'    => sprintf('%s.chunk.%05d', $baseKey, $chunk),
            'Body'   => $content,
        ]);
    }

    /**
     * List, concat and delete all chunks for a base key.
     * Logs a warning if found count differs from $expectedCount.
     */
    public function collectAndDeleteChunks(string $baseKey, int $expectedCount = -1): string
    {
        $prefix = $baseKey . '.chunk.';
        $params = ['Bucket' => $this->bucket, 'Prefix' => $prefix];
        $keys   = [];

        do {
            $result = $this->s3->listObjectsV2($params);
            foreach ($result['Contents'] ?? [] as $obj) {
                $keys[] = $obj['Key'];
            }
            $params['ContinuationToken'] = $result['NextContinuationToken'] ?? null;
        } while (!empty($result['IsTruncated']));

        sort($keys);

        if ($expectedCount > 0 && count($keys) !== $expectedCount) {
            echo "WARNING: expected {$expectedCount} chunks, found " . count($keys) . " — feed may be incomplete" . PHP_EOL;
            for ($i = 0; $i < $expectedCount; $i++) {
                $expected = sprintf('%s.chunk.%05d', $baseKey, $i);
                if (!in_array($expected, $keys)) {
                    echo "  missing chunk: {$expected}" . PHP_EOL;
                }
            }
        }

        $buffer = '';
        foreach ($keys as $key) {
            $obj     = $this->s3->getObject(['Bucket' => $this->bucket, 'Key' => $key]);
            $buffer .= (string) $obj['Body'];
            $this->s3->deleteObject(['Bucket' => $this->bucket, 'Key' => $key]);
        }

        return $buffer;
    }

    /**
     * Check whether any chunks exist for a base key (used instead of exists() for temp files).
     */
    public function chunksExist(string $baseKey): bool
    {
        $result = $this->s3->listObjectsV2([
            'Bucket'  => $this->bucket,
            'Prefix'  => $baseKey . '.chunk.',
            'MaxKeys' => 1,
        ]);
        return !empty($result['Contents']);
    }

    public function get(string $key): string
    {
        $result = $this->s3->getObject([
            'Bucket' => $this->bucket,
            'Key'    => $key,
        ]);
        return (string) $result['Body'];
    }

    /**
     * Stream object body directly to output in chunks — avoids loading entire file into memory.
     * Call after setting response headers. Returns Content-Length if available.
     */
    public function stream(string $key, int $chunkSize = 1048576): ?int
    {
        $result = $this->s3->getObject([
            'Bucket' => $this->bucket,
            'Key'    => $key,
        ]);

        $contentLength = $result['ContentLength'] ?? null;
        $body          = $result['Body'];

        $body->rewind();
        while (! $body->eof()) {
            echo $body->read($chunkSize);
            flush();
        }

        return $contentLength ? (int) $contentLength : null;
    }

    public function put(string $key, string $content, string $contentType = 'application/octet-stream'): void
    {
        $this->s3->putObject([
            'Bucket'      => $this->bucket,
            'Key'         => $key,
            'Body'        => $content,
            'ContentType' => $contentType,
        ]);
    }

    public function append(string $key, string $additionalContent): void
    {
        $existing = $this->exists($key) ? $this->get($key) : '';
        $this->put($key, $existing . $additionalContent);
    }

    public function delete(string $key): void
    {
        try {
            $this->s3->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);
        } catch (S3Exception $e) {
            // ignore if not found
        }
    }
}

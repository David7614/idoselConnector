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

    public function get(string $key): string
    {
        $result = $this->s3->getObject([
            'Bucket' => $this->bucket,
            'Key'    => $key,
        ]);
        return (string) $result['Body'];
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

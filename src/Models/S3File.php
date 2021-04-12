<?php

namespace STS\ZipStream\Models;

use Aws;
use Aws\Result as AwsResult;
use Aws\S3\S3Client;
use Aws\S3\S3UriParser;
use function GuzzleHttp\Psr7\stream_for;
use Psr\Http\Message\StreamInterface;
use Aws\S3\Exception\S3Exception;

class S3File extends File
{
    /** @var string */
    protected $region;

    /** @var S3Client */
    protected $client;

    /** @var AwsResult|null|int */
    protected $fetchedObject;

    /**
     * @return int
     */
    public function calculateFilesize(): int
    {
        return optional($this->object())->get('ContentLength') ?? 0;
    }

    /**
     * @param S3Client $client
     *
     * @return mixed
     */
    public function setS3Client(S3Client $client)
    {
        $this->client = $client;

        return $this;
    }

    /**
     * @return S3Client
     */
    public function getS3Client(): S3Client
    {
        if (! $this->client) {
            $this->client = app('zipstream.s3client');
        }

        return $this->client;
    }

    /**
     * @return string
     */
    public function getBucket(): string
    {
        return parse_url($this->getSource(), PHP_URL_HOST);
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return ltrim(parse_url($this->getSource(), PHP_URL_PATH), "/");
    }

    /**
     * @return StreamInterface
     */
    protected function buildReadableStream(): StreamInterface
    {
        return optional($this->object())->get('Body') ?? stream_for(null);
    }

    /**
     * @return StreamInterface
     */
    protected function buildWritableStream(): StreamInterface
    {
        $this->getS3Client()->registerStreamWrapper();

        return stream_for(fopen($this->getSource(), 'w'));
    }

    /**
     * @return AwsResult|null
     */
    protected function object(): ?AwsResult
    {
        if (! $this->fetchedObject) {
            try {
                return $this->fetchedObject = $this->getS3Client()->getObject([
                    'Bucket' => $this->getBucket(),
                    'Key' => $this->getKey()
                ]);
            } catch (S3Exception $e) {
                // Set to -1 to know that we don't need to re-fetch it, as it will fail again.
                $this->fetchedObject = -1;

                return null;
            }
        }

        if ($this->fetchedObject === -1) {
            return null;
        }

        return $this->fetchedObject;
    }
}

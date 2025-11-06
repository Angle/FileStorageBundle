<?php

namespace Angle\FileStorageBundle\Storage;

use Angle\Utilities\SlugUtility;

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\Blob;
use MicrosoftAzure\Storage\Blob\Models\BlobAccessPolicy;
use MicrosoftAzure\Storage\Blob\Models\ContainerACL;
use MicrosoftAzure\Storage\Blob\Models\GetBlobOptions;
use MicrosoftAzure\Storage\Blob\Models\GetBlobResult;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Blob\Models\SetBlobPropertiesOptions;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AzureBlobStorage implements StorageInterface
{
    public const ACL_NONE = '';
    public const ACL_BLOB = 'blob';
    public const ACL_CONTAINER = 'container';

    protected ?string $connectionString;
    protected ?string $container;

    /** @var BlobRestProxy $blobClient */
    protected ?BlobRestProxy $blobClient;

    public function __construct(string $accountName, string $accountKey, string $containerName)
    {
        // Build the connection string
        $this->connectionString = sprintf('DefaultEndpointsProtocol=https;AccountName=%s;AccountKey=%s;EndpointSuffix=core.windows.net', $accountName, $accountKey);
        $this->container = $containerName;

        $this->initializeBlobClient();
    }

    private function initializeBlobClient(): void
    {
        try {
            $this->blobClient = BlobRestProxy::createBlobService($this->connectionString);
        } catch (\Throwable $e) {
            throw new \RuntimeException("Azure Blob Storage failed to initialize: " . $e->getMessage());
        }
    }


    #########################
    ##      INTERFACE      ##
    #########################

    public function exists(string $key): bool
    {
        $options = new ListBlobsOptions();
        $options->setPrefix($key);
        $options->setMaxResults(1);
        $options->setIncludeDeleted(false);

        $blobList = $this->blobClient->listBlobs($this->container,$options);

        return (count($blobList->getBlobs()) > 0);
    }

    public function write(string $key, $content, $contentType = null, $originalName = null): bool
    {
        $this->blobClient->createBlockBlob($this->container, $key, $content);

        // Configure Blob Options
        $blobOptions = new SetBlobPropertiesOptions();

        // TODO:

        // if specific ContentType wishes to be specified for different file dispositions.
        if ($contentType) $blobOptions->setContentType($contentType);
        // In case of download disposition files, ensure FileName set as desired
        if ($originalName) {

            // clean up the filename
            setlocale(LC_ALL, 'en_US.UTF-8');
            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
            $nameWithoutExtension = substr($originalName, 0, -1-strlen($extension));
            $attachmentFilename = SlugUtility::slugify($nameWithoutExtension, 120) . '.' . $extension; // this will also shorten it if too long

            $blobOptions->setContentDisposition('attachment; filename=' . $attachmentFilename);
        }

        $this->blobClient->setBlobProperties(
            $this->container,
            $key,
            $blobOptions
        );

        return true;
    }

    /**
     * @param string $blobKey
     * @return GetBlobResult
     */
    private function getBlob(string $blobKey): GetBlobResult
    {
        return $this->blobClient->getBlob($this->container, $blobKey);
    }

    /**
     * Get a blob from Azure, return a StreamedResponse ready to be served as a stream
     *
     * @param string $key
     * @throws \Exception
     * @return StreamedResponse
     */
    public function getAsStreamedResponse(string $key): StreamedResponse
    {
        try {
            $r = $this->getBlob($key);
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw $e;
        }

        $body = stream_get_contents($r->getContentStream());

        $contentType = $r->getProperties()->getContentType();

        $response = new StreamedResponse();
        $response->setCallback(function () use ($contentType, $body) {
            header("Content-Type: {$contentType}");
            echo $body;
        });

        return $response;
    }

    /**
     * Get a blob from Azure, return a StreamedResponse ready to download
     *
     * @param string $key
     * @param string $downloadFileName name to download the file as
     * @throws \Exception
     * @return StreamedResponse
     */
    public function getAsDownloadResponse(string $key, string $downloadFileName): StreamedResponse
    {
        try {
            $r = $this->getBlob($key);
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw $e;
        }

        $body = stream_get_contents($r->getContentStream());

        $contentType = $r->getProperties()->getContentType();

        $response = new StreamedResponse();
        $response->setCallback(function () use ($contentType, $body, $downloadFileName) {
            header("Content-Type: {$contentType}");
            header("Content-Disposition: attachment; filename=\"{$downloadFileName}\"");
            echo $body;
        });

        return $response;
    }

    public function delete(string $key): bool
    {
        try {
            $this->blobClient->deleteBlob($this->container, $key);
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }


    #########################
    ##        CUSTOM       ##
    #########################

    // INITIALIZATION ONLY METHODS
    /**
     * Create (initialize) the Blob Container
     * @return void
     */
    public function createBlobContainer(): void
    {
        $this->blobClient->createContainer($this->container);
    }

    /**
     * Configure the Blob Container
     * @param string $acl
     * @return bool
     */
    public function setBlobContainerAcl(string $acl = self::ACL_BLOB): bool
    {
        if (! in_array($acl, [self::ACL_NONE, self::ACL_BLOB, self::ACL_CONTAINER], true)) {
            return false;
        }

        $blobAcl = new ContainerACL();
        $blobAcl->setPublicAccess($acl);

        $this->blobClient->setContainerAcl(
            $this->container,
            $blobAcl
        );

        return true;
    }

    // DEBUG METHODS
    /**
     * Create (initialize) the Blob Container
     * @return Blob[]
     */
    public function listBlobsInContainer()
    {
        $blobResults = $this->blobClient->listBlobs($this->container);

        return $blobResults->getBlobs();
    }
}
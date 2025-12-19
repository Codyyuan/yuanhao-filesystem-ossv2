<?php

namespace Yuan\FilesystemOssv2;

use AlibabaCloud\Oss\V2\Models\CopyObjectRequest;
use AlibabaCloud\Oss\V2\Models\DeleteMultipleObjectsRequest;
use AlibabaCloud\Oss\V2\Models\DeleteObject;
use AlibabaCloud\Oss\V2\Models\GetBucketAclRequest;
use AlibabaCloud\Oss\V2\Models\GetObjectAclRequest;
use AlibabaCloud\Oss\V2\Models\GetObjectMetaRequest;
use AlibabaCloud\Oss\V2\Models\ListObjectsV2Request;
use AlibabaCloud\Oss\V2\Models\PutObjectAclRequest;
use AlibabaCloud\Oss\V2\Paginator\ListObjectsV2Paginator;
use Exception;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use AlibabaCloud\Oss\V2\Client;
use AlibabaCloud\Oss\V2\Models\GetObjectRequest;
use AlibabaCloud\Oss\V2\Models\DeleteObjectRequest;
use AlibabaCloud\Oss\V2\Models\HeadObjectRequest;
use AlibabaCloud\Oss\V2\Models\PutObjectRequest;
use AlibabaCloud\Oss\V2\Utils;
use League\Flysystem\InvalidVisibilityProvided;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;

/**
 * OSS 文件系统适配器
 */
class OssAdapter implements FilesystemAdapter
{
    private Client $client;
    private string $bucket;

    /**
     * 构造函数
     * @param Client $client OSS客户端实例
     * @param string $bucket Bucket名称
     */
    public function __construct(Client $client, string $bucket)
    {
        $this->client = $client;
        $this->bucket = $bucket;
    }

    /**
     * 写入文件
     * @param string $path
     * @param string $contents
     * @param \League\Flysystem\Config $config
     * @return void
     */
    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $body = Utils::streamFor($contents);
            $request = new PutObjectRequest(bucket: $this->bucket, key: $path);
            $request->body = $body;
            //TODO:  处理可见性配置

            $this->client->putObject($request);
        } catch (\InvalidArgumentException $e) {
            // 参数错误直接抛出
            throw $e;
        } catch (Exception $e) {
            throw new UnableToWriteFile(
                message: "无法写入文件到路径: {$path}",
                previous: $e
            );
        }
    }


    /**
     * 写入文件流
     * @param string $path
     * @param resource $contents
     * @param \League\Flysystem\Config $config
     * @return void
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        // 验证资源流
        if (!is_resource($contents)) {
            throw new \InvalidArgumentException('非文件资源');
        }
        try {
            $body = Utils::streamFor($contents);
            $request = new  PutObjectRequest(bucket: $this->bucket, key: $path);
            $request->body = $body;
            //TODO:  处理可见性配置
            $this->client->putObject($request);
        } catch (\InvalidArgumentException $e) {
            // 参数错误直接抛出
            throw $e;
        } catch (\Throwable $e) {
            // 兜底异常异常并转换为Flysystem的UnableToWriteFile异常
            throw new UnableToWriteFile(
                message: "无法写入文件到路径: {$path}",
                previous: $e
            );
        }
    }

    /**
     * 读取文件内容
     * @param string $path 文件路径
     * @return string 文件内容
     */
    public function read(string $path): string
    {
        $request = new GetObjectRequest(bucket: $this->bucket, key: $path);
        try {
            $response = $this->client->getObject($request);
        } catch (\Throwable $e) {
            throw new UnableToReadFile(
                message: "无法读取文件: {$path}",
                previous: $e
            );
        }
        return $response->body->getContents();
    }

    /**
     * 读取文件流
     * @param string $path 文件路径
     * @return resource 文件流资源
     */
    public function readStream(string $path)
    {
        $request = new GetObjectRequest(bucket: $this->bucket, key: $path);
        try {
            $response = $this->client->getObject($request);
        } catch (\Throwable $e) {
            throw new UnableToReadFile(
                message: "无法读取文件: {$path}",
                previous: $e
            );
        }
        return $response->body->detach();
    }

    /**
     * 删除文件
     * @param string $path 文件路径
     * @return void
     */
    public function delete(string $path): void
    {
        try {
            $request = new DeleteObjectRequest(bucket: $this->bucket, key: $path);
            $result = $this->client->deleteObject($request);
            //TODO: 如有需要可根据结果控制返回值:statusCode HTTP状态码，例如204表示删除成功
        } catch (Exception $e) {
            throw new UnableToDeleteFile(
                message: "无法删除文件: {$path}",
                previous: $e
            );
        }
    }

    /**
     * 删除目录
     * @param string $path 目录路径
     * @return void
     */
    public function deleteDirectory(string $path): void
    {
        try {
            $normalizedPath = rtrim($path, '/') . '/';
            $continuationToken = null;
            do {
                $listRequest = new ListObjectsV2Request(
                    bucket: $this->bucket,
                    continuationToken: $continuationToken,
                    maxKeys: 1000,//单次最多1000个
                    prefix: $normalizedPath
                );
                $result = $this->client->listObjectsV2($listRequest);
                // 提取所有对象 Key
                $keys = array_map(fn($obj) => $obj->key, $result->contents ?? []);
                $objects = [];
                if (count($keys)) {
                    //生成删除对象
                    foreach ($keys as $key) {
                        $objects[] = new DeleteObject(key: $key);
                    }
                    // 批量删除
                    $deleteRequest = new DeleteMultipleObjectsRequest(bucket: $this->bucket, objects: $objects);
                    $deleteResult = $this->client->deleteMultipleObjects($deleteRequest);
                    //TODO: 有需要可返回删除结果
                }
                $continuationToken = $result->nextContinuationToken;
            } while ($result->isTruncated);
        } catch (Exception $e) {
            // OSS不存在实际的目录，所以目录删除失败不会影响系统功能
            throw new UnableToDeleteFile(message: '删除失败',
                previous: $e
            );
        }
    }

    /**
     * 创建目录
     * @param string $path 目录路径
     * @param \League\Flysystem\Config $config
     * @return void
     */
    public function createDirectory(string $path, Config $config): void
    {
        //TODO OSS不需要实际创建目录，只需要在上传文件时指定路径即可
    }

    /**
     * @param string $path
     * @param string $visibility
     * @return void
     */
    public function setVisibility(string $path, string $visibility): void
    {
        //TODO: oss可见性映射多于Flysystem
        $acl = match ($visibility) {
            'public' => 'public-read',//默认给读
            'private' => 'private',
            default => throw new InvalidVisibilityProvided(
                message: "无法设置可见性: {$path}",
                previous: new InvalidVisibilityProvided(
                    message: "不存在的acl: {$visibility}",
                )
            )
//            'public-read' => 'public-read',
//            'public-read-write' => 'public-read-write',
//            'default' => 'default',
        };
        try {
            $request = new PutObjectAclRequest(bucket: $this->bucket, key: $path, acl: $acl);
            $this->client->putObjectAcl($request);
        } catch (Exception $e) {
            throw new InvalidVisibilityProvided(
                message: "无法设置可见性: {$path}",
                previous: $e
            );
        }

    }


    /**
     * 获取文件可见性  属性
     * @param string $path 文件路径
     * @return \League\Flysystem\FileAttributes 文件属性
     */
    public function visibility(string $path): FileAttributes
    {
        /**
         * OSS的可见性通常通过ACL控制
         *public-read-write 公共读写权限
         * public-read 公共读权限
         * private 私有权限
         * default 默认：该Object遵循Bucket的读写权限，即Bucket的读写权限与Object的读写权限一致。
         * */
        try {
            $request = new GetObjectAclRequest(bucket: $this->bucket, key: $path);
            $result = $this->client->getObjectAcl($request);
            $grant = $result->accessControlList->grant;
            if ($grant === 'default') {
                $requestBucket = new GetBucketAclRequest(bucket: $this->bucket);
                $resultBucket = $this->client->getBucketAcl($requestBucket);
                $bucketGrant = $resultBucket->accessControlList->grant;
                $grant = $bucketGrant;
            }
            $visibility = match ($grant) {
                'private' => 'private',
                'public-read', 'public-read-write' => 'public'
            };
        } catch (Exception $e) {
            throw new UnableToRetrieveMetadata(
                message: "无法获取文件属性: {$path}",
                previous: $e
            );
        }
        return new FileAttributes(
            path: $path,
            visibility: $visibility,
        );
    }

    /**
     * 判断文件是否存在
     * @param string $path
     * @return bool
     */
    public function fileExists(string $path): bool
    {
        try {
            return $this->client->isObjectExist(bucket: $this->bucket, key: $path);
        } catch (Exception $e) {
            throw new UnableToCheckFileExistence(
                message: "无法判断文件是否存在: {$path}",
                previous: $e
            );
        }
    }

    /**
     * 判断目录是否存在
     * @param string $path
     * @return bool
     */
    public function directoryExists(string $path): bool
    {
        // OSS不存在实际的目录，所以只要路径不为空就认为存在
        return !empty($path);
    }

    /**
     * 获取文件MIME类型
     * @param string $path
     * @return \League\Flysystem\FileAttributes
     */
    public function mimeType(string $path): FileAttributes
    {
        try {
            $request = new HeadObjectRequest(bucket: $this->bucket, key: $path);
            $response = $this->client->headObject($request);
            $mimeType = $response->contentType;
            return new FileAttributes(path: $path, mimeType: $mimeType);
        } catch (Exception $e) {
            throw new UnableToRetrieveMetadata(
                message: "无法获取文件属性: {$path}",
                previous: $e
            );
        }
    }

    /**
     * 获取文件最后修改时间
     * @param string $path
     * @return \League\Flysystem\FileAttributes
     */
    public function lastModified(string $path): FileAttributes
    {
        try {
            $request = new HeadObjectRequest(bucket: $this->bucket, key: $path);
            $response = $this->client->headObject($request);
            $headers = $response->headers;
            $lastModified = strtotime($headers['Last-Modified'][0] ?? date('Y-m-d H:i:s'));
            return new FileAttributes($path, null, null, $lastModified);
        } catch (Exception $e) {
            throw new UnableToRetrieveMetadata(
                message: "无法获取文件属性: {$path}",
                previous: $e
            );
        }
    }

    /**
     * 获取文件大小
     * @param string $path
     * @return \League\Flysystem\FileAttributes
     */
    public function fileSize(string $path): FileAttributes
    {
        try {
            $request = new GetObjectMetaRequest(bucket: $this->bucket, key: $path);
            $response = $this->client->getObjectMeta($request);
            $headers = $response->headers;
            $size = (int)$headers['Content-Length'];
            return new FileAttributes(path: $path, fileSize: $size);
        } catch (Exception $e) {
            throw new UnableToRetrieveMetadata(
                message: "无法获取文件属性: {$path}",
                previous: $e
            );
        }
    }

    /**
     * 获取文件列表
     * @param string $path
     * @param bool $deep 是否递归
     * @return iterable  泛指任何类型，这里返回的是FileAttributes对象
     */
    public function listContents(string $path, bool $deep): iterable
    {
        //TODO: 暂未适配获取目录列表
        try {
            $normalizedPath = rtrim($path, '/') . '/';
            $contents = [];
            // 使用V2 API的ListObjectsV2Paginator高效分页遍历所有对象
            $paginator = new ListObjectsV2Paginator(client: $this->client);
            //delimiter 是阿里云 OSS（对象存储服务）中一个重要的参数，用于控制列举对象（文件）时的分组方式 相当于阻止默认递归只拿当前文件夹下的文件
            if ($path === '') {
                if ($deep) {
                    $iter = $paginator->iterPage(request: new ListObjectsV2Request(
                        bucket: $this->bucket,
                    ));
                } else {
                    $iter = $paginator->iterPage(request: new ListObjectsV2Request(
                        bucket: $this->bucket,
                        delimiter: '/',
                    ));
                }
            } else {
                if ($deep) {
                    $iter = $paginator->iterPage(request: new ListObjectsV2Request(
                        bucket: $this->bucket,
                        prefix: $normalizedPath,
                    ));
                } else {
                    $iter = $paginator->iterPage(request: new ListObjectsV2Request(
                        bucket: $this->bucket,
                        delimiter: '/',
                        prefix: $normalizedPath
                    ));
                }
            }
            foreach ($iter as $page) {
                foreach ($page->contents ?? [] as $object) {
                    //新方式：返回FileAttributes对象
                    $contents[] = new FileAttributes(
                        path: $object->key, // 对象键（文件路径）
                        fileSize: (int)$object->size, //  文件大小（字节）
                        visibility: 'public', // 可见性
                        lastModified: $object->lastModified->getTimestamp(), // 最后修改时间戳
                        mimeType: $object->type, // MIME类型
                        extraMetadata: ['etag' => $object->etag] // 额外元数据
                    );
                }
            }
            return $contents;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * 移动文件
     * @param string $source
     * @param string $destination
     * @param \League\Flysystem\Config $config
     * @return void
     * @throws \Exception
     */
    public function move(string $source, string $destination, Config $config): void
    {
        try {
            // 先复制文件
            $this->copy($source, $destination, $config);
            // 再删除源文件
            $this->delete($source);
        } catch (Exception $e) {
            // 如果复制或删除失败，直接抛出异常
            throw new UnableToMoveFile('文件处理失败');
        }
    }

    /**
     * @param string $source
     * @param string $destination
     * @param \League\Flysystem\Config $config
     * @return void
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            // 使用OSS的CopyObject API直接复制文件
            $copyRequest = new CopyObjectRequest(
                bucket: $this->bucket,
                key: $destination,
                sourceBucket: $this->bucket,
                sourceKey: $source
            );
            $this->client->copyObject($copyRequest);
        } catch (Exception $e) {
            throw new UnableToCopyFile(
                message: '文件处理失败',
                previous: $e
            );
        }
    }


}
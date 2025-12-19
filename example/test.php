<?php

/**
 * 整合上传配置和OssAdapter使用的完整示例
 * 本示例展示了如何加载配置文件、初始化OssAdapter并进行文件上传操作
 */

// 自动加载类（如果使用Composer）
require_once __DIR__ . '/../vendor/autoload.php';

use Yuan\FilesystemOssv2\OssAdapter;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

// 1. 加载上传配置
$uploadConfig = require_once __DIR__ . '/upload.php';

// 2. 获取默认驱动和配置
$defaultDriver = $uploadConfig['default'];
$driverConfig = $uploadConfig['drivers'][$defaultDriver];

// 3. 验证配置是否完整
if (empty($driverConfig)) {
    die("错误：未找到驱动配置 '{$defaultDriver}'");
}

// 4. 根据驱动类型初始化文件系统
echo "正在初始化 {$defaultDriver} 文件系统...\n";

$filesystem = null;

switch ($defaultDriver) {
    case 'oss':
        // 验证OSS配置是否完整
        $requiredConfig = ['accessKeyId', 'accessKeySecret', 'endpoint', 'bucket', 'region'];
        foreach ($requiredConfig as $key) {
            if (empty($driverConfig[$key])) {
                die("错误：OSS配置缺少必要参数 '{$key}'");
            }
        }

        $accessKeyId = $driverConfig['accessKeyId'];
        $accessKeySecret = $driverConfig['accessKeySecret'];
        $region = $driverConfig['region'];
        $bucket = $driverConfig['bucket'];
        // 创建凭证提供者
        $credentialsProvider = new \AlibabaCloud\Oss\V2\Credentials\StaticCredentialsProvider($accessKeyId, $accessKeySecret);
        // 初始化OSS客户端
        $ossConfig = \AlibabaCloud\Oss\V2\Config::loadDefault();
        $ossConfig->setCredentialsProvider($credentialsProvider);
        $ossConfig->setRegion($region);
        if (isset($driverConfig['endpoint'])) {
            $ossConfig->setEndpoint($driverConfig['endpoint']);
        }
        // 创建OSS客户端实例
        $client = new \AlibabaCloud\Oss\V2\Client($ossConfig);
        // 创建自定义OSS适配器
        $adapter = new OssAdapter($client, $driverConfig['bucket']);
        $filesystem = new Filesystem($adapter);
        echo "OSS文件系统初始化成功\n";
        break;

    case 'local':
        // 初始化本地文件系统
        $adapter = new LocalFilesystemAdapter($driverConfig['root']);
        $filesystem = new Filesystem($adapter);
        echo "本地文件系统初始化成功\n";
        break;

    default:
        die("错误：不支持的驱动类型 '{$defaultDriver}'");
}

// 5. 示例：上传文件

echo "\n===== 文件上传示例 =====\n";

// 准备一个测试文件
$testFilePath = __DIR__ . '/test_upload.txt';
$testFileContent = '这是一个用于测试文件上传的示例内容';

// 创建测试文件
file_put_contents($testFilePath, $testFileContent);
echo "创建测试文件: {$testFilePath}\n";

// 6. 验证文件类型配置
$fileType = 'document'; // 可以是：image, video, audio, document, attachment
$typeConfig = $uploadConfig['file_types'][$fileType] ?? null;

if (!$typeConfig) {
    die("错误：不支持的文件类型 '{$fileType}'");
}

echo "使用文件类型配置: {$fileType}\n";
echo "允许的扩展名: " . implode(', ', $typeConfig['allowed_extensions']) . "\n";
echo "最大文件大小: " . round($typeConfig['max_size'] / 1024 / 1024, 2) . " MB\n";

// 7. 生成存储路径
$extension = pathinfo($testFilePath, PATHINFO_EXTENSION);
$path = $typeConfig['path'];
$filename = date('YmdHis') . '_' . uniqid() . '.' . $extension;
$fullPath = date('Ymd') . '/' . $path . '/' . $filename;

echo "生成存储路径: {$fullPath}\n";

// 8. 验证文件大小和扩展名
if (!in_array($extension, $typeConfig['allowed_extensions'])) {
    die("错误：文件扩展名 '{$extension}' 不被允许");
}

$fileSize = filesize($testFilePath);
if ($fileSize > $typeConfig['max_size']) {
    die("错误：文件大小 (" . round($fileSize / 1024, 2) . " KB) 超出限制");
}

echo "文件验证通过: {$fileSize} 字节\n";

// 9. 上传文件

try {
    $stream = fopen($testFilePath, 'r');
    if (!$stream) {
        die("错误：无法打开文件流");
    }

    echo "正在上传文件...\n";
    try {
        $filesystem->writeStream($fullPath, $stream);
    } catch (\League\Flysystem\FilesystemException $e) {
        die("错误：文件上传失败: {$e->getMessage()}");
    }

    // 关闭流
    if (is_resource($stream)) {
        fclose($stream);
    }

    echo "✅ 文件上传成功！\n";
    echo "存储路径: {$fullPath}\n";

    // 10. 获取文件URL（如果使用OssAdapter）
    if ($defaultDriver === 'oss' && method_exists($adapter, 'getFileUrl')) {
        $fileUrl = $adapter->getFileUrl($fullPath);
        echo "文件URL: {$fileUrl}\n";
    } elseif ($defaultDriver === 'local') {
        $fileUrl = $driverConfig['url'] . '/' . $fullPath;
        echo "文件URL: {$fileUrl}\n";
    }

    // 11. 读取文件内容（验证上传成功）
    try {
        $content = $filesystem->read($fullPath);
    } catch (\League\Flysystem\FilesystemException $e) {
        die("错误：无法读取文件内容");
    }
    echo "✅ 文件内容验证成功\n";
    echo "文件内容: {$content}\n";

    // 12. 清理测试文件
    unlink($testFilePath);
    echo "清理本地测试文件成功\n";

    // 13. （可选）删除上传的文件（用于测试）
    // $filesystem->delete($fullPath);
    // echo "✅ 已删除上传的测试文件\n";

} catch (Exception $e) {
    echo "❌ 文件上传失败: {$e->getMessage()}\n";
    if (isset($stream) && is_resource($stream)) {
        fclose($stream);
    }
    if (file_exists($testFilePath)) {
        unlink($testFilePath);
    }
    exit(1);
}

echo "\n===== 示例完成 =====\n";
echo "您可以根据需要修改本示例，将其集成到您的项目中\n";
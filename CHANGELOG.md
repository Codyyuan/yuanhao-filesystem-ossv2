# Changelog

所有对该项目的显著更改都将记录在此文件中。

## [1.0.0] - 2025-12-18

### Added
- 首次发布
- 实现了 Flysystem `FilesystemAdapter` 接口的所有必要方法
- 支持阿里云 OSS v2 SDK
- 支持文件上传、下载、删除等基本操作
- 支持文件流操作
- 支持文件可见性管理
- 支持目录操作
- 支持文件元数据获取
- 提供完整的错误处理机制

### 主要功能
- `write()` - 写入文件内容
- `writeStream()` - 写入文件流
- `read()` - 读取文件内容
- `readStream()` - 读取文件流
- `delete()` - 删除文件
- `deleteDirectory()` - 删除目录
- `createDirectory()` - 创建目录
- `setVisibility()` - 设置文件可见性
- `visibility()` - 获取文件可见性
- `fileExists()` - 判断文件是否存在
- `directoryExists()` - 判断目录是否存在
- `mimeType()` - 获取文件MIME类型
- `lastModified()` - 获取文件最后修改时间
- `fileSize()` - 获取文件大小
- `listContents()` - 列出目录内容
- `move()` - 移动文件
- `copy()` - 复制文件
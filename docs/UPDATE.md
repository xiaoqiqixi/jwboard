# JWBoard 更新教程

本文件只说明已部署 JWBoard 的更新流程。首次安装请按仓库根目录 `readme.md` 操作。

## 更新前必须确认

1. 当前版本：

   ```bash
   cd /www/wwwroot/jwboard
   cat VERSION
   ```

2. 备份数据库、`.env`、`storage/` 与 `config/theme/`。
3. 使用 PHP 7.4；不要使用系统默认的其他 PHP 版本。
4. 确认服务器可访问 `api.github.com`、`github.com` 和 Composer 镜像或官网。

## 标准更新

```bash
cd /www/wwwroot/jwboard
curl -fL https://github.com/xiaoqiqixi/jwboard/releases/latest/download/update.sh -o update.sh
chmod +x update.sh
PHP_BIN=/www/server/php/74/bin/php ./update.sh
```

脚本会自动完成以下动作：

1. 校验 PHP 7.4、`.env`、网络下载工具和压缩工具；
2. 查询 GitHub 的最新正式 Release，并比较本地版本；
3. 下载 Release 源码包，保留 `.env`、`storage/`、`config/theme/`、`vendor/` 与 Laravel 缓存目录；
4. 按 Release 内 `composer.lock` 执行 `composer install`，不会改变已锁定依赖版本；
5. 执行 `jwboard:update`，读取数据库版本记录并按顺序执行缺失的版本迁移；
6. 刷新配置缓存并通知 Horizon 重启。

脚本不依赖 Git 仓库、不会执行 `git reset --hard`、不会删除 `.env`、不会执行 `composer update`。

不同旧版本不需要不同脚本。数据库中的 `v2_jwboard_update_log` 会记录每一项成功迁移：1.0.0 升级到 1.0.2 会依次执行 `1.0.1.sql`、`1.0.2.sql`；1.0.1 升级到 1.0.2 只执行 `1.0.2.sql`。SQL 文件位于 `database/jwboard-updates/`，成功后才会记录，失败时修复问题后重跑同一个脚本即可。

## 更新后检查

1. aaPanel 重启 PHP 7.4 的 PHP-FPM，启用 OPCache 时必须执行。
2. 检查当前版本：

   ```bash
   cat VERSION
   /www/server/php/74/bin/php artisan horizon:status
   ```

3. 访问首页、登录页和后台，使用测试账号验证订阅与订单。
4. 若本次版本涉及节点变更，使用测试 V2bX 节点验证用户拉取与流量回传。

## 常见停止原因

### 无法下载 Release

确认服务器可访问 `api.github.com` 和 `github.com`，并确认该 Release 已上传 `update.sh` 资产。该脚本不会回退到未发布的 `main` 分支。

### Composer 不可用

脚本会自动下载 `composer.phar`；如果服务器无法访问 Composer 官网，请预先将 `composer.phar` 放到项目根目录后重试。

### Telegram 唯一索引创建失败

先查询并处理重复绑定，再重试：

```sql
SELECT telegram_id, COUNT(*) AS count
FROM v2_user
WHERE telegram_id IS NOT NULL
GROUP BY telegram_id
HAVING COUNT(*) > 1;
```

## 发布新版本（维护者）

假设下一个版本为 `JWBoard 1.0.2`：

1. 修改 `VERSION` 为 `JWBoard 1.0.2`。
2. 在 `CHANGELOG.md` 写明功能、修复、数据库与部署影响。
3. 将数据库增量 SQL 写入 `database/jwboard-updates/1.0.2.sql`；只写本版本新增的数据库修改，不能写全量建表 SQL。
4. 在测试环境完整执行 `./init.sh` 与从 Release 下载的 `update.sh`。
5. 提交 `main` 后创建并推送版本标签，再创建 GitHub Release：

   ```bash
   git tag -a jwboard1.0.2 -m "JWBoard 1.0.2"
   git push origin main
   git push origin jwboard1.0.2
   gh release create jwboard1.0.2 --title "JWBoard 1.0.2" --notes-file CHANGELOG.md update.sh#update.sh
   ```

Release 正文必须写明新增、修复、数据库影响和部署注意事项。生产服务器继续运行 Release 下载的 `./update.sh`；它会显示更新前后的版本号。

## 内部兼容项

`config('v2board.*')` 与 `config/v2board.php` 是为既有部署保留的内部配置键，不是对外品牌。不要为了改名而删除或改名它们。

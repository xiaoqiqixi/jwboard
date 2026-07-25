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
4. `composer.lock` 存在。它由首次 `init.sh` 生成，不能删除。
5. 没有未提交的源代码改动：

   ```bash
   git status --short
   ```

   没有输出才可继续。若你修改过代码，先提交到自己的分支或完成备份。

## 标准更新：逐行执行

```bash
cd /www/wwwroot/jwboard
PHP_BIN=/www/server/php/74/bin/php ./update.sh
```

脚本会自动完成以下动作：

1. 校验 PHP 7.4、Git、`.env` 与 `composer.lock`；
2. 从 GitHub 的 `origin/main` 拉取仅可快进的代码；
3. 按 `composer.lock` 执行 `composer install`，不会改变已锁定依赖版本；
4. 执行 `jwboard:update`，处理历史数据库更新、VLESS 表和 Telegram 登录索引；
5. 刷新配置缓存并通知 Horizon 重启。

脚本不会执行 `git reset --hard`、不会删除 `.env`、不会删除 `composer.lock`、不会执行 `composer update`。

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

### `composer.lock is missing`

说明该服务器没有完成首次安装，或锁文件被删除。不要执行 `composer update`。恢复首次安装生成的 `composer.lock` 后重试；无备份时先在测试环境用 PHP 7.4 重新执行依赖安装并验证，再把生成的锁文件带回生产环境。

### `The working tree has local changes`

说明服务器内改过代码。先执行 `git status --short` 找到文件；将本地修改提交到独立分支，或备份后恢复为由 Git 管理的版本，再更新。不要通过删除 `.env` 或 `git reset --hard` 解决。

### `Local history has diverged`

说明服务器分支与 GitHub 主分支各自都有提交。保留当前目录和数据库备份，创建新目录重新 clone 当前版本，在测试环境确认后再切换；不要强制覆盖生产代码。

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

假设下一个版本为 `JWBoard 1.0.1`：

1. 修改 `VERSION` 为 `JWBoard 1.0.1`。
2. 在 `CHANGELOG.md` 写明功能、修复、数据库与部署影响。
3. 将需要自动升级的数据库改动加入 `jwboard:update`，确保重复执行安全。
4. 在测试环境完整执行 `./init.sh` 与 `./update.sh`。
5. 提交 `main` 后创建并推送版本标签：

   ```bash
   git tag -a jwboard1.0.1 -m "JWBoard 1.0.1"
   git push origin main --tags
   ```

生产服务器继续运行 `./update.sh`；它会从当前分支拉取最新版本，并在结束时显示更新前后的版本号。

## 内部兼容项

`config('v2board.*')` 与 `config/v2board.php` 是为既有部署保留的内部配置键，不是对外品牌。不要为了改名而删除或改名它们。

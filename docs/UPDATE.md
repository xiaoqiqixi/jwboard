# JWBoard Git 更新手册

## 更新前

1. 备份数据库与站点的 `.env`、`storage/`、`config/theme/`。
2. 确认当前代码没有未提交修改：`git status --short` 应为空。自行开发的改动请先提交到单独分支，或先完成备份。
3. 确认系统仍使用 PHP 7.4；JWBoard 保持与 MySQL 5.6 兼容。
4. 确认仓库中有已提交的 `composer.lock`。锁文件缺失时，脚本会拒绝更新，以免服务器重新解析依赖版本。

## 正常更新

```bash
cd /www/wwwroot/your-site
PHP_BIN=/www/server/php/74/bin/php ./update.sh
```

脚本会从 `origin` 当前分支快进更新、按 `composer.lock` 安装依赖、执行 `jwboard:update`，并刷新 Laravel 配置缓存与 Horizon。它不会：

- `git reset --hard` 或删除你的 `.env`；
- 删除 `composer.lock`；
- 执行会改变依赖版本的 `composer update`；
- 递归修改整个项目的文件所有者。

如果远程分支不是默认分支，可指定：

```bash
JWBOARD_BRANCH=main PHP_BIN=/www/server/php/74/bin/php ./update.sh
```

如果 Git remote 名称不是 `origin`，同时指定 `JWBOARD_REMOTE`。

## 数据库更新内容

`jwboard:update` 会保留历史升级 SQL 的兼容行为，并额外确保：

- VLESS 节点表 `v2_server_vless` 存在；
- `v2_user.telegram_id` 存在且有唯一索引，供 Telegram 一键登录使用。

若数据库中已有重复的非空 `telegram_id`，唯一索引无法创建。此时先查询并处理重复数据，再重试：

```sql
SELECT telegram_id, COUNT(*) AS count
FROM v2_user
WHERE telegram_id IS NOT NULL
GROUP BY telegram_id
HAVING COUNT(*) > 1;
```

## 更新后

在 aaPanel 重启 PHP 7.4 的 PHP-FPM 服务（尤其启用了 OPCache 时），然后访问站点和后台确认服务正常。队列由 Horizon 管理，确保 aaPanel 的守护进程或 Supervisor 仍在运行。

> `config('v2board.*')` 与 `config/v2board.php` 是为既有部署保留的内部配置键；它们不是对外品牌。请不要为改名而删除或改名这两个内部兼容项。

# JWBoard

当前正式版本：`JWBoard 1.0.2`（发布标签：`jwboard1.0.2`；首发版本标签为 `jwboard1.0`）

JWBoard 是面向商业化运营的代理服务管理面板，包含 Nova 前台、VLESS、Cloudflare Turnstile、Telegram 一键注册/登录与 V2bX 适配。

## 环境要求与安全注意事项

- 固定使用 **PHP 7.4**，不要升级 PHP 8。
- 支持 **MySQL 5.6+**，无需升级数据库版本。
- 需要 Redis、Nginx、Git、Composer 2、Supervisor；aaPanel 可直接安装。
- PHP 7.4 扩展：`redis`、`fileinfo`、`pdo_mysql`、`curl`、`mbstring`、`openssl`、`opcache`。
- PHP 7.4 禁用函数中必须放行：`proc_open`、`pcntl_alarm`、`pcntl_signal`、`putenv`。

不要公开 Redis、MySQL、`.env`、`SERVER_TOKEN`、Reality 私钥或用户订阅链接。

## 安装

### aaPanel 环境

在 aaPanel 安装 Nginx、MySQL 5.6、PHP 7.4、Redis、Supervisor。创建站点与独立数据库用户，申请 HTTPS 证书。

站点的运行目录必须设为：

```text
/www/wwwroot/jwboard/public
```

不要把项目根目录设为网站运行目录。

### 获取并初始化项目

在服务器项目目录执行：

```bash
cd /www/wwwroot
git clone https://github.com/xiaoqiqixi/jwboard.git
cd jwboard
cat VERSION
PHP_BIN=/www/server/php/74/bin/php ./init.sh
```

如需安装首发 `JWBoard 1.0.0`，才执行 `git checkout jwboard1.0`；正常新安装始终使用 `main` 的当前正式版本。

首次安装前不要创建 `.env`，也不要手工执行 `composer update`。`init.sh` 会：

1. 检查 PHP 是否为 7.4；
2. 创建 Laravel 所需的缓存、日志和主题目录；
3. 用 `composer install` 安装依赖；
4. 询问数据库地址、数据库名、账号、密码与管理员邮箱；
5. 创建 `.env`、导入数据库并生成管理员账号。

安装完成后记下终端显示的管理员密码；首次安装在服务器本地生成的 `composer.lock` 必须保留，之后更新不能删除它。

### Nginx 配置

aaPanel → 网站 → 目标站点 → URL 重写，填写：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ^~ /theme/nova/assets/ {
    expires 7d;
    add_header Cache-Control "public, immutable";
    access_log off;
}
```

在站点设置中开启强制 HTTPS。

### 队列与定时任务

aaPanel → Cron → Shell Script，设置为每分钟执行一次：

```bash
cd /www/wwwroot/jwboard && /www/server/php/74/bin/php artisan schedule:run >> /dev/null 2>&1
```

aaPanel → Supervisor → 添加守护进程：

```text
名称：jwboard-horizon
运行用户：www
运行目录：/www/wwwroot/jwboard
启动命令：/www/server/php/74/bin/php artisan horizon
进程数：1
自动启动：开启
```

没有 Horizon 或每分钟定时任务时，订单处理、VLESS 流量统计和到期处理会延迟。

## 常用功能启用

### Cloudflare Turnstile

在 Cloudflare 创建 Turnstile Managed Widget 后，将以下内容写入 `.env`，再运行配置缓存命令：

```dotenv
TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=你的SiteKey
TURNSTILE_SECRET_KEY=你的SecretKey
```

```bash
PHP_BIN=/www/server/php/74/bin/php
$PHP_BIN artisan config:clear
$PHP_BIN artisan config:cache
```

### Telegram 一键注册/登录

1. 在 BotFather 创建机器人；
2. BotFather 执行 `/setdomain`，设置你的前台 HTTPS 域名；
3. 管理后台 → Telegram 设置，启用机器人并填入 Bot Token；
4. 已安装站点运行 `./update.sh`，自动建立 Telegram 登录所需索引。

邀请码地址可使用：

```text
https://你的域名/#/register?code=邀请码
```

### VLESS 与 V2bX

后台 `#/vless` 可以新增独立 VLESS 节点，不影响现有 VMess。节点端 V2bX 的 `NodeType` 必须是 `vless`，`NodeID` 必须填 VLESS 节点 ID，`ApiKey` 使用 `.env` 的全局 `SERVER_TOKEN`。

VLESS、Reality、V2bX、Telegram 的完整字段说明分别位于仓库文件：`docs/VLESS.md`、`docs/V2BX_VLESS.md`、`docs/TELEGRAM_LOGIN.md`。

## 更新

首次安装完成并正常运行后，不要重新 clone、不要覆盖 `.env`。每次更新按下面顺序操作。

### 更新前检查与备份

```bash
cd /www/wwwroot/jwboard
cat VERSION
git status --short
```

`git status --short` 没有输出才可以继续。然后备份数据库、`.env`、`storage/` 与 `config/theme/`。确认本地存在 `composer.lock`；该文件由首次 `init.sh` 生成，不能删除。

### 执行更新

```bash
cd /www/wwwroot/jwboard
PHP_BIN=/www/server/php/74/bin/php ./update.sh
```

更新脚本会检查 PHP 7.4、当前代码是否有本地修改和 `composer.lock`，然后快进拉取 GitHub 的 `main`、执行 `composer install`、数据库版本更新、配置缓存刷新和 Horizon 重启。它不会执行 `git reset --hard`、不会修改 `.env`、不会执行 `composer update`。

### 版本迁移规则

所有用户都运行同一个 `./update.sh`，不需要自己挑选脚本。脚本会读取数据库表 `v2_jwboard_update_log`，只执行缺少的版本迁移：

| 已部署版本 | 目标版本 | 实际执行的数据库更新 |
| --- | --- | --- |
| 1.0.0 | 1.0.2 | 依次执行 `1.0.1.sql`、`1.0.2.sql` |
| 1.0.1 | 1.0.2 | 只执行 `1.0.2.sql` |
| 1.0.2 | 1.0.2 | 不重复执行数据库 SQL，只刷新依赖与服务 |

每个版本的 SQL 位于 `database/jwboard-updates/`，更新成功后才写入迁移记录；因此同一个数据库变更不会重复运行。版本发布记录见 `CHANGELOG.md`，代码目标版本见 `VERSION`。

### 更新后检查

在 aaPanel 重启 PHP 7.4 的 PHP-FPM（启用 OPCache 时必须重启），再执行：

```bash
cat VERSION
/www/server/php/74/bin/php artisan horizon:status
```

最后检查首页、后台、登录、订阅、订单与测试节点流量。若更新涉及 VLESS 或 Telegram，用测试用户完成节点拉取、流量回传和 Telegram 登录验证。

### 常见停止原因

- `composer.lock is missing`：服务器未完成首次安装或锁文件被删除。不要运行 `composer update`；恢复首次安装生成的锁文件后重试。
- `The working tree has local changes`：服务器上改过源代码。先备份并提交自己的改动，不能强制覆盖。
- `Local history has diverged`：服务器分支与 GitHub 都有各自提交。保留备份，在新目录 clone 后先测试，不要直接强制更新。
- 数据库迁移失败：查看终端报错；该迁移不会写入完成记录。处理后再次运行同一个 `./update.sh` 即可。

更新教程的独立副本位于 `docs/UPDATE.md`，方便只分发运维文档；README 与该文件遵循相同的更新规则。

## 上线检查

- `.env` 中设置 `APP_ENV=production`、`APP_DEBUG=false`、正确的 HTTPS `APP_URL`。
- 使用测试账号完成注册、登录、找回密码、下单、支付回调、订阅重置、邀请码和工单测试。
- 确认 `php artisan horizon:status` 正常，Redis 未监听公网。
- VLESS 先以测试节点验证用户同步、流量回传、到期禁用与客户端导入。
- 每次升级前备份数据库、`.env`、`storage/`、`config/theme/`。

## 版本规则

JWBoard 使用 `主版本.次版本.修订版本`：

- `JWBoard 1.0.0`：首个正式商业化发布；
- 新增兼容功能时升级次版本，例如 `1.1.0`；
- 修复问题时升级修订版本，例如 `1.0.1`；
- 破坏现有部署兼容性时才升级主版本，例如 `2.0.0`。

每次发布必须同步更新 `VERSION`、`CHANGELOG.md`、必要的数据库升级 SQL，并创建对应 Git 标签，例如 `jwboard1.0.2`。推送标签时只推送当前标签，例如 `git push origin jwboard1.0.2`，不要使用 `git push --tags`。

## 许可

本项目保留上游 V2Board 的 MIT 许可证与版权声明；JWBoard 的新增功能与品牌变更同样遵循 MIT 许可证。

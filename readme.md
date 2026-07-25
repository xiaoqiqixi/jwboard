# JWBoard

当前正式版本：`JWBoard 1.0.0`（发布标签：`jwboard1.0`）

JWBoard 是面向商业化运营的代理服务管理面板，包含 Nova 前台、VLESS、Cloudflare Turnstile、Telegram 一键注册/登录与 V2bX 适配。

## 先看这里

- 固定使用 **PHP 7.4**，不要升级 PHP 8。
- 支持 **MySQL 5.6+**，无需升级数据库版本。
- 需要 Redis、Nginx、Git、Composer 2、Supervisor；aaPanel 可直接安装。
- PHP 7.4 扩展：`redis`、`fileinfo`、`pdo_mysql`、`curl`、`mbstring`、`openssl`、`opcache`。
- PHP 7.4 禁用函数中必须放行：`proc_open`、`pcntl_alarm`、`pcntl_signal`、`putenv`。

不要公开 Redis、MySQL、`.env`、`SERVER_TOKEN`、Reality 私钥或用户订阅链接。

## 第一次安装：手把手操作

### 1. aaPanel 准备环境

在 aaPanel 安装 Nginx、MySQL 5.6、PHP 7.4、Redis、Supervisor。创建站点与独立数据库用户，申请 HTTPS 证书。

站点的运行目录必须设为：

```text
/www/wwwroot/jwboard/public
```

不要把项目根目录设为网站运行目录。

### 2. 从 GitHub 获取 JWBoard 1.0

使用 SSH 登录服务器，逐行执行：

```bash
cd /www/wwwroot
git clone https://github.com/xiaoqiqixi/jwboard.git
cd jwboard
git checkout jwboard1.0
PHP_BIN=/www/server/php/74/bin/php ./init.sh
```

首次安装前不要创建 `.env`，也不要手工执行 `composer update`。`init.sh` 会：

1. 检查 PHP 是否为 7.4；
2. 创建 Laravel 所需的缓存、日志和主题目录；
3. 用 `composer install` 安装依赖；
4. 询问数据库地址、数据库名、账号、密码与管理员邮箱；
5. 创建 `.env`、导入数据库并生成管理员账号。

安装完成后记下终端显示的管理员密码；首次安装在服务器本地生成的 `composer.lock` 必须保留，之后更新不能删除它。

### 3. 配置 Nginx

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

### 4. 配置队列与定时任务

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

## 后续更新：只运行更新脚本

首次安装完成并正常运行后，以后不要重新 clone、不要覆盖 `.env`，只执行：

```bash
cd /www/wwwroot/jwboard
PHP_BIN=/www/server/php/74/bin/php ./update.sh
```

更新脚本会检查 PHP 7.4、当前代码是否有本地修改和 `composer.lock`，然后快进拉取 GitHub 的 `main`、执行 `composer install`、数据库兼容更新、配置缓存刷新和 Horizon 重启。它不会执行 `git reset --hard`、不会修改 `.env`、不会执行 `composer update`。

完整的排错、备份、版本升级和发布规则位于 `docs/UPDATE.md`；每个版本的内容记录在 `CHANGELOG.md`，当前版本文件为 `VERSION`。

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

每次发布必须同步更新 `VERSION`、`CHANGELOG.md`、必要的数据库升级逻辑，并创建对应 Git 标签，例如 `jwboard1.0.1`。

## 许可

本项目保留上游 V2Board 的 MIT 许可证与版权声明；JWBoard 的新增功能与品牌变更同样遵循 MIT 许可证。

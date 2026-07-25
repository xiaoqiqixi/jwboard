# JWBoard Nova 主题：aaPanel 部署手册

这份手册基于上游 aaPanel 指引更新，适用于本包内的 `nova` 前台主题。Nova 不需要 Node.js 构建；主题是已交付的静态文件，由 PHP 直接提供。

## 0. 部署前要求

- 仅在你有权运营的网络和业务范围内使用。
- 一台已安装 aaPanel 的 Linux 服务器、已解析到服务器的域名，以及可用的 HTTPS 证书。
- MySQL、Redis、Nginx、Supervisor。
- **PHP 7.4**：与原版 aaPanel 教程保持一致；Nova 主题和 Cloudflare Turnstile 改动不要求升级 PHP。
- 使用独立的 MySQL 数据库账号，禁止把 Redis、MySQL 直接暴露到公网。

> 本仓库上游较旧。首次正式运营前，请先完成依赖锁定和安全加固；特别是节点令牌、CORS、JWT 有效期和限流。本文的上线验证清单不能替代安全审计。

## 1. 在 aaPanel 安装运行环境

在 **App Store** 安装并启动：

- Nginx 1.17
- MySQL 5.6（与原版 aaPanel 教程保持一致）
- PHP 7.4，并在 PHP 扩展中安装 `redis`、`fileinfo`、`opcache`、`pdo_mysql`、`curl`、`mbstring`、`openssl`
- Redis
- Supervisor

在 PHP 7.4 的 *Disabled Functions* 中确认 `proc_open`、`pcntl_alarm`、`pcntl_signal`、`putenv` 没有被禁用；Horizon 需要这些能力。不要为了“能跑”而关闭 `open_basedir` 或给站点目录 777 权限。

## 2. 创建站点与数据库

1. aaPanel → **Website** → **Add site**，填写业务域名；PHP 版本选 PHP 7.4。
2. 创建数据库及**专用数据库用户**，记录数据库名、用户名和强密码。
3. 申请并启用 SSL 证书，站点设置中开启强制 HTTPS。
4. 将站点运行目录改为 `public`，例如：

   ```text
   /www/wwwroot/example.com/public
   ```

## 3. 上传本包并安装依赖

本包已经包含 Nova 主题。压缩包内文件位于项目根目录，**没有额外的顶层目录**。用 aaPanel 文件管理器上传 `jwboard-nova.zip` 到站点目录，或通过 SSH 上传；然后执行：

```bash
mkdir -p /www/wwwroot/example.com
cd /www/wwwroot/example.com
unzip /path/to/jwboard-nova.zip
```

如果目录已经由 aaPanel 创建，先确认其中没有业务文件再移动内容；不要照抄原教程的无差别 `rm -rf`。

安装 Composer 依赖前，先确认 `composer` 指向 PHP 7.4：

```bash
php -v
composer --version
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate --force
```

本包已将 `firebase/php-jwt` 固定在兼容 PHP 7.4 的 5.x 系列；不要执行无目的的 `composer update`，首次和后续部署均使用上面的 `composer install`。

也可以直接运行本包调整后的初始化脚本。它会拒绝非 PHP 7.4、只运行 `composer install`，且不会再对整个站点执行 `chown -R`：

```bash
PHP_BIN=/www/server/php/74/bin/php bash init.sh
```

脚本会在 Composer 执行前自动创建 `bootstrap/cache` 与 `storage` 的运行时目录并赋予 aaPanel 的 `www` 用户权限；若你手动执行 Composer，也请先执行：

```bash
mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs config/theme
chown -R www:www storage bootstrap/cache config/theme
chmod -R ug+rwX storage bootstrap/cache config/theme
```

首次成功安装后会生成 `composer.lock`。请将它保存到你的私有代码仓库；后续上线只执行 `composer install`，避免依赖版本漂移。

编辑 `.env`。至少配置以下值，所有密码均应使用随机强密码：

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=你的数据库名
DB_USERNAME=专用数据库用户
DB_PASSWORD=强数据库密码

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=Redis密码
```

## Cloudflare Turnstile（替代 Google reCAPTCHA）

本包已移除 Google reCAPTCHA 的 Composer 依赖和服务端验证，改为 Cloudflare Turnstile。请在 Cloudflare Dashboard → **Turnstile** → **Add site** 中创建一个 Widget：

1. Widget type 选 **Managed**；在域名列表中填入你的实际前台域名。
2. 将生成的 Site Key 与 Secret Key 写进 `.env`：

   ```dotenv
   TURNSTILE_ENABLED=true
   TURNSTILE_SITE_KEY=你的SiteKey
   TURNSTILE_SECRET_KEY=你的SecretKey
   ```

3. 重新生成配置缓存：

   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

启用后，Nova 的注册、发送邮箱验证码、找回密码会显示 Turnstile；后端会向 Cloudflare 的 Siteverify API 校验一次性 token。未配置密钥时保持 `TURNSTILE_ENABLED=false`，前台不会加载外部验证脚本。

> Turnstile 密钥由 `.env` 管理，不在旧版管理后台保存；这样不会把 Secret Key 写入数据库或返回给后台 API。

导入架构并初始化：

```bash
mysql -u 数据库用户 -p 数据库名 < database/install.sql
php artisan config:clear
php artisan config:cache
php artisan jwboard:install
```

`jwboard:install` 的交互项会要求继续填写站点与管理账户信息。安装完成后，确认 `.env` 不在 `public` 目录内、且不能由 Web 直接读取。

## 4. 启用 Nova 主题

登录原始管理后台后：

1. 进入 **系统设置 → 前端**。
2. 将“前端主题”设置为 `nova` 并保存。
3. 主题首次访问时会生成 `config/theme/nova.php`；确认 Web 用户对 `config/theme` 有写权限。
4. 再访问站点首页，检查登录、注册、套餐、订单、订阅、邀请、工单、知识库与账户设置。

Nova 主题不修改管理后台。后台仍使用上游的 `/secure_path` 管理界面。先登录后台配置至少一个套餐和支付方式，前台购买流程才会显示可用内容。

## 5. Nginx 伪静态与静态缓存

在 aaPanel → Website → 站点 → **URL rewrite** 中使用以下配置；将 PHP FastCGI 段保留为 aaPanel 为你的 PHP 7.4 生成的默认段。

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

确认站点运行目录是 `public`。不要把整个项目根目录设为运行目录，也不要增加把 `.env`、`storage` 或 `vendor` 暴露为静态文件的规则。

## 6. 文件权限

假设 aaPanel 的 PHP-FPM/队列账户为 `www`：

```bash
cd /www/wwwroot/example.com
chown -R www:www storage bootstrap/cache config/theme
find storage bootstrap/cache config/theme -type d -exec chmod 775 {} \;
find storage bootstrap/cache config/theme -type f -exec chmod 664 {} \;
chmod 640 .env
```

主题源文件只需读取权限；不要对全项目递归 `chmod 777`。

## 7. 定时任务和队列

### 定时任务

aaPanel → **Cron** → Shell Script：

```bash
cd /www/wwwroot/example.com && /www/server/php/74/bin/php artisan schedule:run >> /dev/null 2>&1
```

周期：每分钟一次。

### Supervisor

aaPanel → App Store → Supervisor → Add Daemon：

- Name: `jwboard-horizon`
- Run user: `www`
- Run directory: `/www/wwwroot/example.com`
- Start command: `/www/server/php/74/bin/php artisan horizon`
- Processes: `1`
- Autostart: 开启

部署或更新配置后执行：

```bash
php artisan config:cache
php artisan horizon:terminate
```

Supervisor 会自动拉起新的 Horizon 进程。

## 8. 上线验证清单

在启用真实支付、导入真实用户或接入节点前，使用测试账户逐项验证：

- HTTPS 重定向、首页与 `#/register`、`#/login` 正常。
- 邮箱验证码、注册、登录、密码重置正常。
- 套餐下单、支付网关回调、订单开通和取消的状态一致。
- `#/subscribe` 可复制订阅地址；重置订阅后旧地址失效。
- 邀请码、工单创建/回复、知识库文章可用。
- `php artisan horizon:status` 返回正常，Redis 未暴露公网。
- Nginx 与应用日志中不记录订阅 token、支付密钥或节点令牌。

商业发布前还必须完成 [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md) 中的节点凭据、支付回调、备份、运营与合规检查。

如需启用 Telegram 一键注册/登录，请继续按 [TELEGRAM_LOGIN.md](TELEGRAM_LOGIN.md) 配置 BotFather 域名、机器人 Token 与数据库唯一索引。

如需启用新增的 VLESS 节点类型，请继续按照 [VLESS.md](VLESS.md) 导入独立数据表并配置 VLESS 节点 Agent。

## 9. Nova 主题更新

只更新主题时，先备份并覆盖以下目录即可：

```text
public/theme/nova/
```

然后执行：

```bash
php artisan config:clear
php artisan config:cache
```

浏览器若仍展示旧样式，请清理 CDN/浏览器缓存；也可以在管理后台保存一次前端配置来更新页面资源版本。

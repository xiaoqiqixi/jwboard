# JWBoard

JWBoard 是面向商业化运营的代理服务管理面板，基于 Laravel 8，包含 Nova 前台、VLESS、Cloudflare Turnstile、Telegram 一键注册/登录，以及 V2bX 节点端适配。

本项目仅应用于你有权运营的网络和业务。上线真实支付、真实用户和真实节点前，请先在独立测试环境完成全流程验证。

## 运行环境

- PHP 7.4：保持 PHP 7.4，不要升级到 PHP 8。
- MySQL 5.6+：保持 MySQL 5.6 可用，不要求升级。
- Redis。
- Nginx、Git、Composer 2、Supervisor（aaPanel 可直接安装）。
- PHP 7.4 扩展：`redis`、`fileinfo`、`pdo_mysql`、`curl`、`mbstring`、`openssl`、`opcache`。

在 aaPanel 的 PHP 7.4「禁用函数」中，确认 `proc_open`、`pcntl_alarm`、`pcntl_signal`、`putenv` 未被禁用；Horizon 队列需要它们。不要将 MySQL、Redis 直接暴露至公网，也不要对整个项目递归设置 `777` 权限。

## 首次部署（aaPanel / Linux）

1. 在 aaPanel 创建站点、数据库和独立数据库用户；站点 PHP 版本选择 PHP 7.4，并申请 HTTPS 证书。
2. 将网站运行目录设置为项目中的 `public` 目录，例如：

   ```text
   /www/wwwroot/jwboard/public
   ```

3. 在 SSH 中克隆项目并执行初始化：

   ```bash
   cd /www/wwwroot
   git clone https://github.com/xiaoqiqixi/jwboard.git
   cd jwboard
   PHP_BIN=/www/server/php/74/bin/php ./init.sh
   ```

`init.sh` 会自动创建 Laravel 所需目录、使用 PHP 7.4 执行 `composer install`，并进入交互式安装。首次安装前不要手工创建 `.env`；脚本会询问数据库地址、数据库名、用户名、密码和管理员邮箱，然后导入 `database/install.sql`。

首次安装完成后，服务器本地会生成 `composer.lock`。请保留这个文件，后续更新始终使用 `composer install`，不要执行无目的的 `composer update`。

## 必要的 `.env` 配置

安装完成后编辑 `.env`，至少确认以下生产配置正确：

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://panel.example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=数据库名
DB_USERNAME=数据库用户
DB_PASSWORD=强数据库密码

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=Redis密码
```

修改配置后执行：

```bash
PHP_BIN=/www/server/php/74/bin/php
$PHP_BIN artisan config:clear
$PHP_BIN artisan config:cache
```

`.env` 必须放在项目根目录而非 `public` 中；推荐权限为 `640`。`SERVER_TOKEN` 必须使用随机强值，仅存放于面板 `.env` 与受控 V2bX 节点配置中。

## Nginx 伪静态、队列与定时任务

aaPanel 的站点 URL 重写可使用：

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

添加 aaPanel 定时任务，每分钟执行一次：

```bash
cd /www/wwwroot/jwboard && /www/server/php/74/bin/php artisan schedule:run >> /dev/null 2>&1
```

在 aaPanel Supervisor 新增守护进程：

```text
名称：jwboard-horizon
运行用户：www
运行目录：/www/wwwroot/jwboard
启动命令：/www/server/php/74/bin/php artisan horizon
进程数：1
自动启动：开启
```

VLESS 流量统计、订单状态处理和定时任务依赖 Horizon 与每分钟调度；二者缺失会导致流量或订单状态延迟。

## Cloudflare Turnstile（替代 Google reCAPTCHA）

本项目不使用 Google reCAPTCHA。到 Cloudflare Dashboard 的 Turnstile 页面创建 Managed Widget，将实际前台域名加入允许域名，然后在 `.env` 写入：

```dotenv
TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=你的SiteKey
TURNSTILE_SECRET_KEY=你的SecretKey
```

执行配置刷新命令后，注册、邮箱验证码和找回密码将使用 Turnstile。密钥只放在 `.env`，不要写入前端、截图或公开配置。

## Telegram 一键注册与登录

启用后，用户可以通过 Nova 登录/注册页的 Telegram 官方登录按钮直接注册或登录，无需填写邮箱、密码、验证码，也无需向机器人发送绑定命令。

1. 在 BotFather 创建机器人并保存 Bot Token；机器人必须有公开用户名。
2. 在 BotFather 执行 `/setdomain`，设置前台 HTTPS 域名，不能带路径。
3. 管理后台进入 Telegram 设置，启用机器人并填入同一个 Bot Token。
4. 已安装站点执行一次升级命令：

   ```bash
   cd /www/wwwroot/jwboard
   PHP_BIN=/www/server/php/74/bin/php ./update.sh
   ```

该操作会建立 `telegram_id` 唯一索引。若历史数据库存在重复 Telegram ID，先执行以下 SQL，处理重复数据后再运行升级：

```sql
SELECT telegram_id, COUNT(*) AS count
FROM v2_user
WHERE telegram_id IS NOT NULL
GROUP BY telegram_id
HAVING COUNT(*) > 1;
```

Telegram 注册页使用 `https://你的域名/#/register?code=邀请码` 时，会继承邀请码、邀请关系、试用套餐、停止注册、IP 注册限制与封禁检查。Telegram-only 用户使用系统生成的内部占位邮箱和随机密码，仅用于兼容既有表结构，不能作为真实联系邮箱。

## VLESS 节点与订阅

VLESS 是独立节点类型，使用 `v2_server_vless` 表；不会迁移、读取或修改 VMess 节点。

已安装站点可运行安全更新脚本建立 VLESS 表：

```bash
cd /www/wwwroot/jwboard
PHP_BIN=/www/server/php/74/bin/php ./update.sh
```

如果当前服务器还没有 `composer.lock`，可只执行一次数据库导入：

```bash
mysql -u 数据库用户 -p 数据库名 < database/vless.sql
```

管理员登录后台后，进入 `#/vless` 管理 VLESS 节点。支持 `none`、`tls`、`reality` 安全模式，以及 `tcp`、`ws`、`grpc` 传输。Reality 的 `privateKey`、`dest` 和 `serverPort` 只下发给节点端，不会出现在用户订阅中。

普通订阅、v2rayN、v2rayNG、SagerNet、Shadowrocket、Passwall、SSR Plus 会输出标准 `vless://`；Clash Meta/Stash 输出 VLESS YAML。旧版 Clash、Surge、Loon、Surfboard、Quantumult X 不接收 VLESS 节点，避免生成不可用配置。

## V2bX 配置 VLESS

V2bX 使用面板 `.env` 的全局 `SERVER_TOKEN`，不是用户订阅 Token。将 `/etc/V2bX/config.json` 的节点写为：

```json
{
  "Log": {"Level": "info", "Output": ""},
  "Cores": [
    {
      "Type": "xray",
      "Log": {"Level": "warning", "AccessPath": "", "ErrorPath": ""},
      "AssetPath": "/etc/V2bX/"
    }
  ],
  "Nodes": [
    {
      "Core": "xray",
      "ApiHost": "https://panel.example.com",
      "ApiKey": "替换为SERVER_TOKEN",
      "NodeID": 1,
      "NodeType": "vless",
      "Timeout": 30,
      "ListenIP": "0.0.0.0",
      "SendIP": "0.0.0.0",
      "ReportMinTraffic": 0,
      "EnableProxyProtocol": false,
      "EnableFallback": false,
      "DisableSniffing": false
    }
  ]
}
```

`NodeID` 必须填写 VLESS 管理页中的节点 ID，不是 VMess 节点 ID。Reality 私钥、Short ID 和回落目标由节点向面板 UniProxy API 拉取，不应复制到 V2bX 配置文件。完成后执行：

```bash
systemctl restart V2bX
systemctl status V2bX --no-pager
journalctl -u V2bX -n 100 --no-pager
```

先使用测试用户验证用户拉取、流量回传、到期禁用、v2rayN/Clash Meta 导入，再开放给真实用户。不要在日志、工单或截图中暴露 `SERVER_TOKEN`、Reality 私钥或用户订阅地址。

## 安全更新（GitHub）

首次部署必须从 GitHub 克隆：

```bash
cd /www/wwwroot
git clone https://github.com/xiaoqiqixi/jwboard.git
cd jwboard
PHP_BIN=/www/server/php/74/bin/php ./init.sh
```

后续更新在同一个项目目录执行：

```bash
cd /www/wwwroot/jwboard
PHP_BIN=/www/server/php/74/bin/php ./update.sh
```

`update.sh` 只接受 Git 快进更新，并依次执行：拉取 `origin` 当前分支、`composer install`、`jwboard:update`、刷新配置缓存、重启 Horizon。它不会执行 `git reset --hard`、不会修改 `.env`、不会删除 `composer.lock`、不会执行 `composer update`，也不会递归修改整个项目的文件所有者。

若你改过源代码，先使用 Git 提交自己的修改或完成备份；脚本发现已跟踪文件有本地改动时会停止，避免覆盖。若使用非 `main` 分支：

```bash
JWBOARD_BRANCH=你的分支名 PHP_BIN=/www/server/php/74/bin/php ./update.sh
```

更新完成后，在 aaPanel 重启 PHP 7.4 的 PHP-FPM（启用 OPCache 时尤其需要），并检查首页、后台、Horizon、定时任务、支付回调、订阅与测试节点流量是否正常。

## 上线前检查

- `APP_DEBUG=false`，网站和后台均只通过 HTTPS 访问。
- `.env`、数据库、Redis 与 `SERVER_TOKEN` 不泄露、不公开。
- 至少用测试账号验证注册、登录、找回密码、下单、支付回调、订阅重置、邀请码、工单与套餐变更。
- 确认 `php artisan horizon:status` 正常，Redis 不监听公网。
- VLESS Reality 使用独立测试节点，确认统计流量、到期禁用和订阅导入正确。
- 在发布前备份数据库、`.env`、`storage/`、`config/theme/`。

## 许可

本项目保留上游 V2Board 的 MIT 许可证与版权声明；JWBoard 的新增功能与品牌变更同样遵循 MIT 许可证。

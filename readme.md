# JWBoard

JWBoard 是面向商业化运营的代理服务管理面板，基于 Laravel 8，包含 Nova 前台、VLESS、Cloudflare Turnstile、Telegram 一键登录及 V2bX 节点端适配。

## 环境要求

- PHP 7.4（不要升级到 PHP 8）
- MySQL 5.6+
- Redis
- Composer 2 与 Git

## 安装

```bash
git clone https://github.com/xiaoqiqixi/jwboard.git
cd jwboard
PHP_BIN=/www/server/php/74/bin/php ./init.sh
```

`init.sh` 会创建运行目录、使用锁定的依赖版本安装 `vendor/`，并启动交互式安装。首次安装前不要手工创建 `.env`。

部署、VLESS、Telegram 登录的完整说明位于 [docs](docs/) 目录。

## 安全更新

```bash
cd /www/wwwroot/your-site
PHP_BIN=/www/server/php/74/bin/php ./update.sh
```

更新脚本仅接受 Git 的快进更新：不会执行 `git reset`、不会修改 `.env`、不会删除锁文件，也不会运行 `composer update`。它会执行 `composer install`、应用 JWBoard 数据库兼容更新并刷新配置缓存。已有本地代码修改时会停止，以免覆盖你的改动。

正式发布前必须随仓库提交 `composer.lock`；`update.sh` 会在锁文件缺失时直接停止，避免在线上环境解析出不同的依赖版本。

更多操作细节见 [docs/UPDATE.md](docs/UPDATE.md)。

## 开源许可与来源

本项目保留上游 V2Board 的 MIT 许可证与版权声明，详情见 [LICENSE](LICENSE)。JWBoard 的新增功能和品牌变更同样以 MIT 许可证发布。

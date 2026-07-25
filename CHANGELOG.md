# JWBoard 版本记录

## JWBoard 1.0.0 — 2026-07-25

首个正式商业化发布。

- 新增独立 VLESS 节点、Reality 配置、订阅输出与流量统计。
- 适配 V2bX 的 `vless` 节点类型与全局 `SERVER_TOKEN`。
- 新增 Nova 前台主题。
- Google reCAPTCHA 替换为 Cloudflare Turnstile。
- 新增 Telegram 一键注册与登录，并兼容邀请码与试用规则。
- 品牌重命名为 JWBoard。
- 新增安全的 Git 更新脚本与更新教程。

## 后续版本发布规则

每次发版都必须：

1. 修改 `VERSION`；
2. 在本文件说明新增功能、修复和数据库变更；
3. 对数据库变更补充可重复执行的升级逻辑；
4. 创建 Git 标签，格式为 `jwboard主版本.次版本.修订版本`；
5. 在测试环境执行安装、更新、登录、订阅与节点流量验证后再发布。

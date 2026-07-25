# Telegram 一键注册与登录

启用后，Nova 登录与注册页会显示 Telegram 官方登录按钮。用户在 Telegram 中确认后即可自动注册或登录：无需填写邮箱、密码、验证码，也无需再向机器人发送绑定命令。

## 配置

1. 在 BotFather 创建机器人并取得 Bot Token；机器人必须有公开的 `username`。
2. 在 BotFather 使用 `/setdomain`，填入前台的 HTTPS 域名（不要带路径）。
3. 在原管理后台的 Telegram 设置中启用机器人并保存 Token；如同时使用工单/通知机器人，可沿用同一个机器人。
4. 对已有站点执行一次：

   ```bash
   mysql -u 数据库用户 -p 数据库名 < database/telegram-login.sql
   php artisan config:clear
   php artisan config:cache
   ```

   SQL 第一条查询必须没有返回重复 `telegram_id`；若有结果，先人工确认并清理重复绑定后再继续。

## 邀请码

使用 `https://你的域名/#/register?code=邀请码` 打开 Telegram 注册时，邀请码会随 Telegram 已签名身份提交。它与邮箱注册使用同一套规则：强制邀请码、一次性邀请码、永不过期邀请码、邀请关系与试用套餐均保持一致。

## 安全边界

- 登录数据由服务端按 Telegram 官方 HMAC 规则校验，且 `auth_date` 超过 24 小时会被拒绝。
- `stop_register`、按 IP 注册限额、封禁检查仍然生效；Telegram 登录不绕过这些规则。
- 为兼容既有数据库，每个 Telegram-only 账户会保存一个不可用于登录的内部占位邮箱和随机密码。不要向该地址发送邮件，也不要把它当作可联系邮箱。
- 使用真实 HTTPS 域名；Bot Token 只应存放在面板配置中，绝不能放进前端代码、截图或公开仓库。

# VLESS 节点支持

VLESS 是新增的独立节点类型，使用 `v2_server_vless` 表；不会读取、迁移或修改现有 VMess 节点。

## 升级已有站点

在项目根目录执行一次：

```bash
mysql -u 数据库用户 -p 数据库名 < database/vless.sql
php artisan config:clear
php artisan config:cache
```

## 节点字段

使用 Nova 前台登录管理员账户后，进入 **VLESS 管理**（`#/vless`）即可创建、编辑、复制、隐藏和删除 VLESS 节点。该页面只在管理员登录后显示；接口仍会在服务端再次校验管理员身份。

VLESS API 位于现有管理员安全路径下：

```text
GET  /api/v1/{secure_path}/server/vless/fetch
POST /api/v1/{secure_path}/server/vless/save
POST /api/v1/{secure_path}/server/vless/update
POST /api/v1/{secure_path}/server/vless/copy
POST /api/v1/{secure_path}/server/vless/drop
POST /api/v1/{secure_path}/server/vless/sort
```

请求需要管理员 JWT 的 `Authorization` 头。`save` 创建或更新节点，字段如下：

```json
{
  "name": "HK VLESS Reality",
  "group_id": [1],
  "host": "hk.example.com",
  "port": "443",
  "server_port": 443,
  "security": "reality",
  "flow": "xtls-rprx-vision",
  "rate": 1,
  "network": "tcp",
  "networkSettings": {},
  "tlsSettings": {"serverName": "hk.example.com", "allowInsecure": false},
  "realitySettings": {
    "serverName": "www.example.com",
    "publicKey": "Xray Reality 公钥",
    "privateKey": "Xray Reality 私钥（仅节点端使用）",
    "shortId": "0123456789abcdef",
    "dest": "www.example.com",
    "serverPort": 443,
    "fingerprint": "chrome",
    "spiderX": "/"
  },
  "show": 1
}
```

- `security`：`none`、`tls`、`reality`。
- `network`：`tcp`、`ws`、`grpc`。
- `networkSettings.ws` 使用 `path`、`headers.Host`；gRPC 使用 `serviceName`。
- Reality 必须填写真实 Xray 公钥、私钥、Short ID、回落目标及端口；这些值必须与节点 Xray 入站一致。
- `privateKey`、`dest`、`serverPort` 只会发送到节点端的 UniProxy 接口，不会出现在用户节点列表或订阅中。

## 订阅与节点端

普通订阅、v2rayN、v2rayNG、SagerNet、Shadowrocket、Passwall、SSR Plus 会输出标准 `vless://` URI；Clash Meta/Stash 输出 VLESS YAML。旧版 Clash、Surge、Loon、Surfboard 和 Quantumult X 不会收到 VLESS 节点，以免生成不被客户端支持的配置。

UniProxy 节点 API 接受 `node_type=vless`，与 VMess 使用同一组 `user`、`push`、`config` 端点。为了兼容 V2bX，`config` 响应同时返回 V2bX 所需的数值 `tls`（`0`、`1`、`2`）和 snake_case 的 Reality 节点字段。

```text
security, tls, flow, network, networkSettings, tlsSettings, realitySettings
```

V2bX 的完整节点部署见 [V2BX_VLESS.md](V2BX_VLESS.md)。面板不会替节点服务器自动安装 Xray、签发证书或生成 Reality 私钥；先在测试节点验证用户同步、流量回传、到期禁用和订阅导入后，再向真实用户开放。

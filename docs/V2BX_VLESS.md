# V2bX VLESS 节点部署

本面板已为 `wyx2685/V2bX` 的 UniProxy 接口补齐兼容字段。V2bX 上游已经实现 VLESS/Xray 入站，因此不需要替换其协议实现；节点配置的 `NodeType` 必须是独立的 `vless`，不能写为 `vmess` 或 `v2ray`。

## 1. 前置条件

- 先按 [VLESS.md](VLESS.md) 导入面板数据库变更，并在 Nova 的 `#/vless` 创建节点。
- VLESS 节点的 `id` 是 VLESS 管理列表中的 ID，不是 VMess 节点 ID。
- 节点服务器开放 VLESS 的 `server_port`（及必要的 HTTPS/证书端口），面板 API 只应通过 HTTPS 访问。
- 使用 V2bX 上游安装包或从其源码构建 Xray 内核版本；不要使用仅支持 VMess 的旧版节点程序。

## 2. Reality 节点字段

在 VLESS 管理页的 **Reality 设置（JSON）** 填入以下结构。公钥给订阅客户端，私钥只给 V2bX 节点端；二者不可互换。

```json
{
  "serverName": "www.example.com",
  "publicKey": "客户端使用的 Reality 公钥",
  "privateKey": "节点端 Reality 私钥",
  "shortId": "0123456789abcdef",
  "dest": "www.example.com",
  "serverPort": 443,
  "fingerprint": "chrome",
  "spiderX": "/"
}
```

生成密钥可在节点服务器执行 V2bX 的 `V2bX x25519` 命令，或使用与部署版本对应的 Xray 密钥生成命令。先保存私钥到面板 Reality 设置，再将输出的公钥保存到 `publicKey`。

## 3. V2bX 配置

将 `/etc/V2bX/config.json` 的节点项配置为下面形式。把示例地址、Token 和节点 ID 替换为实际值；`ApiKey` 是面板 `.env` 的全局 `SERVER_TOKEN`，不是用户订阅 Token。

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

Reality 不需要在 V2bX 配置文件内重复写私钥、Short ID 或回落目标；节点会从面板的 `/api/v1/server/UniProxy/config` 拉取。TLS 节点则按 V2bX 原始文档配置 `CertConfig`。

## 4. 启动与验收

```bash
systemctl restart V2bX
systemctl status V2bX --no-pager
journalctl -u V2bX -n 100 --no-pager
```

确认日志没有 `unsupported Node type`、Reality 私钥缺失或入站构建错误。随后用测试账户验证：用户拉取、流量回传、到期用户失效，以及 v2rayN 或 Clash Meta 导入生成的 VLESS Reality 订阅。不要在日志、工单或截图中暴露 `SERVER_TOKEN`、Reality 私钥或用户订阅链接。

(() => {
  'use strict';
  const root = document.querySelector('#root');
  const securePath = String((window.settings || {}).secure_path || '').replace(/^\/+|\/+$/g, '');
  const endpoint = path => `/${securePath}/server/vless/${path}`;
  const escapeHtml = value => String(value == null ? '' : value).replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const asJson = value => JSON.stringify(value || {}, null, 2);
  let servers = [], editingServer = {};

  function isOpen() { return location.hash === '#/server/manage/vless'; }
  function isManage() { return location.hash === '#/server/manage' || (root && root.textContent.indexOf('节点管理') !== -1); }
  function request(path, options) {
    const headers = {Accept: 'application/json', Authorization: localStorage.getItem('authorization') || ''};
    if (options && options.body) headers['Content-Type'] = 'application/json';
    return fetch(endpoint(path), Object.assign({headers}, options || {})).then(async response => {
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || payload.code && payload.code !== 200) throw new Error(payload.message || '请求失败');
      return payload.data;
    });
  }
  function parseArray(value) {
    const input = String(value || '').trim();
    if (!input) return [];
    if (input[0] === '[') return JSON.parse(input);
    return input.split(',').map(item => Number(item.trim())).filter(Boolean);
  }
  function parseObject(value) { const input = String(value || '').trim(); return input ? JSON.parse(input) : {}; }
  function form(server) {
    server = server || {};
    const select = (name, values, selected) => `<select name="${name}">${values.map(([value, label]) => `<option value="${value}" ${String(value) === String(selected) ? 'selected' : ''}>${label}</option>`).join('')}</select>`;
    return `<section class="jw-vless-card"><h2>${server.id ? '编辑 VLESS 节点' : '新增 VLESS 节点'}</h2><p class="jw-vless-muted">与 VMess、Trojan 位于同一节点管理区；配置保存后由 V2bX 拉取。</p><form id="jw-vless-form"><input type="hidden" name="id" value="${escapeHtml(server.id)}"><div class="jw-vless-grid"><div><div class="jw-vless-field"><label>节点名称</label><input name="name" required value="${escapeHtml(server.name)}"></div><div class="jw-vless-field"><label>主机地址</label><input name="host" required value="${escapeHtml(server.host)}"></div><div class="jw-vless-field"><label>订阅端口</label><input name="port" required value="${escapeHtml(server.port || 443)}"></div><div class="jw-vless-field"><label>Xray 入站端口</label><input name="server_port" type="number" required value="${escapeHtml(server.server_port || 443)}"></div><div class="jw-vless-field"><label>权限组 ID（例如 1,2）</label><input name="group_id" required value="${escapeHtml((server.group_id || [1]).join(','))}"></div><div class="jw-vless-field"><label>路由组 ID（可选）</label><input name="route_id" value="${escapeHtml((server.route_id || []).join(','))}"></div><div class="jw-vless-field"><label>父节点 ID（可选）</label><input name="parent_id" type="number" value="${escapeHtml(server.parent_id)}"></div><div class="jw-vless-field"><label>倍率</label><input name="rate" type="number" min="0" step="0.01" required value="${escapeHtml(server.rate || 1)}"></div></div><div><div class="jw-vless-field"><label>安全层</label>${select('security', [['tls','TLS'],['reality','Reality'],['none','None']], server.security || 'tls')}</div><div class="jw-vless-field"><label>传输协议</label>${select('network', [['tcp','TCP'],['ws','WebSocket'],['grpc','gRPC']], server.network || 'tcp')}</div><div class="jw-vless-field"><label>Flow</label>${select('flow', [['','无'],['xtls-rprx-vision','xtls-rprx-vision'],['xtls-rprx-vision-udp443','xtls-rprx-vision-udp443']], server.flow || '')}</div><div class="jw-vless-field"><label>状态</label>${select('show', [['1','显示'],['0','隐藏']], String(server.show == null ? 1 : server.show))}</div><div class="jw-vless-field"><label>传输设置（JSON）</label><textarea name="networkSettings">${escapeHtml(asJson(server.networkSettings))}</textarea></div><div class="jw-vless-field"><label>TLS 设置（JSON）</label><textarea name="tlsSettings">${escapeHtml(asJson(server.tlsSettings))}</textarea></div><div class="jw-vless-field"><label>Reality 设置（JSON）</label><textarea name="realitySettings">${escapeHtml(asJson(server.realitySettings))}</textarea></div></div></div><div class="jw-vless-actions"><button class="jw-vless-btn primary">保存节点</button><button class="jw-vless-btn" type="button" data-new>新建节点</button></div></form></section>`;
  }
  function table() {
    const rows = servers.length ? servers.map(server => `<tr><td>${escapeHtml(server.name)}</td><td>${escapeHtml(server.host)}:${escapeHtml(server.port)}</td><td>${escapeHtml(server.security)}</td><td>${server.show ? '显示' : '隐藏'}</td><td><button class="jw-vless-btn" data-edit="${server.id}">编辑</button> <button class="jw-vless-btn" data-copy="${server.id}">复制</button> <button class="jw-vless-btn danger" data-drop="${server.id}">删除</button></td></tr>`).join('') : '<tr><td colspan="5" class="jw-vless-muted">尚未创建 VLESS 节点</td></tr>';
    return `<section class="jw-vless-card"><h2>VLESS 节点</h2><table class="jw-vless-table"><thead><tr><th>名称</th><th>地址</th><th>安全层</th><th>状态</th><th>操作</th></tr></thead><tbody>${rows}</tbody></table></section>`;
  }
  function bind() {
    const panel = document.querySelector('#jw-vless-admin');
    if (!panel) return;
    const configFields = document.createElement('div');
    configFields.className = 'jw-vless-grid';
    configFields.innerHTML = `<div class="jw-vless-field"><label>节点标签（逗号分隔，可选）</label><input name="tags" value="${escapeHtml((editingServer.tags || []).join(','))}"></div><div class="jw-vless-field"><label>规则设置（JSON）</label><textarea name="ruleSettings">${escapeHtml(asJson(editingServer.ruleSettings))}</textarea></div><div class="jw-vless-field"><label>DNS 设置（JSON）</label><textarea name="dnsSettings">${escapeHtml(asJson(editingServer.dnsSettings))}</textarea></div>`;
    panel.querySelector('.jw-vless-actions').before(configFields);
    panel.querySelector('[data-close]').onclick = () => { location.hash = '#/server/manage'; };
    panel.querySelector('[data-new]').onclick = () => draw();
    panel.querySelectorAll('[data-edit]').forEach((button, index) => { button.onclick = () => draw(servers.find(server => String(server.id) === button.dataset.edit)); button.insertAdjacentHTML('afterend', ` <button class="jw-vless-btn" data-move="${button.dataset.edit}" data-offset="-1" ${index ? '' : 'disabled'}>↑</button> <button class="jw-vless-btn" data-move="${button.dataset.edit}" data-offset="1" ${index + 1 < servers.length ? '' : 'disabled'}>↓</button>`); });
    panel.querySelectorAll('[data-move]').forEach(button => button.onclick = () => move(button.dataset.move, Number(button.dataset.offset)));
    panel.querySelectorAll('[data-copy]').forEach(button => button.onclick = async () => { await request('copy', {method: 'POST', body: JSON.stringify({id: button.dataset.copy})}); await load(); });
    panel.querySelectorAll('[data-drop]').forEach(button => button.onclick = async () => { if (confirm('确定删除此 VLESS 节点？')) { await request('drop', {method: 'POST', body: JSON.stringify({id: button.dataset.drop})}); await load(); } });
    panel.querySelector('#jw-vless-form').onsubmit = async event => {
      event.preventDefault();
      const data = Object.fromEntries(new FormData(event.currentTarget));
      try {
        data.group_id = parseArray(data.group_id); data.route_id = parseArray(data.route_id);
        data.tags = String(data.tags || '').split(',').map(item => item.trim()).filter(Boolean);
        ['networkSettings', 'tlsSettings', 'realitySettings', 'ruleSettings', 'dnsSettings'].forEach(name => data[name] = parseObject(data[name]));
        if (!data.id) delete data.id; if (!data.parent_id) delete data.parent_id;
        await request('save', {method: 'POST', body: JSON.stringify(data)}); await load();
      } catch (error) { showError(error.message === 'Unexpected end of JSON input' ? 'JSON 设置格式不正确' : error.message); }
    };
  }
  async function move(id, offset) {
    const ids = servers.map(server => server.id), index = ids.indexOf(Number(id)), target = index + offset;
    if (index < 0 || target < 0 || target >= ids.length) return;
    [ids[index], ids[target]] = [ids[target], ids[index]];
    await request('sort', {method: 'POST', body: JSON.stringify({ids})}); await load();
  }
  function showError(message) { const el = document.querySelector('#jw-vless-error'); if (el) el.textContent = message; }
  function draw(selected) {
    if (!isOpen()) return;
    editingServer = selected || {};
    let panel = document.querySelector('#jw-vless-admin');
    if (!panel) { panel = document.createElement('div'); panel.id = 'jw-vless-admin'; document.body.append(panel); }
    panel.innerHTML = `<div class="jw-vless-wrap"><header class="jw-vless-head"><div><h1>节点管理 / VLESS</h1><p>与 VMess、Trojan 使用相同的管理员权限和节点管理入口。</p></div><button class="jw-vless-btn" data-close>返回节点管理</button></header><p id="jw-vless-error" class="jw-vless-error"></p>${form(selected)}${table()}</div>`;
    bind();
  }
  async function load(selected) { draw(selected); try { servers = await request('fetch'); draw(selected); } catch (error) { showError(error.message); } }
  function sync() {
    const launch = document.querySelector('#jw-vless-launch');
    const panel = document.querySelector('#jw-vless-admin');
    if (isOpen()) { if (launch) launch.remove(); if (!panel) load(); return; }
    if (panel) panel.remove();
    if (isManage() && !launch) {
      const button = document.createElement('button'); button.id = 'jw-vless-launch'; button.textContent = 'VLESS 节点'; button.onclick = () => { location.hash = '#/server/manage/vless'; }; document.body.append(button);
    } else if (!isManage() && launch) launch.remove();
  }
  window.addEventListener('hashchange', sync);
  new MutationObserver(sync).observe(root, {childList: true, subtree: true});
  sync();
})();

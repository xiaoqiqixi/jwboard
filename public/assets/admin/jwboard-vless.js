(() => {
  'use strict';
  const root = document.querySelector('#root');
  const securePath = String((window.settings || {}).secure_path || '').replace(/^\/+|\/+$/g, '');
  const endpoint = path => `/${securePath}/server/vless/${path}`;
  const esc = value => String(value == null ? '' : value).replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
  const json = value => JSON.stringify(value || {}, null, 2);
  let servers = [], timer;

  function request(path, options) {
    const headers = {Accept: 'application/json', Authorization: localStorage.getItem('authorization') || ''};
    if (options && options.body) headers['Content-Type'] = 'application/json';
    return fetch(endpoint(path), Object.assign({headers}, options || {})).then(async response => {
      const data = await response.json().catch(() => ({}));
      if (!response.ok || data.code && data.code !== 200) throw new Error(data.message || '请求失败');
      return data.data;
    });
  }
  function isNodePage() { return location.hash === '#/server/manage' || root && root.textContent.indexOf('节点管理') !== -1; }
  function list(value) { const input = String(value || '').trim(); return input ? input[0] === '[' ? JSON.parse(input) : input.split(',').map(item => Number(item.trim())).filter(Boolean) : []; }
  function object(value) { return String(value || '').trim() ? JSON.parse(value) : {}; }
  function closeDrawer() { document.querySelector('#jw-vless-drawer')?.remove(); }
  function openDrawer(server = {}) {
    closeDrawer();
    const select = (name, values, selected) => `<select name="${name}">${values.map(([value, label]) => `<option value="${value}" ${String(value) === String(selected) ? 'selected' : ''}>${label}</option>`).join('')}</select>`;
    const panel = document.createElement('div');
    panel.id = 'jw-vless-drawer';
    panel.innerHTML = `<div class="jw-vless-mask" data-close></div><aside class="jw-vless-panel"><header><h2>${server.id ? '编辑 VLESS 节点' : '新建 VLESS 节点'}</h2><button type="button" data-close>×</button></header><form id="jw-vless-form"><input type="hidden" name="id" value="${esc(server.id)}"><div class="jw-vless-grid"><label>节点名称<input name="name" required value="${esc(server.name)}"></label><label>倍率<input name="rate" type="number" min="0" step="0.01" required value="${esc(server.rate || 1)}"></label><label>主机地址<input name="host" required value="${esc(server.host)}"></label><label>订阅端口<input name="port" required value="${esc(server.port || 443)}"></label><label>Xray 入站端口<input name="server_port" type="number" required value="${esc(server.server_port || 443)}"></label><label>权限组 ID（例如 1,2）<input name="group_id" required value="${esc((server.group_id || [1]).join(','))}"></label><label>路由组 ID（可选）<input name="route_id" value="${esc((server.route_id || []).join(','))}"></label><label>父节点 ID（可选）<input name="parent_id" type="number" value="${esc(server.parent_id)}"></label><label>安全层${select('security', [['tls','TLS'],['reality','Reality'],['none','None']], server.security || 'tls')}</label><label>传输协议${select('network', [['tcp','TCP'],['ws','WebSocket'],['grpc','gRPC']], server.network || 'tcp')}</label><label>Flow${select('flow', [['','无'],['xtls-rprx-vision','xtls-rprx-vision'],['xtls-rprx-vision-udp443','xtls-rprx-vision-udp443']], server.flow || '')}</label><label>显示状态${select('show', [['1','显示'],['0','隐藏']], server.show == null ? '1' : server.show)}</label></div><label>节点标签（逗号分隔）<input name="tags" value="${esc((server.tags || []).join(','))}"></label><label>传输设置（JSON）<textarea name="networkSettings">${esc(json(server.networkSettings))}</textarea></label><label>TLS 设置（JSON）<textarea name="tlsSettings">${esc(json(server.tlsSettings))}</textarea></label><label>Reality 设置（JSON）<textarea name="realitySettings">${esc(json(server.realitySettings))}</textarea></label><label>规则设置（JSON）<textarea name="ruleSettings">${esc(json(server.ruleSettings))}</textarea></label><label>DNS 设置（JSON）<textarea name="dnsSettings">${esc(json(server.dnsSettings))}</textarea></label><p class="jw-vless-error" aria-live="polite"></p><footer><button type="button" data-close>取消</button><button class="primary">保存</button></footer></form></aside>`;
    document.body.append(panel);
    panel.querySelectorAll('[data-close]').forEach(button => button.onclick = closeDrawer);
    panel.querySelector('#jw-vless-form').onsubmit = async event => {
      event.preventDefault();
      const data = Object.fromEntries(new FormData(event.currentTarget));
      try {
        data.group_id = list(data.group_id); data.route_id = list(data.route_id); data.tags = String(data.tags || '').split(',').map(tag => tag.trim()).filter(Boolean);
        ['networkSettings', 'tlsSettings', 'realitySettings', 'ruleSettings', 'dnsSettings'].forEach(key => data[key] = object(data[key]));
        if (!data.id) delete data.id; if (!data.parent_id) delete data.parent_id;
        await request('save', {method: 'POST', body: JSON.stringify(data)}); closeDrawer(); refresh(true);
      } catch (error) { panel.querySelector('.jw-vless-error').textContent = error.message === 'Unexpected end of JSON input' ? 'JSON 设置格式不正确' : error.message; }
    };
  }
  function addMenuItem(menu) {
    if (menu.dataset.jwVless || !/VMess/.test(menu.textContent)) return;
    menu.dataset.jwVless = '1';
    const vmess = Array.from(menu.children).find(item => /VMess/.test(item.textContent));
    const item = document.createElement('li');
    item.className = vmess?.className || 'ant-dropdown-menu-item';
    item.innerHTML = '<span class="ant-tag jw-vless-tag">VLESS</span>';
    item.onclick = event => { event.preventDefault(); event.stopPropagation(); openDrawer(); };
    if (vmess) vmess.after(item); else menu.append(item);
  }
  function decorateRows() {
    document.querySelectorAll('.ant-dropdown-menu').forEach(addMenuItem);
    if (!isNodePage()) return;
    document.querySelectorAll('.ant-table-tbody tr, .v2board_node_mobile').forEach(row => {
      const server = servers.find(item => row.textContent.indexOf(item.name) !== -1 && row.textContent.indexOf(item.host) !== -1);
      if (!server || row.dataset.jwVless === String(server.id)) return;
      row.dataset.jwVless = String(server.id);
      const cells = row.querySelectorAll('td');
      if (cells.length) {
        cells[0].innerHTML = `<span class="ant-tag jw-vless-tag">VLESS</span> ${server.parent_id ? `${server.id} => ${server.parent_id}` : server.id}`;
        if (cells[1]) cells[1].innerHTML = `<button class="jw-vless-switch ${Number(server.show) ? 'on' : ''}" aria-label="切换显示状态"></button>`;
        const actions = cells[cells.length - 1];
        actions.innerHTML = '<a data-vless-edit>编辑</a><span class="ant-divider ant-divider-vertical"></span><a data-vless-copy>复制</a><span class="ant-divider ant-divider-vertical"></span><a class="jw-vless-delete" data-vless-drop>删除</a>';
        actions.querySelector('[data-vless-edit]').onclick = () => openDrawer(server);
        actions.querySelector('[data-vless-copy]').onclick = async () => { await request('copy', {method: 'POST', body: JSON.stringify({id: server.id})}); refresh(true); };
        actions.querySelector('[data-vless-drop]').onclick = async () => { if (confirm('确定删除此 VLESS 节点？')) { await request('drop', {method: 'POST', body: JSON.stringify({id: server.id})}); refresh(true); } };
        cells[1]?.querySelector('button').addEventListener('click', async () => { await request('update', {method: 'POST', body: JSON.stringify({id: server.id, show: Number(server.show) ? 0 : 1})}); refresh(true); });
      }
    });
  }
  async function refresh(reload) {
    if (!isNodePage()) return;
    try { servers = await request('fetch'); decorateRows(); if (reload) setTimeout(() => location.reload(), 120); } catch (_) {}
  }
  function schedule() { clearTimeout(timer); timer = setTimeout(refresh, 120); }
  new MutationObserver(schedule).observe(document.body, {childList: true, subtree: true});
  window.addEventListener('hashchange', schedule); schedule();
})();

const apiBase = '/api';
let appState = { user: null, services: [] };

const toastContainer = document.getElementById('toast');
const loader = document.getElementById('loader');
const memberBadge = document.getElementById('member-badge');
const adminNav = document.getElementById('admin-nav');
const serviceList = document.getElementById('services-list');
const adminUsers = document.getElementById('admin-users');
const adminServices = document.getElementById('admin-services');
const adminLogs = document.getElementById('admin-logs');

window.addEventListener('load', initApp);

document.querySelectorAll('[data-page]').forEach((button) => {
  button.addEventListener('click', () => showPage(button.dataset.page));
});

document.getElementById('refresh-services').addEventListener('click', loadServices);
document.getElementById('reload-users').addEventListener('click', loadAdminUsers);
document.getElementById('reload-logs').addEventListener('click', loadAdminLogs);
document.getElementById('open-service-form').addEventListener('click', openServiceForm);
document.getElementById('close-service-form').addEventListener('click', closeServiceForm);
document.getElementById('save-service-button').addEventListener('click', saveServiceForm);
document.getElementById('buy-vip').addEventListener('click', () => showToast('VIP üyelik için lütfen yönetici ile iletişime geçin.', 'info'));
document.getElementById('view-profile-vip').addEventListener('click', () => showPage('profile'));

function initApp() {
  if (!window.Telegram?.WebApp) {
    showToast('Bu sayfa Telegram Mini App içinde açılmalıdır.', 'error');
    loader.textContent = 'Telegram Mini App içinde açın.';
    return;
  }

  const tg = window.Telegram.WebApp;
  tg.ready();
  const initData = tg.initData || '';

  if (!initData) {
    showToast('Telegram initData bulunamadı.', 'error');
    loader.textContent = 'Kimlik doğrulama yapılamadı.';
    return;
  }

  authenticate(initData);
}

async function authenticate(initData) {
  try {
    const response = await apiRequest('/auth.php', { initData });
    appState.user = response.data.user;
    localStorage.setItem('auth_token', response.data.auth_token);
    setMemberBadge();
    if (appState.user.is_admin) {
      adminNav.classList.remove('hidden');
    }
    showPage('home');
    loader.style.display = 'none';
    loadServices();
    updateProfilePage();
    if (appState.user.is_admin) {
      loadAdminUsers();
      loadAdminServices();
      loadAdminLogs();
    }
  } catch (error) {
    showToast(error.message || 'Kimlik doğrulama başarısız.', 'error');
    loader.textContent = 'Kimlik doğrulama başarısız.';
    loader.style.display = 'none';
  }
}

function setMemberBadge() {
  if (!appState.user) return;
  memberBadge.textContent = `${appState.user.membership_type.toUpperCase()} Üye`;
  memberBadge.className = appState.user.membership_type === 'vip'
    ? 'inline-flex items-center rounded-full bg-emerald-500/15 px-4 py-2 text-sm font-medium text-emerald-100 ring-1 ring-emerald-500/20'
    : 'inline-flex items-center rounded-full bg-slate-700/15 px-4 py-2 text-sm font-medium text-slate-100 ring-1 ring-slate-600/20';
}

function showPage(pageId) {
  document.querySelectorAll('.page').forEach((section) => {
    section.classList.toggle('active', section.id === pageId);
  });
}

async function apiRequest(path, payload = null, method = 'POST') {
  const token = localStorage.getItem('auth_token');
  const headers = { 'Content-Type': 'application/json' };
  if (token) headers['X-Auth-Token'] = token;

  const response = await fetch(`${apiBase}${path}`, {
    method,
    headers,
    credentials: 'include',
    body: payload !== null ? JSON.stringify(payload) : undefined,
  });

  const data = await response.json();
  if (!data.success) {
    throw new Error(data.error || 'Sunucudan beklenmeyen yanıt alındı.');
  }
  return data;
}

async function loadServices() {
  try {
    const data = await apiRequest('/services.php', null, 'GET');
    appState.services = data.data.services;
    appState.user = data.data.user;
    renderServices();
    updateProfilePage();
  } catch (error) {
    showToast(error.message, 'error');
  }
}

function renderServices() {
  if (!serviceList) return;
  serviceList.innerHTML = appState.services.map((service) => {
    const availability = service.available ? 'Erişilebilir' : 'VIP Üye Olmanız Gerekiyor';
    const badge = service.is_vip ? '<span class="rounded-full bg-purple-500/15 px-2 py-1 text-xs font-semibold text-purple-200">VIP</span>' : '<span class="rounded-full bg-slate-600/15 px-2 py-1 text-xs font-semibold text-slate-200">Free</span>';
    return `
      <article class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <div class="flex items-center gap-2 text-slate-100">
              <h3 class="text-xl font-semibold">${escapeHtml(service.name)}</h3>
              ${badge}
            </div>
            <p class="mt-3 text-slate-400">${escapeHtml(service.description)}</p>
          </div>
          <div class="space-y-2 text-right">
            <p class="text-sm uppercase tracking-[0.3em] text-slate-500">${availability}</p>
            <p class="text-lg font-semibold text-cyan-300">${Number(service.price).toFixed(2)} ₺</p>
          </div>
        </div>
        <div class="mt-6 grid gap-3 sm:grid-cols-[1fr_auto]">
          <input id="query-input-${service.id}" class="form-input w-full rounded-2xl border border-slate-700 px-4 py-3" placeholder="Sorgu değerini girin" />
          <button onclick="runQuery(${service.id})" class="rounded-2xl bg-cyan-500 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-400" ${service.available ? '' : 'disabled style="opacity:.5;cursor:not-allowed;"'}>Sorgula</button>
        </div>
      </article>
    `;
  }).join('');
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

async function runQuery(serviceId) {
  const inputElement = document.getElementById(`query-input-${serviceId}`);
  const input = inputElement?.value.trim();
  if (!input) {
    showToast('Lütfen sorgu değeri girin.', 'warning');
    return;
  }
  try {
    showToast('Sorgu işleniyor...', 'info');
    const response = await apiRequest('/query.php', { service_id: serviceId, input });
    showToast('Sorgu tamamlandı.', 'success');
    showQueryResult(response.data);
    appState.user = response.data.user;
    updateProfilePage();
  } catch (error) {
    showToast(error.message, 'error');
  }
}

function showQueryResult(result) {
  const html = `
    <div class="mt-6 rounded-3xl border border-cyan-500/20 bg-slate-900/80 p-6">
      <h3 class="text-lg font-semibold text-slate-100">Sonuç</h3>
      <pre class="mt-4 max-h-96 overflow-auto rounded-2xl bg-slate-950 p-4 text-sm text-slate-200">${escapeHtml(JSON.stringify(result.result, null, 2))}</pre>
    </div>
  `;
  const resultContainer = document.createElement('div');
  resultContainer.innerHTML = html;
  const servicesSection = document.getElementById('services');
  servicesSection.appendChild(resultContainer);
}

function updateProfilePage() {
  if (!appState.user) return;
  document.getElementById('profile-username').textContent = `@${appState.user.username}`;
  document.getElementById('profile-telegram-id').textContent = `Telegram ID: ${appState.user.telegram_id}`;
  document.getElementById('profile-membership').textContent = `Üyelik: ${appState.user.membership_type.toUpperCase()}`;
  document.getElementById('profile-expire').textContent = appState.user.membership_expire ? `VIP Bitiş: ${appState.user.membership_expire}` : 'VIP süresi yok';
  document.getElementById('profile-query-count').textContent = `${appState.user.daily_query_count}/${appState.user.daily_query_limit ?? 'Limitsiz'}`;
  setMemberBadge();
}

async function loadAdminUsers() {
  if (!appState.user?.is_admin) return;
  try {
    const data = await apiRequest('/admin.php', { action: 'list_users' });
    renderAdminUsers(data.data.users);
  } catch (error) {
    showToast(error.message, 'error');
  }
}

async function loadAdminLogs() {
  if (!appState.user?.is_admin) return;
  try {
    const data = await apiRequest('/admin.php', { action: 'logs', limit: 50 });
    renderAdminLogs(data.data.logs);
  } catch (error) {
    showToast(error.message, 'error');
  }
}

function renderAdminUsers(users) {
  adminUsers.innerHTML = users.map((user) => `
    <div class="rounded-3xl border border-slate-800 bg-slate-950/80 p-4">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="font-semibold text-slate-100">${escapeHtml(user.username)} (${user.telegram_id})</p>
          <p class="text-sm text-slate-400">Üyelik: ${escapeHtml(user.membership_type)} • VIP Bitiş: ${escapeHtml(user.membership_expire ?? 'Yok')}</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button onclick="toggleMembership(${user.id}, '${user.membership_type}')" class="rounded-full bg-cyan-500 px-3 py-2 text-xs font-semibold text-slate-950">Rol Değiştir</button>
          <button onclick="extendVip(${user.id})" class="rounded-full border border-slate-700 px-3 py-2 text-xs font-semibold text-slate-200">VIP Ekle</button>
        </div>
      </div>
    </div>
  `).join('');
}

function renderAdminLogs(logs) {
  adminLogs.innerHTML = logs.map((log) => `
    <div class="rounded-3xl border border-slate-800 bg-slate-950/80 p-4 text-sm text-slate-300">
      <p><strong>${escapeHtml(log.type)}</strong> • Kullanıcı ID: ${escapeHtml(log.user_id)} • Servis ID: ${escapeHtml(log.service_id)}</p>
      <p class="mt-2">${escapeHtml(log.created_at)}</p>
      <details class="mt-2 rounded-2xl bg-slate-900 p-3 text-slate-400"><summary>Detaylar</summary>
        <pre class="mt-2 overflow-auto text-xs">${escapeHtml(log.response_payload)}</pre>
      </details>
    </div>
  `).join('');
}

async function toggleMembership(userId, currentMembership) {
  const nextType = currentMembership === 'vip' ? 'free' : 'vip';
  try {
    await apiRequest('/admin.php', { action: 'update_user', user_id: userId, membership_type: nextType });
    showToast('Kullanıcı üyeliği güncellendi.', 'success');
    loadAdminUsers();
  } catch (error) {
    showToast(error.message, 'error');
  }
}

async function extendVip(userId) {
  const days = prompt('VIP süresini kaç gün uzatmak istersiniz?', '30');
  if (!days) return;
  try {
    await apiRequest('/admin.php', { action: 'extend_vip', user_id: userId, days: Number(days) });
    showToast('VIP süresi uzatıldı.', 'success');
    loadAdminUsers();
  } catch (error) {
    showToast(error.message, 'error');
  }
}

function openServiceForm() {
  document.getElementById('service-form-id').value = '';
  document.getElementById('service-form-name').value = '';
  document.getElementById('service-form-api').value = '';
  document.getElementById('service-form-description').value = '';
  document.getElementById('service-form-price').value = '0.00';
  document.getElementById('service-form-vip').value = '0';
  document.getElementById('service-form-status').value = 'active';
  document.getElementById('service-form-modal').classList.remove('hidden');
}

function closeServiceForm() {
  document.getElementById('service-form-modal').classList.add('hidden');
}

async function saveServiceForm() {
  const id = Number(document.getElementById('service-form-id').value || 0);
  const name = document.getElementById('service-form-name').value.trim();
  const apiUrl = document.getElementById('service-form-api').value.trim();
  const description = document.getElementById('service-form-description').value.trim();
  const price = Number(document.getElementById('service-form-price').value || 0);
  const isVip = Number(document.getElementById('service-form-vip').value || 0);
  const status = document.getElementById('service-form-status').value;

  if (!name || !apiUrl) {
    showToast('Servis adı ve API URL doldurulmalıdır.', 'warning');
    return;
  }

  try {
    await apiRequest('/admin.php', {
      action: 'save_service', id, name, description, api_url: apiUrl, price, is_vip: isVip, status,
    });
    showToast('Servis kaydedildi.', 'success');
    closeServiceForm();
    loadAdminServices();
  } catch (error) {
    showToast(error.message, 'error');
  }
}

async function loadAdminServices() {
  if (!appState.user?.is_admin) return;
  try {
    const data = await apiRequest('/admin.php', { action: 'services_list' });
    adminServices.innerHTML = data.data.services.map((service) => `
      <div class="rounded-3xl border border-slate-800 bg-slate-950/80 p-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="font-semibold text-slate-100">${escapeHtml(service.name)}</p>
            <p class="text-sm text-slate-400">${escapeHtml(service.api_url)}</p>
          </div>
          <div class="flex gap-2">
            <button onclick="editService(${service.id})" class="rounded-full bg-cyan-500 px-3 py-2 text-xs font-semibold text-slate-950">Düzenle</button>
            <button onclick="deleteService(${service.id})" class="rounded-full border border-red-700 px-3 py-2 text-xs font-semibold text-red-200">Sil</button>
          </div>
        </div>
      </div>
    `).join('');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

async function editService(id) {
  try {
    const data = await apiRequest('/admin.php', { action: 'services_list' });
    const service = data.data.services.find((item) => item.id === id);
    if (!service) {
      showToast('Servis bulunamadı.', 'error');
      return;
    }
    document.getElementById('service-form-id').value = service.id;
    document.getElementById('service-form-name').value = service.name;
    document.getElementById('service-form-api').value = service.api_url;
    document.getElementById('service-form-description').value = service.description;
    document.getElementById('service-form-price').value = Number(service.price).toFixed(2);
    document.getElementById('service-form-vip').value = service.is_vip ? '1' : '0';
    document.getElementById('service-form-status').value = service.status;
    document.getElementById('service-form-modal').classList.remove('hidden');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

async function deleteService(id) {
  if (!confirm('Bu servisi silmek istediğinizden emin misiniz?')) return;
  try {
    await apiRequest('/admin.php', { action: 'delete_service', id });
    showToast('Servis silindi.', 'success');
    loadAdminServices();
  } catch (error) {
    showToast(error.message, 'error');
  }
}

function showToast(message, type = 'success') {
  if (!toastContainer) return;
  const colors = {
    success: 'bg-emerald-500 text-slate-950',
    error: 'bg-rose-500 text-white',
    warning: 'bg-amber-500 text-slate-950',
    info: 'bg-cyan-500 text-slate-950',
  };
  const toast = document.createElement('div');
  toast.className = `rounded-3xl px-4 py-3 shadow-xl shadow-slate-950/20 ${colors[type] || colors.success}`;
  toast.textContent = message;
  toastContainer.appendChild(toast);

  setTimeout(() => {
    toast.remove();
  }, 4500);
}

// expose functions for HTML inline handlers
window.runQuery = runQuery;
window.toggleMembership = toggleMembership;
window.extendVip = extendVip;
window.editService = editService;
window.deleteService = deleteService;

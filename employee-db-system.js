(() => {
  const state = {
    selectedEmployeeId: 'NS-0248',
    employees: [
      { id: 'NS-0248', name: 'Maya Chen', email: 'maya.chen@northstar.co', position: 'Product Designer', department: 'Design', site: 'Singapore HQ', client: 'Acme Retail', status: 'Active', manager: 'Olivia Bennett', color: '' },
      { id: 'NS-0184', name: 'Daniel Kim', email: 'daniel.kim@northstar.co', position: 'Engineering Lead', department: 'Engineering', site: 'Seoul Studio', client: 'Acme Retail', status: 'Active', manager: 'Jordan Davis', color: 'purple' },
      { id: 'NS-0132', name: 'Sofia Martinez', email: 'sofia.martinez@northstar.co', position: 'Marketing Manager', department: 'Marketing', site: 'Remote', client: 'Brightline Health', status: 'On leave', manager: 'Jordan Davis', color: 'peach' },
      { id: 'NS-0108', name: 'Noah Thompson', email: 'noah.thompson@northstar.co', position: 'Operations Coordinator', department: 'Operations', site: 'Sydney Hub', client: 'Harbor & Co', status: 'Active', manager: 'Jordan Davis', color: 'yellow' },
      { id: 'NS-0216', name: 'Amelia Patel', email: 'amelia.patel@northstar.co', position: 'Financial Analyst', department: 'Finance', site: 'Singapore HQ', client: 'Brightline Health', status: 'Active', manager: 'Jordan Davis', color: 'green' },
      { id: 'NS-0205', name: 'Ethan Williams', email: 'ethan.williams@northstar.co', position: 'Software Engineer', department: 'Engineering', site: 'Singapore HQ', client: 'Harbor & Co', status: 'Active', manager: 'Daniel Kim', color: 'violet' },
      { id: 'NS-0199', name: 'Priya Shah', email: 'priya.shah@northstar.co', position: 'Product Manager', department: 'Product', site: 'Remote', client: 'Acme Retail', status: 'Active', manager: 'Olivia Bennett', color: 'rose' },
      { id: 'NS-0097', name: 'Marcus Lee', email: 'marcus.lee@northstar.co', position: 'People Partner', department: 'People', site: 'Singapore HQ', client: 'Internal', status: 'Inactive', manager: 'Jordan Davis', color: 'blue' }
    ],
    clients: [
      { id: 'CL-001', name: 'Acme Retail', industry: 'Retail', owner: 'Jordan Davis', color: 'blue' },
      { id: 'CL-002', name: 'Brightline Health', industry: 'Healthcare', owner: 'Olivia Bennett', color: 'green' },
      { id: 'CL-003', name: 'Harbor & Co', industry: 'Professional services', owner: 'Jordan Davis', color: 'purple' },
      { id: 'CL-004', name: 'Internal', industry: 'Company operations', owner: 'People team', color: 'peach' }
    ],
    accounts: [
      { name: 'Jordan Davis', email: 'jordan.davis@northstar.co', role: 'HR administrator', status: 'Active' },
      { name: 'Maya Chen', email: 'maya.chen@northstar.co', role: 'Employee · Design', status: 'Active' },
      { name: 'Ethan Williams', email: 'ethan.williams@northstar.co', role: 'Manager · Engineering', status: 'Pending' },
      { name: 'Sofia Martinez', email: 'sofia.martinez@northstar.co', role: 'Employee · Marketing', status: 'Active' }
    ]
  };

  const byId = (id) => document.getElementById(id);
  const esc = (value) => String(value).replace(/[&<>'"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));
  const initials = (name) => name.split(' ').map((part) => part[0]).join('').slice(0, 2).toUpperCase();
  const avatarClass = (employee) => employee.color ? `ui-avatar ui-${employee.color}` : 'ui-avatar';
  const statusClass = (status) => status === 'On leave' ? 'ui-status ui-warn' : status === 'Inactive' ? 'ui-status ui-danger' : 'ui-status';

  function toast(message) {
    let node = document.querySelector('.ui-toast');
    if (!node) { node = document.createElement('div'); node.className = 'ui-toast'; node.setAttribute('role', 'status'); document.body.appendChild(node); }
    node.textContent = message; node.classList.add('ui-show'); window.clearTimeout(node._timer);
    node._timer = window.setTimeout(() => node.classList.remove('ui-show'), 2600);
  }

  async function copyText(text, message) {
    try { await navigator.clipboard.writeText(text); }
    catch {
      const helper = document.createElement('textarea'); helper.value = text; helper.style.position = 'fixed'; helper.style.opacity = '0'; document.body.appendChild(helper); helper.select(); document.execCommand('copy'); helper.remove();
    }
    toast(message);
  }

  function employeeTsv(list) {
    const header = ['Employee ID', 'Name', 'Email', 'Position', 'Department', 'Site location', 'Client', 'Status'];
    const rows = list.map((employee) => [employee.id, employee.name, employee.email, employee.position, employee.department, employee.site, employee.client, employee.status]);
    return [header, ...rows].map((row) => row.map((value) => String(value).replace(/\t/g, ' ')).join('\t')).join('\n');
  }

  function employeeCsv(list) {
    return employeeTsv(list).split('\n').map((row) => row.split('\t').map((value) => `"${value.replace(/"/g, '""')}"`).join(',')).join('\n');
  }

  function currentFilteredEmployees() {
    const query = (byId('employeeSearch')?.value || '').trim().toLowerCase();
    const client = byId('clientFilter')?.value || '';
    const department = byId('departmentFilter')?.value || '';
    const status = byId('statusFilter')?.value || '';
    return state.employees.filter((employee) => {
      const searchable = `${employee.id} ${employee.name} ${employee.email} ${employee.position} ${employee.department} ${employee.site} ${employee.client}`.toLowerCase();
      return (!query || searchable.includes(query)) && (!client || employee.client === client) && (!department || employee.department === department) && (!status || employee.status === status);
    });
  }

  function renderEmployeeTable() {
    const list = currentFilteredEmployees();
    const rows = byId('employeeRows');
    if (!rows) return;
    rows.innerHTML = list.length ? list.map((employee) => `<tr class="${employee.id === state.selectedEmployeeId ? 'is-selected' : ''}" data-employee-row="${employee.id}">
      <td><label class="system-check"><input type="checkbox" data-employee-check="${employee.id}" /><span></span></label></td>
      <td><div class="ui-person"><div class="${avatarClass(employee)}">${initials(employee.name)}</div><div><b>${esc(employee.name)}</b><span>${esc(employee.email)}</span></div></div></td>
      <td>${esc(employee.position)}</td><td>${esc(employee.department)}</td><td>${esc(employee.site)}</td><td>${esc(employee.client)}</td><td><span class="${statusClass(employee.status)}">${esc(employee.status)}</span></td>
    </tr>`).join('') : '<tr><td colspan="7" class="system-empty">No employees match your filters.</td></tr>';
    byId('employeeResultCount').textContent = `Showing ${list.length} of ${state.employees.length} employee users`;
    byId('visibleCount').textContent = list.length;
    byId('totalEmployeeMetric').textContent = state.employees.length;
    renderSelectedEmployee();
  }

  function renderSelectedEmployee() {
    const employee = state.employees.find((item) => item.id === state.selectedEmployeeId) || state.employees[0];
    if (!employee) return;
    byId('profileName').textContent = employee.name;
    byId('profileRole').textContent = `${employee.position} · ${employee.department}`;
    byId('profileSite').textContent = `${employee.site} · ${employee.client}`;
    byId('profileAvatar').textContent = initials(employee.name);
    byId('profileAvatar').className = `system-profile-avatar ${employee.color || ''}`;
    byId('profileEmail').textContent = employee.email;
    byId('profileManager').textContent = employee.manager;
    byId('profilePosition').textContent = employee.position;
    byId('profileDepartment').textContent = employee.department;
    byId('profileClient').textContent = employee.client;
    byId('profileLocation').textContent = employee.site;
    byId('profileStatus').textContent = employee.status;
    byId('profileStatus').className = statusClass(employee.status);
    byId('profileId').textContent = employee.id;
  }

  function renderClients() {
    const container = byId('clientRows');
    if (!container) return;
    container.innerHTML = state.clients.map((client) => {
      const count = state.employees.filter((employee) => employee.client === client.name).length;
      return `<div class="client-row"><div class="client-mark ${client.color || ''}">${initials(client.name)}</div><div><b>${esc(client.name)}</b><span>${esc(client.industry)} · Owner ${esc(client.owner)}</span></div><strong>${count}</strong><button type="button" class="ui-btn ui-copy-client" data-copy-client="${esc(client.name)}">Copy</button></div>`;
    }).join('');
    byId('clientCount').textContent = state.clients.length;
    const clientFilter = byId('clientFilter');
    if (clientFilter) {
      const selected = clientFilter.value;
      clientFilter.innerHTML = '<option value="">All clients</option>' + state.clients.map((client) => `<option value="${esc(client.name)}">${esc(client.name)}</option>`).join('');
      clientFilter.value = state.clients.some((client) => client.name === selected) ? selected : '';
    }
    const accountClient = byId('accountClient');
    if (accountClient) accountClient.innerHTML = state.clients.map((client) => `<option>${esc(client.name)}</option>`).join('');
  }

  function renderAccounts() {
    const container = byId('accountRows');
    if (!container) return;
    container.innerHTML = state.accounts.map((account) => `<div class="account-row"><div class="ui-avatar">${initials(account.name)}</div><div><b>${esc(account.name)}</b><span>${esc(account.email)} · ${esc(account.role)}</span></div><span class="${account.status === 'Pending' ? 'ui-status ui-warn' : 'ui-status'}">${esc(account.status)}</span></div>`).join('');
  }

  function download(filename, content, type) {
    const link = document.createElement('a'); link.href = URL.createObjectURL(new Blob([content], { type })); link.download = filename; link.click(); URL.revokeObjectURL(link.href);
  }

  function setup() {
    const params = new URLSearchParams(window.location.search);
    const requestedEmployee = params.get('employee');
    const requestedDepartment = params.get('department');
    if (requestedEmployee && state.employees.some((employee) => employee.id === requestedEmployee)) state.selectedEmployeeId = requestedEmployee;
    renderClients();
    if (requestedDepartment && byId('departmentFilter')) byId('departmentFilter').value = requestedDepartment;
    renderAccounts(); renderEmployeeTable();
    ['employeeSearch', 'clientFilter', 'departmentFilter', 'statusFilter'].forEach((id) => byId(id)?.addEventListener('input', renderEmployeeTable));
    byId('globalSearch')?.addEventListener('input', (event) => { byId('employeeSearch').value = event.target.value; renderEmployeeTable(); });
    byId('employeeRows')?.addEventListener('click', (event) => {
      if (event.target.closest('input')) return;
      const row = event.target.closest('[data-employee-row]'); if (!row) return;
      state.selectedEmployeeId = row.dataset.employeeRow; renderEmployeeTable();
    });
    document.addEventListener('click', (event) => {
      const clientButton = event.target.closest('[data-copy-client]');
      if (clientButton) {
        const client = clientButton.dataset.copyClient; const list = state.employees.filter((employee) => employee.client === client);
        copyText(employeeTsv(list), `${list.length} ${client} employees copied as tab-separated data.`);
      }
    });
    byId('copyVisible')?.addEventListener('click', () => copyText(employeeTsv(currentFilteredEmployees()), `${currentFilteredEmployees().length} visible employees copied.`));
    byId('copySelected')?.addEventListener('click', () => {
      const ids = [...document.querySelectorAll('[data-employee-check]:checked')].map((input) => input.dataset.employeeCheck);
      const list = state.employees.filter((employee) => ids.includes(employee.id));
      if (!list.length) { toast('Select one or more employee users first.'); return; }
      copyText(employeeTsv(list), `${list.length} selected employees copied.`);
    });
    byId('copyAll')?.addEventListener('click', () => copyText(employeeTsv(state.employees), `All ${state.employees.length} employees copied.`));
    byId('exportCsv')?.addEventListener('click', () => { const list = currentFilteredEmployees(); download('employee-data.csv', employeeCsv(list), 'text/csv'); toast(`${list.length} employees exported.`); });
    byId('copyProfile')?.addEventListener('click', () => { const employee = state.employees.find((item) => item.id === state.selectedEmployeeId); copyText(employeeTsv([employee]), `${employee.name}'s record copied.`); });
    byId('accountForm')?.addEventListener('submit', (event) => {
      event.preventDefault(); const form = new FormData(event.target); const name = String(form.get('name')); const email = String(form.get('email')); const department = String(form.get('department')); const position = String(form.get('position')); const site = String(form.get('site')); const client = String(form.get('client'));
      const id = `NS-${String(250 + state.employees.length).padStart(4, '0')}`;
      state.employees.unshift({ id, name, email, position, department, site, client, status: 'Active', manager: 'Jordan Davis', color: 'blue' });
      state.accounts.unshift({ name, email, role: `${String(form.get('role'))} · ${department}`, status: 'Pending' });
      renderClients(); renderAccounts(); renderEmployeeTable(); event.target.closest('dialog')?.close(); event.target.reset(); toast(`${name} added to the employee database.`);
    });
    byId('clientForm')?.addEventListener('submit', (event) => {
      event.preventDefault(); const form = new FormData(event.target); const name = String(form.get('name')); state.clients.push({ id: `CL-${String(1 + state.clients.length).padStart(3, '0')}`, name, industry: String(form.get('industry')), owner: 'Jordan Davis', color: 'blue' }); renderClients(); event.target.closest('dialog')?.close(); event.target.reset(); toast(`${name} added to the client list.`);
    });
    byId('selectAllEmployees')?.addEventListener('change', (event) => document.querySelectorAll('[data-employee-check]').forEach((input) => { input.checked = event.target.checked; }));
    byId('copyClientFilter')?.addEventListener('click', () => {
      const client = byId('clientFilter').value; if (!client) { toast('Choose a client before copying client data.'); return; }
      const list = state.employees.filter((employee) => employee.client === client); copyText(employeeTsv(list), `${list.length} ${client} employees copied.`);
    });
    byId('copyAllClients')?.addEventListener('click', () => {
      const list = state.employees.filter((employee) => employee.client !== 'Internal');
      copyText(employeeTsv(list), `${list.length} client-assigned employees copied.`);
    });
    byId('copyWebhookEndpoint')?.addEventListener('click', () => copyText('https://api.northstar.co/hooks/employee-db', 'Webhook endpoint copied.'));
  }

  setup();
})();

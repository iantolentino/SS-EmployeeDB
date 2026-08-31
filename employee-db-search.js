(() => {
  const people = [
    { id:'NS-0248', name:'Maya Chen', email:'maya.chen@northstar.co', position:'Product Designer', department:'Design', site:'Singapore HQ', client:'Acme Retail' },
    { id:'NS-0184', name:'Daniel Kim', email:'daniel.kim@northstar.co', position:'Engineering Lead', department:'Engineering', site:'Seoul Studio', client:'Acme Retail' },
    { id:'NS-0132', name:'Sofia Martinez', email:'sofia.martinez@northstar.co', position:'Marketing Manager', department:'Marketing', site:'Remote', client:'Brightline Health' },
    { id:'NS-0108', name:'Noah Thompson', email:'noah.thompson@northstar.co', position:'Operations Coordinator', department:'Operations', site:'Sydney Hub', client:'Harbor & Co' },
    { id:'NS-0216', name:'Amelia Patel', email:'amelia.patel@northstar.co', position:'Financial Analyst', department:'Finance', site:'Singapore HQ', client:'Brightline Health' },
    { id:'NS-0205', name:'Ethan Williams', email:'ethan.williams@northstar.co', position:'Software Engineer', department:'Engineering', site:'Singapore HQ', client:'Harbor & Co' },
    { id:'NS-0199', name:'Priya Shah', email:'priya.shah@northstar.co', position:'Product Manager', department:'Product', site:'Remote', client:'Acme Retail' },
    { id:'NS-0097', name:'Marcus Lee', email:'marcus.lee@northstar.co', position:'People Partner', department:'People', site:'Singapore HQ', client:'Internal' }
  ];
  const departments = [
    { name:'Engineering', count:78, manager:'Daniel Kim', positions:12, sites:'Singapore HQ · Seoul Studio' },
    { name:'Design', count:42, manager:'Olivia Bennett', positions:7, sites:'Singapore HQ · Remote' },
    { name:'Marketing', count:31, manager:'Sofia Martinez', positions:8, sites:'Singapore HQ · Remote' },
    { name:'Operations', count:54, manager:'Noah Thompson', positions:10, sites:'Singapore HQ · Sydney Hub' },
    { name:'Finance', count:26, manager:'Amelia Patel', positions:6, sites:'Singapore HQ' },
    { name:'People', count:17, manager:'Jordan Davis', positions:5, sites:'Singapore HQ · Remote' },
    { name:'Product', count:28, manager:'Priya Shah', positions:9, sites:'Singapore HQ · Remote' }
  ];
  const input = document.getElementById('mainSearch');
  const results = document.getElementById('searchResults');
  const clear = document.getElementById('clearSearch');
  const esc = (value) => String(value).replace(/[&<>'"]/g, (char) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', "'":'&#39;', '"':'&quot;' }[char]));
  const initials = (name) => name.split(' ').map((part) => part[0]).join('').slice(0,2).toUpperCase();
  const matches = (text, tokens) => tokens.every((token) => text.toLowerCase().includes(token));

  if (localStorage.getItem('employee-db-theme') === 'dark') document.body.classList.add('dark');

  function render() {
    const query = input.value.trim();
    clear.classList.toggle('show', Boolean(query));
    if (!query) { results.classList.remove('show'); results.innerHTML=''; input.setAttribute('aria-expanded','false'); return; }
    const tokens = query.toLowerCase().split(/\s+/).filter(Boolean);
    const foundPeople = people.filter((person) => matches(`${person.id} ${person.name} ${person.email} ${person.position} ${person.department} ${person.site} ${person.client}`, tokens)).slice(0,8);
    const foundDepartments = departments.filter((department) => matches(`${department.name} ${department.manager} ${department.sites}`, tokens)).slice(0,6);
    let html = '';
    if (foundPeople.length) html += `<section class="result-group"><div class="result-heading"><span>People</span><span>${foundPeople.length} result${foundPeople.length===1?'':'s'}</span></div>${foundPeople.map((person) => `<a class="result-item" href="employee-db-system.html?employee=${encodeURIComponent(person.id)}#employees"><span class="result-avatar">${initials(person.name)}</span><span class="result-text"><b>${esc(person.name)}</b><span>${esc(person.position)} · ${esc(person.department)} · ${esc(person.client)}</span></span><span class="result-meta">${esc(person.site)}</span><span class="result-arrow">→</span></a>`).join('')}</section>`;
    if (foundDepartments.length) html += `<section class="result-group"><div class="result-heading"><span>Departments</span><span>${foundDepartments.length} result${foundDepartments.length===1?'':'s'}</span></div>${foundDepartments.map((department) => `<a class="result-item" href="employee-db-system.html?department=${encodeURIComponent(department.name)}#departments"><span class="result-avatar department">▦</span><span class="result-text"><b>${esc(department.name)}</b><span>${department.count} employee users · ${department.positions} positions · Manager ${esc(department.manager)}</span></span><span class="result-meta">${esc(department.sites)}</span><span class="result-arrow">→</span></a>`).join('')}</section>`;
    if (!html) html = `<div class="no-results">No people or departments match “${esc(query)}”.</div>`;
    results.innerHTML = html; results.classList.add('show'); input.setAttribute('aria-expanded','true');
  }

  input.addEventListener('input', render);
  input.addEventListener('keydown', (event) => { if (event.key === 'Escape') { input.value=''; render(); input.focus(); } });
  clear.addEventListener('click', () => { input.value=''; render(); input.focus(); });
})();

(() => {
  const byId = (id) => document.getElementById(id);
  const endpoint = (action, params = {}) => {
    const url = new URL(document.body.dataset.appUrl, location.origin);
    Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
    url.searchParams.set('action', action);
    return url.pathname + url.search;
  };
  let savedTheme;
  try { savedTheme = localStorage.getItem('employee-db-theme'); } catch {}
  const setTheme = (dark) => {
    document.body.classList.toggle('dark', dark);
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
      button.textContent = dark ? '☾' : '☀';
      button.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
      button.setAttribute('aria-pressed', String(dark));
    });
    try { localStorage.setItem('employee-db-theme', dark ? 'dark' : 'light'); } catch {}
  };
  setTheme(savedTheme === 'dark');
  const toast = (message) => {
    const node = byId('toast');
    if (!node) return;
    node.textContent = message;
    node.classList.add('ui-show');
    clearTimeout(node.timer);
    node.timer = setTimeout(() => node.classList.remove('ui-show'), 4000);
  };
  const highlight = () => {
    const id = document.querySelector('[data-profile-id]')?.dataset.profileId;
    document.querySelectorAll('[data-employee-id]').forEach((row) => row.classList.toggle('is-selected', row.dataset.employeeId === id));
  };
  const selectedEmployeeIds = new Set();
  let selectionMode = false;
  const matchingEmployeeIds = () => {
    const list = byId('directory')?.querySelector('[data-matching-ids]');
    return list ? JSON.parse(list.dataset.matchingIds) : [];
  };
  const syncSelection = () => {
    if (!byId('directory')) return;
    const matchingIds = matchingEmployeeIds();
    const allowedIds = new Set(matchingIds);
    for (const id of selectedEmployeeIds) if (!allowedIds.has(id)) selectedEmployeeIds.delete(id);
    document.body.classList.toggle('selection-mode', selectionMode);
    byId('selectionActions').hidden = !selectionMode && !selectedEmployeeIds.size;
    byId('toggleEmployeeSelection').textContent = selectionMode ? 'Done selecting' : 'Select employees';
    byId('toggleEmployeeSelection').setAttribute('aria-pressed', String(selectionMode));
    document.querySelectorAll('[data-export-employee]').forEach((box) => { box.checked = selectedEmployeeIds.has(box.dataset.exportEmployee); });
    document.querySelectorAll('[data-select-all]').forEach((box) => {
      box.checked = matchingIds.length > 0 && selectedEmployeeIds.size === matchingIds.length;
      box.indeterminate = selectedEmployeeIds.size > 0 && !box.checked;
      box.disabled = matchingIds.length === 0;
    });
    byId('employeeSelectionCount').textContent = `${selectedEmployeeIds.size} selected`;
    byId('selectAllEmployees').disabled = matchingIds.length === 0;
    byId('selectAllEmployees').textContent = `Select all ${matchingIds.length} matching`;
    byId('clearEmployeeSelection').disabled = selectedEmployeeIds.size === 0;
    byId('exportCsvLabel').textContent = selectedEmployeeIds.size ? `Export CSV (${selectedEmployeeIds.size})` : 'Export CSV';
  };
  const resetDirectoryPage = () => { if (byId('directoryPage')) byId('directoryPage').value = '1'; };
  ['input', 'change', 'submit'].forEach((name) => byId('directoryFilters')?.addEventListener(name, (event) => {
    resetDirectoryPage();
    if (event.target.matches('input[type="search"],select:not(#employeesPerPage)')) {
      selectedEmployeeIds.clear();
      syncSelection();
    }
  }, true));
  highlight();
  syncSelection();
  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-theme-toggle]')) setTheme(!document.body.classList.contains('dark'));
    if (event.target.closest('[data-close]')) byId('employeeDialog')?.close();
    const row = event.target.closest('[data-employee-row]');
    if (row && event.detail < 2 && !event.target.closest('button,a,input,label,.employee-select-column')) row.querySelector('[data-preview-link]')?.click();
  });
  document.addEventListener('dblclick', (event) => {
    const row = event.target.closest('[data-employee-row]');
    if (row && !event.target.closest('button,input,label,.employee-select-column')) {
      event.preventDefault();
      location.href = row.dataset.profileUrl;
    }
  });
  byId('toggleEmployeeSelection')?.addEventListener('click', () => {
    selectionMode = !selectionMode;
    syncSelection();
  });
  byId('selectAllEmployees')?.addEventListener('click', () => {
    matchingEmployeeIds().forEach((id) => selectedEmployeeIds.add(id));
    syncSelection();
  });
  byId('clearEmployeeSelection')?.addEventListener('click', () => { selectedEmployeeIds.clear(); syncSelection(); });
  document.addEventListener('change', (event) => {
    if (event.target.matches('[data-export-employee]')) {
      const id = event.target.dataset.exportEmployee;
      if (event.target.checked) selectedEmployeeIds.add(id); else selectedEmployeeIds.delete(id);
      syncSelection();
    }
    if (event.target.matches('[data-select-all]')) {
      if (event.target.checked) matchingEmployeeIds().forEach((id) => selectedEmployeeIds.add(id)); else selectedEmployeeIds.clear();
      syncSelection();
    }
  });
  byId('employeeDialog')?.addEventListener('click', (event) => { if (event.target === event.currentTarget) event.currentTarget.close(); });
  document.body.addEventListener('htmx:beforeSwap', (event) => {
    if (event.detail.xhr.status === 422) { event.detail.shouldSwap = true; event.detail.isError = false; }
  });
  document.body.addEventListener('htmx:afterSwap', (event) => {
    if (event.detail.target.id === 'modalContent' && byId('modalContent').textContent.trim()) {
      const dialog = byId('employeeDialog');
      if (!dialog.open) dialog.showModal();
    }
    if (event.detail.target.id === 'employeePreview') {
      const selected = byId('selectedEmployee');
      if (selected) selected.value = document.querySelector('[data-profile-id]')?.dataset.profileId || '';
    }
    if (event.detail.target.id === 'directory') {
      const form = byId('directoryFilters');
      const list = byId('directory').querySelector('[data-directory-page]');
      if (list) {
        byId('directoryPage').value = list.dataset.directoryPage;
        const url = new URL(document.body.dataset.appUrl, location.origin);
        for (const [key, value] of new FormData(form)) if (value !== '') url.searchParams.set(key, value);
        history.replaceState({}, '', url.pathname + url.search);
      }
      syncSelection();
    }
    highlight();
  });
  document.body.addEventListener('htmx:historyRestore', () => { highlight(); syncSelection(); });
  document.body.addEventListener('employeeSaved', (event) => {
    byId('employeeDialog')?.close();
    if (byId('employeePreview')) {
      byId('selectedEmployee').value = event.detail.id;
      htmx.ajax('GET', endpoint('preview', { id: event.detail.id }), { target: '#employeePreview' });
      const url = new URL(location.href);
      url.searchParams.set('action', 'employees');
      url.searchParams.set('selected', event.detail.id);
      history.replaceState({}, '', url.pathname + url.search);
      toast(event.detail.message || 'Employee saved.');
      return;
    }
    if (!byId('profile')) {
      location.href = endpoint('employee', { employee: event.detail.id });
      return;
    }
    htmx.ajax('GET', endpoint('profile', { id: event.detail.id }), { target: '#profile' });
    const url = new URL(document.body.dataset.appUrl, location.origin);
    url.searchParams.set('action', 'employee');
    url.searchParams.set('employee', event.detail.id);
    history.replaceState({}, '', url.pathname + url.search);
    toast(event.detail.message || 'Employee saved.');
  });
  document.body.addEventListener('accountSaved', (event) => { byId('employeeDialog')?.close(); toast(event.detail.message || 'Account created.'); });
  document.body.addEventListener('htmx:responseError', () => toast('Unable to complete the request. Please try again.'));
  document.body.addEventListener('htmx:sendError', () => toast('Connection lost. Check that the app is running and try again.'));
  document.body.addEventListener('htmx:beforeRequest', (event) => {
    const form = event.detail.elt.closest('form');
    if (form && event.detail.requestConfig.verb === 'post') form.querySelectorAll('[type="submit"]').forEach((button) => { button.disabled = true; });
  });
  document.body.addEventListener('htmx:afterRequest', (event) => {
    event.detail.elt.closest('form')?.querySelectorAll('[type="submit"]').forEach((button) => { button.disabled = false; });
  });
  byId('clearFilters')?.addEventListener('click', () => {
    const form = byId('directoryFilters');
    form.querySelectorAll('input[type="search"],select').forEach((field) => { field.value = ''; });
    byId('employeesPerPage').value = '10';
    selectedEmployeeIds.clear();
    syncSelection();
    htmx.trigger(form, 'submit');
  });
  byId('exportCsv')?.addEventListener('click', () => {
    const form = byId('directoryFilters');
    const values = {};
    if (form) for (const field of ['q', 'department', 'client', 'status']) values[field] = form.elements.namedItem(field).value;
    const exportUrl = endpoint('export', values);
    if (!selectedEmployeeIds.size) { location.href = exportUrl; return; }
    const exportForm = document.createElement('form');
    exportForm.method = 'post';
    exportForm.action = exportUrl;
    exportForm.hidden = true;
    for (const [name, value] of Object.entries({ csrf: byId('exportCsv').dataset.csrf, employee_ids: JSON.stringify([...selectedEmployeeIds]) })) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      exportForm.append(input);
    }
    document.body.append(exportForm);
    exportForm.submit();
    exportForm.remove();
  });
  byId('employeeSearch')?.addEventListener('keydown', (event) => { if (event.key === 'Escape') { event.target.value = ''; selectedEmployeeIds.clear(); syncSelection(); htmx.trigger(byId('directoryFilters'), 'submit'); } });
})();

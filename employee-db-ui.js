(() => {
  const toast = (message) => {
    let node = document.querySelector('.ui-toast');
    if (!node) {
      node = document.createElement('div');
      node.className = 'ui-toast';
      node.setAttribute('role', 'status');
      document.body.appendChild(node);
    }
    node.textContent = message;
    node.classList.add('ui-show');
    window.clearTimeout(node._timer);
    node._timer = window.setTimeout(() => node.classList.remove('ui-show'), 2600);
  };

  const setTheme = (dark) => {
    document.body.classList.toggle('dark', dark);
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
      button.setAttribute('aria-pressed', dark ? 'true' : 'false');
      button.textContent = dark ? '☀ Light mode' : '◐ Dark mode';
    });
    localStorage.setItem('employee-db-theme', dark ? 'dark' : 'light');
  };

  setTheme(localStorage.getItem('employee-db-theme') === 'dark');
  document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => setTheme(!document.body.classList.contains('dark')));
  });

  document.querySelectorAll('[data-nav]').forEach((link) => {
    link.addEventListener('click', () => {
      document.querySelectorAll('[data-nav]').forEach((item) => item.classList.remove('ui-active'));
      link.classList.add('ui-active');
    });
  });

  document.querySelectorAll('[data-open]').forEach((button) => {
    button.addEventListener('click', () => {
      const dialog = document.getElementById(button.dataset.open);
      if (dialog) dialog.showModal();
    });
  });
  document.querySelectorAll('[data-close]').forEach((button) => {
    button.addEventListener('click', () => button.closest('dialog')?.close());
  });

  document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
      if (event.target === dialog) dialog.close();
    });
  });

  document.querySelectorAll('[data-demo-form]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const type = form.dataset.demoForm;
      if (type === 'login') {
        form.closest('dialog')?.close();
        const destination = form.dataset.redirect || 'employee-db-system.html';
        window.location.href = destination;
      } else {
        const name = form.querySelector('[name="name"]')?.value || 'New account';
        form.closest('dialog')?.close();
        toast(`${name} has been added to the account list.`);
        form.reset();
      }
    });
  });

  document.querySelectorAll('[data-copy-webhook]').forEach((button) => {
    button.addEventListener('click', async () => {
      const value = button.closest('.ui-webhook-row')?.querySelector('.ui-code')?.textContent?.trim() || '';
      try { await navigator.clipboard.writeText(value); } catch { /* Local file previews may block clipboard access. */ }
      toast('Webhook endpoint copied.');
    });
  });

  document.querySelectorAll('[data-demo-action]').forEach((button) => {
    button.addEventListener('click', () => toast(button.dataset.demoAction));
  });
})();

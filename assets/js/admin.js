document.addEventListener('DOMContentLoaded', () => {
  const menuButton = document.querySelector('.admin-menu-button');
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.admin-overlay');
  const closeButton = document.querySelector('.sidebar-close');
  const profileMenu = document.querySelector('.profile-menu');
  const profileTrigger = document.querySelector('.profile-trigger');
  const isMobile = () => window.matchMedia('(max-width: 760px)').matches;

  const closeSidebar = () => {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('show');
    document.body.classList.remove('admin-drawer-open');
    menuButton?.setAttribute('aria-expanded', 'false');
  };

  const openSidebar = () => {
    sidebar?.classList.add('open');
    overlay?.classList.add('show');
    document.body.classList.add('admin-drawer-open');
    menuButton?.setAttribute('aria-expanded', 'true');
    sidebar?.querySelector('a')?.focus({ preventScroll: true });
  };

  menuButton?.addEventListener('click', () => {
    if (isMobile()) {
      sidebar?.classList.contains('open') ? closeSidebar() : openSidebar();
      return;
    }
    document.body.classList.toggle('admin-collapsed');
  });

  overlay?.addEventListener('click', closeSidebar);
  closeButton?.addEventListener('click', closeSidebar);
  sidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeSidebar));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeSidebar();
      profileMenu?.classList.remove('open');
      profileTrigger?.setAttribute('aria-expanded', 'false');
    }
  });

  profileTrigger?.addEventListener('click', () => {
    const open = !profileMenu?.classList.contains('open');
    profileMenu?.classList.toggle('open', open);
    profileTrigger.setAttribute('aria-expanded', String(open));
  });
  document.addEventListener('click', (event) => {
    if (!profileMenu?.contains(event.target)) {
      profileMenu?.classList.remove('open');
      profileTrigger?.setAttribute('aria-expanded', 'false');
    }
  });

  const chart = document.getElementById('applicationsChart');
  if (chart && window.Chart) {
    const labels = JSON.parse(chart.dataset.labels || '[]');
    const values = JSON.parse(chart.dataset.values || '[]');
    if (!values.some((value) => Number(value) > 0)) {
      chart.closest('.chart-frame')?.insertAdjacentHTML('beforeend', '<div class="empty-state compact">No application data for this period yet.</div>');
    }
    new Chart(chart, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Membership applications',
          data: values,
          borderColor: '#c8102e',
          backgroundColor: 'rgba(200,16,46,.12)',
          fill: true,
          tension: 0.35,
          pointRadius: 4,
          pointBackgroundColor: '#c8102e',
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (context) => `${context.parsed.y} applications` } } },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 } },
          x: { grid: { display: false } },
        },
      },
    });
  }

  const params = new URLSearchParams(window.location.search);
  if (params.has('updated') || params.has('saved') || params.has('password_changed')) {
    document.querySelector('.admin-table tbody tr, .panel')?.classList.add('updated');
  }

  document.querySelectorAll('.password-toggle').forEach((button) => {
    button.addEventListener('click', () => {
      const input = button.closest('.password-input')?.querySelector('input');
      if (!input) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.textContent = show ? 'Hide' : 'Show';
      button.setAttribute('aria-label', `${show ? 'Hide' : 'Show'} password`);
    });
  });

  const newPassword = document.querySelector('input[name="new_password"]');
  const rules = document.querySelectorAll('.password-rules [data-rule]');
  const updateRules = () => {
    const value = newPassword?.value || '';
    const checks = {
      length: value.length >= 12,
      upper: /[A-Z]/.test(value),
      lower: /[a-z]/.test(value),
      number: /[0-9]/.test(value),
      special: /[^A-Za-z0-9]/.test(value),
    };
    rules.forEach((rule) => rule.classList.toggle('met', Boolean(checks[rule.dataset.rule])));
  };
  newPassword?.addEventListener('input', updateRules);
  updateRules();

  document.querySelectorAll('form[data-prevent-duplicate], .admin-form').forEach((form) => {
    form.addEventListener('submit', () => {
      const button = form.querySelector('button[type="submit"], button:not([type]), button[name="action"]:focus');
      if (!button || button.disabled) return;
      button.dataset.originalText = button.textContent;
      button.textContent = button.dataset.loadingText || 'Working...';
      button.disabled = true;
    });
  });

  document.querySelectorAll('form[data-change-submit]').forEach((form) => {
    const button = form.querySelector('button[type="submit"], button:not([type])');
    const fields = Array.from(form.querySelectorAll('input:not([type="hidden"]), select, textarea'));
    const initial = fields.map((field) => field.value).join('\u001f');
    const update = () => {
      if (button) button.disabled = fields.map((field) => field.value).join('\u001f') === initial;
    };
    fields.forEach((field) => field.addEventListener('input', update));
    fields.forEach((field) => field.addEventListener('change', update));
    update();
  });

  const confirmAction = (message) => new Promise((resolve) => {
    const modal = document.createElement('div');
    modal.className = 'confirm-modal';
    modal.innerHTML = `<div class="confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="confirmTitle"><h2 id="confirmTitle">Confirm action</h2><p>${message}</p><div class="confirm-actions"><button type="button" class="btn secondary" data-cancel>Cancel</button><button type="button" class="btn danger" data-confirm-ok>Confirm</button></div></div>`;
    document.body.appendChild(modal);
    const done = (value) => {
      modal.remove();
      resolve(value);
    };
    modal.querySelector('[data-cancel]')?.addEventListener('click', () => done(false));
    modal.querySelector('[data-confirm-ok]')?.addEventListener('click', () => done(true));
    modal.addEventListener('click', (event) => {
      if (event.target === modal) done(false);
    });
    modal.querySelector('[data-cancel]')?.focus();
  });

  document.querySelectorAll('button[data-confirm]').forEach((button) => {
    button.addEventListener('click', async (event) => {
      if (button.dataset.confirmed === 'true') return;
      event.preventDefault();
      const ok = await confirmAction(button.dataset.confirm || 'Continue?');
      if (!ok) return;
      button.dataset.confirmed = 'true';
      button.click();
    });
  });
});

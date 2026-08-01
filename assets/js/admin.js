document.addEventListener('DOMContentLoaded', () => {
  const menuButton = document.querySelector('.admin-menu-button');
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.admin-overlay');
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
  sidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeSidebar));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeSidebar();
  });

  const chart = document.getElementById('applicationsChart');
  if (chart && window.Chart) {
    const labels = JSON.parse(chart.dataset.labels || '[]');
    const values = JSON.parse(chart.dataset.values || '[]');
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
        plugins: { legend: { display: false } },
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
});

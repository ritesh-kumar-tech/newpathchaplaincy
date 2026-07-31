document.addEventListener('DOMContentLoaded', () => {
  const menuButton = document.querySelector('.admin-menu-button');
  const sidebar = document.querySelector('.sidebar');
  menuButton?.addEventListener('click', () => {
    if (window.matchMedia('(max-width: 760px)').matches) {
      sidebar?.classList.toggle('open');
      return;
    }
    document.body.classList.toggle('admin-expanded');
  });

  document.addEventListener('click', (event) => {
    if (!sidebar?.classList.contains('open')) return;
    if (sidebar.contains(event.target) || menuButton.contains(event.target)) return;
    sidebar.classList.remove('open');
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
});

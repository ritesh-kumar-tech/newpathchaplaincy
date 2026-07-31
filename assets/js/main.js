document.addEventListener('DOMContentLoaded', () => {
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const year = document.getElementById('year');
  if (year) year.textContent = new Date().getFullYear();

  const header = document.querySelector('.site-header');
  const toggle = document.querySelector('.menu-toggle');
  const links = document.querySelector('.nav-links');
  const toast = document.querySelector('.toast');
  const themeToggle = document.querySelector('.theme-toggle');

  const preferredTheme = localStorage.getItem('ngcn_theme');
  const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const applyTheme = (theme) => {
    const dark = theme === 'dark';
    document.documentElement.classList.remove('theme-preload-dark');
    document.body.classList.toggle('public-dark', dark);
    if (themeToggle) {
      themeToggle.setAttribute('aria-pressed', String(dark));
      themeToggle.setAttribute('aria-label', dark ? 'Switch to light theme' : 'Switch to dark theme');
    }
  };
  applyTheme(preferredTheme || (systemDark ? 'dark' : 'light'));
  themeToggle?.addEventListener('click', () => {
    const next = document.body.classList.contains('public-dark') ? 'light' : 'dark';
    localStorage.setItem('ngcn_theme', next);
    applyTheme(next);
  });

  const setScrolled = () => header?.classList.toggle('scrolled', window.scrollY > 24);
  setScrolled();
  window.addEventListener('scroll', setScrolled, { passive: true });

  toggle?.addEventListener('click', () => {
    const open = !links.classList.contains('open');
    links.classList.toggle('open', open);
    toggle.classList.toggle('open', open);
    toggle.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('menu-open', open);
  });

  links?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => {
    links.classList.remove('open');
    toggle?.classList.remove('open');
    toggle?.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('menu-open');
  }));

  const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('in-view');
        revealObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.14 });

  document.querySelectorAll('.reveal').forEach((el) => {
    if (reducedMotion) el.classList.add('in-view');
    else revealObserver.observe(el);
  });

  const navLinks = [...document.querySelectorAll('.nav-links a[href^="#"]')];
  const sections = navLinks.map((link) => document.querySelector(link.getAttribute('href'))).filter(Boolean);
  const navObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        navLinks.forEach((link) => link.classList.toggle('active', link.getAttribute('href') === `#${entry.target.id}`));
      }
    });
  }, { rootMargin: '-35% 0px -55% 0px' });
  sections.forEach((section) => navObserver.observe(section));

  const statObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      const strong = entry.target.querySelector('[data-count]');
      if (strong && !strong.dataset.done) {
        strong.dataset.done = 'true';
        const target = Number(strong.dataset.count);
        let current = 0;
        const step = () => {
          current += Math.max(1, Math.ceil(target / 28));
          if (current >= target) {
            strong.textContent = target === 24 ? '24/7' : 'Global';
            return;
          }
          strong.textContent = String(current);
          requestAnimationFrame(step);
        };
        if (reducedMotion) strong.textContent = target === 24 ? '24/7' : 'Global';
        else step();
      }
      statObserver.unobserve(entry.target);
    });
  }, { threshold: 0.45 });
  document.querySelectorAll('.hero-stats div').forEach((stat) => statObserver.observe(stat));

  if (!reducedMotion) {
    const parallaxCard = document.querySelector('[data-parallax]');
    document.querySelector('.hero')?.addEventListener('mousemove', (event) => {
      if (!parallaxCard) return;
      const rect = event.currentTarget.getBoundingClientRect();
      const x = (event.clientX - rect.left) / rect.width - 0.5;
      const y = (event.clientY - rect.top) / rect.height - 0.5;
      parallaxCard.style.transform = `rotateX(${y * -5}deg) rotateY(${x * 6}deg) translateY(-2px)`;
    });
    document.querySelector('.hero')?.addEventListener('mouseleave', () => {
      if (parallaxCard) parallaxCard.style.transform = '';
    });
  }

  const showToast = (message) => {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    window.setTimeout(() => toast.classList.remove('show'), 4200);
  };

  fetch('api/csrf.php', { credentials: 'same-origin' })
    .then((response) => response.json())
    .then((data) => {
      document.querySelectorAll('input[name="csrf_token"]').forEach((input) => { input.value = data.token || ''; });
    })
    .catch(() => {});

  const clearErrors = (form) => {
    form.querySelectorAll('.field-error').forEach((label) => label.classList.remove('field-error'));
    form.querySelectorAll('.field-message').forEach((msg) => msg.remove());
  };

  const markError = (form, name, message) => {
    const field = form.querySelector(`[name="${CSS.escape(name)}"]`);
    const label = field?.closest('label');
    if (!label) return;
    label.classList.add('field-error');
    const msg = document.createElement('small');
    msg.className = 'field-message';
    msg.textContent = message;
    label.appendChild(msg);
  };

  const validate = (form, scope = form) => {
    clearErrors(form);
    const errors = {};
    scope.querySelectorAll('[required]').forEach((field) => {
      if (!String(field.value).trim()) errors[field.name] = 'This field is required.';
    });
    const email = scope.querySelector('[type="email"]');
    if (email?.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) errors[email.name] = 'Enter a valid email address.';
    Object.entries(errors).forEach(([name, message]) => markError(form, name, message));
    return Object.keys(errors).length === 0;
  };

  document.querySelectorAll('form[data-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const note = form.querySelector('.form-note');
      const button = form.querySelector('button[type="submit"]');
      note.textContent = '';
      note.classList.remove('success');
      if (!validate(form)) {
        note.textContent = 'Please correct the highlighted fields.';
        return;
      }
      button.classList.add('loading');
      button.disabled = true;
      try {
        const response = await fetch(form.dataset.endpoint, {
          method: 'POST',
          body: new FormData(form),
          credentials: 'same-origin',
        });
        const result = await response.json();
        clearErrors(form);
        if (!result.ok) {
          Object.entries(result.errors || {}).forEach(([name, message]) => markError(form, name, message));
          note.textContent = result.message || 'Submission failed. Please try again.';
          return;
        }
        form.reset();
        note.textContent = result.message;
        note.classList.add('success');
        showToast(result.message);
        const csrf = await fetch('api/csrf.php', { credentials: 'same-origin' }).then((res) => res.json());
        form.querySelector('[name="csrf_token"]').value = csrf.token || '';
      } catch (error) {
        note.textContent = 'We could not submit the form right now. Please try again.';
      } finally {
        button.classList.remove('loading');
        button.disabled = false;
      }
    });
  });

  document.querySelectorAll('.progressive-form').forEach((form) => {
    const steps = [...form.querySelectorAll('.form-step')];
    const dots = [...form.querySelectorAll('.step-dots span')];
    const prev = form.querySelector('.step-prev');
    const next = form.querySelector('.step-next');
    const submit = form.querySelector('.submit-step');
    let index = 0;
    const render = () => {
      steps.forEach((step, i) => step.classList.toggle('active', i === index));
      dots.forEach((dot, i) => dot.classList.toggle('active', i <= index));
      prev.style.visibility = index === 0 ? 'hidden' : 'visible';
      next.style.display = index === steps.length - 1 ? 'none' : 'inline-flex';
      submit.style.display = index === steps.length - 1 ? 'inline-flex' : 'none';
      form.querySelector('.form-note').textContent = '';
    };
    next?.addEventListener('click', () => {
      if (!validate(form, steps[index])) return;
      index = Math.min(index + 1, steps.length - 1);
      render();
    });
    prev?.addEventListener('click', () => {
      index = Math.max(index - 1, 0);
      clearErrors(form);
      render();
    });
    render();
  });

  fetch('api/training.php')
    .then((response) => response.ok ? response.json() : null)
    .then((data) => {
      if (!data?.ok || !Array.isArray(data.modules) || data.modules.length !== 4) return;
      document.querySelectorAll('#trainingCards article').forEach((card, index) => {
        const module = data.modules[index];
        if (!module) return;
        card.querySelector('span').textContent = String(module.step_number).padStart(2, '0');
        card.querySelector('h3').textContent = module.title;
        card.querySelector('p').textContent = module.description;
      });
    })
    .catch(() => {});

  fetch('api/settings.php')
    .then((response) => response.ok ? response.json() : null)
    .then((data) => {
      if (!data?.ok) return;
      Object.entries(data.settings || {}).forEach(([key, value]) => {
        document.querySelectorAll(`[data-setting="${key}"]`).forEach((el) => { el.textContent = value; });
      });
    })
    .catch(() => {});
});

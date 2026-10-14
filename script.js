(() => {
  const header = document.querySelector('.site-header');
  const menuToggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.primary-nav');
  const backToTop = document.querySelector('.back-to-top');
  const year = document.querySelector('#year');
  const form = document.querySelector('#franchise-form');
  const message = document.querySelector('#form-message');
  const steps = [...document.querySelectorAll('.form-step')];
  const progress = [...document.querySelectorAll('.progress-step')];
  let currentStep = 1;

  if (year) year.textContent = new Date().getFullYear();

  const onScroll = () => {
    const scrolled = window.scrollY > 24;
    header?.classList.toggle('scrolled', scrolled);
    backToTop?.classList.toggle('visible', window.scrollY > 550);
  };
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  const closeMenu = () => {
    nav?.classList.remove('open');
    menuToggle?.setAttribute('aria-expanded', 'false');
    menuToggle?.setAttribute('aria-label', 'Open navigation menu');
    document.body.classList.remove('menu-open');
  };
  menuToggle?.addEventListener('click', () => {
    const open = menuToggle.getAttribute('aria-expanded') !== 'true';
    menuToggle.setAttribute('aria-expanded', String(open));
    menuToggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
    nav?.classList.toggle('open', open);
    document.body.classList.toggle('menu-open', open);
  });
  nav?.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMenu));
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });
  backToTop?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

  const revealItems = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    revealItems.forEach(item => observer.observe(item));
  } else {
    revealItems.forEach(item => item.classList.add('is-visible'));
  }

  function showStep(step) {
    currentStep = step;
    steps.forEach(panel => panel.classList.toggle('active', Number(panel.dataset.step) === step));
    progress.forEach(item => {
      const pStep = Number(item.dataset.progress);
      item.classList.toggle('active', pStep === step);
      item.setAttribute('aria-selected', String(pStep === step));
    });
    message.className = 'form-message';
    message.textContent = '';
    const cardTop = form.getBoundingClientRect().top + window.scrollY - 90;
    if (window.scrollY > cardTop + 150) window.scrollTo({ top: cardTop, behavior: 'smooth' });
  }

  function validateStep(step) {
    const panel = steps.find(item => Number(item.dataset.step) === step);
    if (!panel) return true;
    const fields = [...panel.querySelectorAll('input,select,textarea')].filter(el => el.type !== 'checkbox' && el.type !== 'radio');
    for (const field of fields) {
      if (!field.checkValidity()) {
        field.reportValidity();
        field.focus();
        return false;
      }
    }
    if (step === 4 && !document.querySelector('#consent')?.checked) {
      document.querySelector('#consent')?.focus();
      message.textContent = 'Please confirm the declaration and provide consent before submitting.';
      message.className = 'form-message show error';
      return false;
    }
    return true;
  }

  document.querySelectorAll('[data-next]').forEach(btn => {
    btn.addEventListener('click', () => {
      if (validateStep(currentStep)) {
        showStep(Math.min(currentStep + 1, steps.length));
      }
    });
  });

  document.querySelectorAll('[data-back]').forEach(btn => {
    btn.addEventListener('click', () => {
      showStep(Math.max(currentStep - 1, 1));
    });
  });

  progress.forEach(tab => {
    tab.addEventListener('click', () => {
      const target = Number(tab.dataset.progress);
      if (target === currentStep) return;
      if (target < currentStep || validateStep(currentStep)) {
        showStep(target);
      }
    });
  });

  // WhatsApp auto-fill helper
  const sameWhatsapp = document.querySelector('#sameWhatsapp');
  const mobileInput = document.querySelector('#mobile');
  const whatsappInput = document.querySelector('#whatsapp');
  if (sameWhatsapp && mobileInput && whatsappInput) {
    sameWhatsapp.addEventListener('change', () => {
      if (sameWhatsapp.checked) {
        whatsappInput.value = mobileInput.value;
        whatsappInput.readOnly = true;
      } else {
        whatsappInput.readOnly = false;
      }
    });
    mobileInput.addEventListener('input', () => {
      if (sameWhatsapp.checked) {
        whatsappInput.value = mobileInput.value;
      }
    });
  }

  form?.addEventListener('submit', async event => {
    event.preventDefault();
    if (!validateStep(4)) return;
    const formData = new FormData(form);
    const data = {};
    for (const [key, val] of formData.entries()) {
      if (data[key]) {
        if (Array.isArray(data[key])) {
          data[key].push(val);
        } else {
          data[key] = [data[key], val];
        }
      } else {
        data[key] = val;
      }
    }
    data.consent = document.querySelector('#consent')?.checked || false;

    const endpoint = form.dataset.endpoint;
    if (!endpoint) {
      const randomRef = 'SS/KL/EQ/' + Math.floor(1000 + Math.random() * 9000);
      message.innerHTML = `<strong>Enquiry captured (Demo Mode)</strong><br>Reference: <code>${randomRef}</code><br>Thank you, ${data.fullName || 'Partner'}. All sections (A–E) verified. Configure <code>data-endpoint</code> on <code>&lt;form&gt;</code> to send directly to your CRM/backend.`;
      message.className = 'form-message show success';
      return;
    }
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.textContent = 'Submitting…';
    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(data)
      });
      if (!response.ok) throw new Error('Submission failed');
      form.reset();
      showStep(1);
      message.textContent = 'Thank you. Your franchise enquiry has been submitted successfully.';
      message.className = 'form-message show success';
    } catch (error) {
      message.textContent = 'We could not submit your enquiry. Please try again later or contact SleepSync directly.';
      message.className = 'form-message show error';
    } finally {
      submitButton.disabled = false;
      submitButton.innerHTML = originalText;
    }
  });
})();

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
    progress.forEach(item => item.classList.toggle('active', Number(item.dataset.progress) === step));
    message.className = 'form-message';
    message.textContent = '';
    const cardTop = form.getBoundingClientRect().top + window.scrollY - 100;
    if (window.scrollY > cardTop + 200) window.scrollTo({ top: cardTop, behavior: 'smooth' });
  }
  function validateStep(step) {
    const panel = steps.find(item => Number(item.dataset.step) === step);
    const fields = [...panel.querySelectorAll('input,select,textarea')].filter(el => el.type !== 'checkbox');
    for (const field of fields) {
      if (!field.checkValidity()) {
        field.reportValidity();
        field.focus();
        return false;
      }
    }
    if (step === 2 && !document.querySelector('#consent').checked) {
      document.querySelector('#consent').focus();
      message.textContent = 'Please provide consent before submitting your enquiry.';
      message.className = 'form-message show error';
      return false;
    }
    return true;
  }
  document.querySelector('[data-next]')?.addEventListener('click', () => {
    if (validateStep(1)) showStep(2);
  });
  document.querySelector('[data-back]')?.addEventListener('click', () => showStep(1));

  form?.addEventListener('submit', async event => {
    event.preventDefault();
    if (!validateStep(2)) return;
    const data = Object.fromEntries(new FormData(form).entries());
    data.consent = document.querySelector('#consent').checked;
    /*
      DEMO MODE: this static page has no backend endpoint configured.
      Replace this section with a POST request to your secure server-side API
      or a trusted form provider before publishing. Never put private API keys here.
    */
    const endpoint = form.dataset.endpoint;
    if (!endpoint) {
      message.textContent = 'The form is ready for integration, but no submission service is configured yet. Please connect a secure form endpoint before publishing.';
      message.className = 'form-message show error';
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
      message.textContent = 'Thank you. Your enquiry has been submitted successfully.';
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

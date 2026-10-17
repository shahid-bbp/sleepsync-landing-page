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
  let submitting = false;

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

  // Collapsible Franchise Details Section (Opportunity, Why SleepSync, Investment, How It Works)
  const detailsContainer = document.querySelector('#franchise-details');
  const toggleButtons = document.querySelectorAll('.toggle-details-btn');
  const detailsNavLinks = document.querySelectorAll('a[href="#opportunity"], a[href="#benefits"], a[href="#investment"], a[href="#process"]');

  function openDetails(targetAnchor = null) {
    if (!detailsContainer) return;
    detailsContainer.classList.add('is-open');
    detailsContainer.setAttribute('aria-hidden', 'false');
    toggleButtons.forEach(btn => {
      btn.setAttribute('aria-expanded', 'true');
      const textSpan = btn.querySelector('.btn-text');
      if (textSpan) {
        textSpan.textContent = btn.classList.contains('button-ghost-dark') ? 'Close franchise details' : 'Hide details';
      }
      const icon = btn.querySelector('.toggle-icon');
      if (icon) icon.textContent = '↑';
    });
    detailsContainer.querySelectorAll('.reveal').forEach(el => el.classList.add('is-visible'));

    if (targetAnchor) {
      setTimeout(() => {
        const el = document.querySelector(targetAnchor);
        if (el) {
          const top = el.getBoundingClientRect().top + window.scrollY - 85;
          window.scrollTo({ top, behavior: 'smooth' });
        }
      }, 60);
    }
  }

  function closeDetails() {
    if (!detailsContainer) return;
    detailsContainer.classList.remove('is-open');
    detailsContainer.setAttribute('aria-hidden', 'true');
    toggleButtons.forEach(btn => {
      btn.setAttribute('aria-expanded', 'false');
      const textSpan = btn.querySelector('.btn-text');
      if (textSpan) textSpan.textContent = 'Know more';
      const icon = btn.querySelector('.toggle-icon');
      if (icon) icon.textContent = '↓';
    });
    const toggleSection = document.querySelector('.details-toggle-section');
    if (toggleSection) {
      const top = toggleSection.getBoundingClientRect().top + window.scrollY - 85;
      window.scrollTo({ top, behavior: 'smooth' });
    }
  }

  toggleButtons.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const isOpen = detailsContainer?.classList.contains('is-open');
      if (isOpen) {
        closeDetails();
      } else {
        openDetails('#opportunity');
      }
    });
  });

  detailsNavLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      const href = link.getAttribute('href');
      if (href && href.startsWith('#')) {
        if (!detailsContainer?.classList.contains('is-open')) {
          e.preventDefault();
          openDetails(href);
        }
      }
    });
  });

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

  function showMessage(text, kind = 'error') {
    message.textContent = text;
    message.className = `form-message show ${kind}`;
  }

  function validateStep(step) {
    const panel = steps.find(item => Number(item.dataset.step) === step);
    if (!panel) return true;
    const fields = [...panel.querySelectorAll('input,select,textarea')];
    const businessChoices = [...panel.querySelectorAll('[name="currentBusiness"]')];
    if (businessChoices.length) {
      businessChoices[0].setCustomValidity(businessChoices.some(field => field.checked)
        ? '' : 'Please select at least one current business category.');
    }
    for (const field of fields) {
      if (field.type !== 'checkbox' && field.type !== 'radio') {
        field.setCustomValidity('');
        if (field.required && !field.value.trim()) {
          field.setCustomValidity('Please complete this required field.');
        } else if (field.maxLength > 0 && new TextEncoder().encode(field.value).length > field.maxLength) {
          field.setCustomValidity('Please shorten this field. The text exceeds the allowed length.');
        } else if (field.type === 'tel' && field.value.replace(/\D/g, '').length < 8) {
          field.setCustomValidity('Please enter a phone number with at least 8 digits.');
        }
      }
      if (!field.checkValidity()) {
        showStep(step);
        showMessage(field.validationMessage);
        field.focus();
        field.reportValidity();
        return false;
      }
    }
    return true;
  }

  function goToStep(target) {
    if (submitting || target === currentStep) return;
    if (target > currentStep) {
      for (let step = 1; step < target; step += 1) {
        if (!validateStep(step)) return;
      }
    }
    showStep(target);
  }

  document.querySelectorAll('[data-next]').forEach(btn => {
    btn.addEventListener('click', () => goToStep(Math.min(currentStep + 1, steps.length)));
  });
  document.querySelectorAll('[data-back]').forEach(btn => {
    btn.addEventListener('click', () => goToStep(Math.max(currentStep - 1, 1)));
  });
  progress.forEach(tab => {
    tab.addEventListener('click', () => goToStep(Number(tab.dataset.progress)));
    tab.addEventListener('keydown', event => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        goToStep(Number(tab.dataset.progress));
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
    if (submitting) return;
    for (let step = 1; step <= steps.length; step += 1) {
      if (!validateStep(step)) return;
    }
    let endpoint;
    try {
      endpoint = new URL(form.dataset.endpoint);
      if (endpoint.protocol !== 'https:' || endpoint.username || endpoint.password) throw new Error();
    } catch {
      showMessage('The enquiry service is unavailable. Please email web@thesleepsync.in.');
      return;
    }
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    data.currentBusiness = formData.getAll('currentBusiness');
    data.consent = form.querySelector('#consent').checked;

    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    const controls = [...form.elements].map(element => [element, element.disabled]);
    submitting = true;
    controls.forEach(([element]) => { element.disabled = true; });
    progress.forEach(tab => tab.setAttribute('aria-disabled', 'true'));
    form.setAttribute('aria-busy', 'true');
    submitButton.textContent = 'Submitting?';
    showMessage('Sending your enquiry. Please keep this page open.', 'pending');
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 65000);
    let invalidField = null;
    try {
      const response = await fetch(endpoint.href, {
        method: 'POST',
        mode: 'cors',
        credentials: 'omit',
        redirect: 'error',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(data),
        signal: controller.signal
      });
      let result;
      try {
        result = await response.json();
      } catch {
        throw new Error('We could not confirm submission. Please contact web@thesleepsync.in before retrying. Your details are still on this page.');
      }
      if (response.status !== 200 || result?.ok !== true || result.code !== 'SMTP_ACCEPTED' ||
          !/^SS-[A-F0-9]{16}$/.test(result.reference || '')) {
        if (response.status === 422 && result?.errors && typeof result.errors === 'object') {
          const fieldName = Object.keys(result.errors).find(name => [...form.elements].some(el => el.name === name));
          invalidField = [...form.elements].find(el => el.name === fieldName);
          if (invalidField) showStep(Number(invalidField.closest('.form-step').dataset.step));
          throw new Error(typeof result.errors[fieldName] === 'string' ? result.errors[fieldName] : 'Please review the form and try again.');
        }
        if (response.status === 429) {
          const retry = Number(response.headers.get('Retry-After'));
          throw new Error(Number.isFinite(retry) && retry > 0
            ? `Too many attempts. Please wait ${Math.ceil(retry / 60)} minute(s), or email web@thesleepsync.in.`
            : 'Too many attempts. Please wait before trying again, or email web@thesleepsync.in.');
        }
        throw new Error(typeof result?.message === 'string' && result.message.length <= 500
          ? result.message : 'We could not confirm submission. Please contact web@thesleepsync.in before retrying.');
      }
      form.reset();
      if (whatsappInput) whatsappInput.readOnly = false;
      showStep(1);
      showMessage(`Thank you! Your franchise enquiry has been submitted successfully.`, 'success');
    } catch (error) {
      const networkFailure = error.name === 'AbortError' || error instanceof TypeError;
      showMessage(networkFailure
        ? 'We could not confirm submission because the connection failed or timed out. Your details are still on this page. Please contact web@thesleepsync.in before retrying.'
        : error.message);
    } finally {
      clearTimeout(timeout);
      submitting = false;
      controls.forEach(([element, wasDisabled]) => { element.disabled = wasDisabled; });
      progress.forEach(tab => tab.removeAttribute('aria-disabled'));
      form.removeAttribute('aria-busy');
      submitButton.innerHTML = originalText;
      if (invalidField) invalidField.focus();
      else message.focus({ preventScroll: true });
      message.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest' });
    }
  });
})();

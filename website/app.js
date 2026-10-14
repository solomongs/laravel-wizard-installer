(() => {
  'use strict';

  const stage = document.getElementById('demo-stage');
  const nextButton = document.getElementById('demo-next');
  const progress = document.getElementById('demo-progress');
  const count = document.getElementById('demo-stage-count');
  const stepItems = [...document.querySelectorAll('.demo-steps li')];
  const toast = document.getElementById('toast');
  let currentStep = 0;
  let profile = '20i';
  let blocked = false;
  let busy = false;
  let runId = 0;
  let toastTimer;
  const icon = (id) => `<svg class="icon" aria-hidden="true"><use href="#i-${id}"/></svg>`;
  const profiles = {
    '20i': { label: '20i / StackCP', host: 'mysql.example.20i.com', hint: 'Copy the database server hostname from StackCP. Set your domain’s document root to the Laravel public folder.' },
    cpanel: { label: 'cPanel', host: 'localhost', hint: 'Copy full database and user names, including your account prefix. Confirm the database hostname with your provider; it may differ from localhost.' },
    other: { label: 'Other compatible hosting', host: 'db.example.test', hint: 'Use the database hostname your host provides. Confirm PHP support and a safe public document root before upload.' }
  };
  const check = (label, result = 'READY') => `<div class="sample-check">${icon('check')}<span>${label}</span><span class="sample-result">${result}</span></div>`;
  const screens = [
    () => `<span class="step-label">01 / PRIVATE ACCESS</span><h3 tabindex="-1">A private start.</h3><p>Your real installer requires the setup token created by your developer. For this demo, we’ll use a fictional token and skip entering any private information.</p><div class="sample-token">${icon('lock')}<span><strong>Demo token supplied</strong><code>•••• •••• ••••</code></span></div><p class="sample-note">Your token is a private key to the installation. Never share it publicly or put it in your GitHub repository.</p>`,
    () => `<span class="step-label">02 / HOSTING PROFILE</span><h3 tabindex="-1">Where will your site live?</h3><p>The hosting profile gives you useful tips for your control panel. It does not change your hosting account or provision a server.</p><label for="demo-host">Choose a sample hosting profile</label><select id="demo-host">${Object.entries(profiles).map(([key, value]) => `<option value="${key}"${profile === key ? ' selected' : ''}>${value.label}</option>`).join('')}</select><div class="profile-summary">${icon('server')}<p id="profile-hint">${profiles[profile].hint}</p></div><p class="sample-note" style="margin-top:18px">No technical experience? Ask your developer which profile matches the hosting you purchased.</p>`,
    () => `<span class="step-label">03 / READINESS CHECKS</span><h3 tabindex="-1">Check before you continue.</h3><p>The real wizard checks the environment and explains how to fix anything that blocks installation. These are fictional results.</p><div class="sample-checks">${check('Project PHP requirements')}${check('Required PHP extensions')}${check('Production dependencies')}${blocked ? `<div class="sample-check">${icon('server')}<span>Writable Laravel storage</span><span class="sample-result" style="color:#986b34">BLOCKED</span></div>` : check('Writable Laravel storage')}${check('Safe public folder')}</div>${blocked ? `<p class="sample-note" style="margin-top:14px">Sample issue: Laravel cannot write to storage. In your file manager, check folder ownership and make storage writable using your host’s recommended permissions. Avoid making every file writable by everyone.</p><button type="button" class="reset-button" id="demo-retry">Simulate fixing permissions & recheck ↻</button>` : `<button type="button" class="reset-button" id="demo-blocked">Preview a blocked check ↗</button>`}`,
    () => `<span class="step-label">04 / SITE & DATABASE</span><h3 tabindex="-1">Give your website a home.</h3><p>On your real site, enter the database details from your control panel. This demo uses fixed fictional settings and does not connect to anything.</p><dl class="sample-settings"><div><dt>Website name</dt><dd>My Laravel Website</dd></div><div><dt>Website address</dt><dd>https://example.test</dd></div><div><dt>Database hostname</dt><dd>${profiles[profile].host}</dd></div><div><dt>Database name</dt><dd>demo_website</dd></div><div><dt>Database username</dt><dd>demo_user</dd></div><div><dt>Database password</dt><dd>Fictional demo value</dd></div></dl><p class="sample-note">The real installer tests the connection before running migrations. Create your database and assign a user in your hosting panel first.</p>`,
    () => `<span class="step-label">05 / INSTALL YOUR PROJECT</span><h3 tabindex="-1">Every step has a status.</h3><p>The actual installer runs your project’s migrations one HTTP step at a time. Here, three fictional migrations illustrate the progress.</p><div class="migration-list">${['Create users table', 'Create pages table', 'Create settings table'].map((name, index) => `<div class="migration-row" data-migration="${index}">${icon('server')}<span>${name}</span><span>Waiting</span></div>`).join('')}</div><p class="sample-note" style="margin-top:20px">Administrator setup is project-specific. A developer must implement the setup hook if your application needs a first admin account.</p>`,
    () => `<span class="step-label">06 / READY TO LAUNCH</span><div class="success-emblem">${icon('check')}</div><h3 tabindex="-1">That’s a confident start.</h3><p>The sample installation is complete. On your real site, completion writes a permanent installation lock, closing the wizard to repeat setup.</p><div class="sample-checks">${check('Sample migrations', 'COMPLETE')}${check('Installation lock', 'SIMULATED')}</div><div class="completion-links"><a href="#hosting">Read your hosting guide ↗</a><a href="https://github.com/solomongs/laravel-wizard-installer">Get the package ↗</a></div><p class="sample-note">This was a demo. No files, database tables, or hosting settings were created or changed.</p>`
  ];
  const labels = ['Start sample setup', 'Continue to checks', 'Continue to settings', 'Test sample connection', 'Run sample installation', 'Try again'];

  function setNextLabel(label) {
    nextButton.innerHTML = `${label}${icon('arrow')}`;
  }

  function render(moveFocus = false) {
    stage.innerHTML = screens[currentStep]();
    progress.style.width = `${((currentStep + 1) / screens.length) * 100}%`;
    count.textContent = `Step ${currentStep + 1} of ${screens.length}`;
    stepItems.forEach((item, index) => {
      item.classList.toggle('current', index === currentStep);
      item.classList.toggle('complete', index < currentStep);
      if (index === currentStep) item.setAttribute('aria-current', 'step');
      else item.removeAttribute('aria-current');
      item.querySelector('.step-index').textContent = index < currentStep ? '✓' : index + 1;
    });
    setNextLabel(labels[currentStep]);
    nextButton.disabled = blocked && currentStep === 2;
    stage.removeAttribute('aria-busy');
    if (moveFocus) {
      stage.querySelector('h3').focus({ preventScroll: true });
      if (window.innerWidth <= 700) stage.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    }
  }

  function reset() {
    runId += 1;
    currentStep = 0;
    profile = '20i';
    blocked = false;
    busy = false;
    render(true);
  }

  const delay = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));

  async function advance() {
    if (busy || (currentStep === 2 && blocked)) return;
    if (currentStep === 5) {
      reset();
      return;
    }
    if (currentStep === 3 || currentStep === 4) {
      busy = true;
      const thisRun = ++runId;
      nextButton.disabled = true;
      stage.setAttribute('aria-busy', 'true');
      if (currentStep === 3) {
        setNextLabel('Testing fictional settings…');
        await delay(750);
        if (thisRun !== runId) return;
      } else {
        setNextLabel('Simulating installation…');
        for (let index = 0; index < 3; index += 1) {
          const row = stage.querySelector(`[data-migration="${index}"]`);
          if (!row || thisRun !== runId) return;
          row.classList.add('running');
          row.lastElementChild.textContent = 'Running';
          await delay(650);
          if (thisRun !== runId) return;
          row.classList.remove('running');
          row.firstElementChild.outerHTML = icon('check');
          row.lastElementChild.textContent = 'Complete';
        }
        await delay(300);
        if (thisRun !== runId) return;
      }
      busy = false;
    }
    currentStep += 1;
    render(true);
  }

  nextButton.addEventListener('click', advance);
  document.getElementById('reset-demo').addEventListener('click', reset);
  stage.addEventListener('change', (event) => {
    if (event.target.id !== 'demo-host') return;
    const value = event.target.value;
    if (Object.hasOwn(profiles, value)) {
      profile = value;
      document.getElementById('profile-hint').textContent = profiles[profile].hint;
    }
  });
  stage.addEventListener('click', (event) => {
    const action = event.target.closest('button');
    if (!action) return;
    if (action.id === 'demo-blocked' || action.id === 'demo-retry') {
      blocked = action.id === 'demo-blocked';
      render(true);
    }
  });

  const tabs = [...document.querySelectorAll('.host-tabs [role="tab"]')];
  function selectTab(tab, focus = false) {
    tabs.forEach((item) => {
      const selected = item === tab;
      item.setAttribute('aria-selected', String(selected));
      item.tabIndex = selected ? 0 : -1;
      document.getElementById(item.getAttribute('aria-controls')).hidden = !selected;
    });
    if (focus) tab.focus();
  }
  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => selectTab(tab));
    tab.addEventListener('keydown', (event) => {
      let destination;
      if (event.key === 'ArrowRight') destination = (index + 1) % tabs.length;
      if (event.key === 'ArrowLeft') destination = (index + tabs.length - 1) % tabs.length;
      if (event.key === 'Home') destination = 0;
      if (event.key === 'End') destination = tabs.length - 1;
      if (destination === undefined) return;
      event.preventDefault();
      selectTab(tabs[destination], true);
    });
  });

  function showToast(message) {
    window.clearTimeout(toastTimer);
    toast.textContent = message;
    toast.classList.add('visible');
    toastTimer = window.setTimeout(() => toast.classList.remove('visible'), 3200);
  }

  document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
      const code = document.getElementById(button.dataset.copy);
      try {
        if (!navigator.clipboard) throw new Error('Clipboard unavailable');
        await navigator.clipboard.writeText(code.textContent);
        showToast('Command copied. Run it from your Laravel project root.');
      } catch {
        const selection = window.getSelection();
        const range = document.createRange();
        range.selectNodeContents(code);
        selection.removeAllRanges();
        selection.addRange(range);
        showToast('Command selected. Use your device’s Copy action.');
      }
    });
  });

  const mobileLinks = [...document.querySelectorAll('.mobile-nav a')];
  const sections = mobileLinks.map((link) => document.querySelector(link.getAttribute('href')));
  let scrollPending = false;
  function updateMobileNavigation() {
    let active = 0;
    sections.forEach((section, index) => {
      if (section.getBoundingClientRect().top <= window.innerHeight * 0.4) active = index;
    });
    mobileLinks.forEach((link, index) => {
      if (index === active) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
    });
    scrollPending = false;
  }
  window.addEventListener('scroll', () => {
    if (!scrollPending) {
      scrollPending = true;
      window.requestAnimationFrame(updateMobileNavigation);
    }
  }, { passive: true });
  window.addEventListener('resize', updateMobileNavigation);
  render();
  updateMobileNavigation();

  // Published copy is plain text. Never interpret CMS content as HTML or code.
  async function loadPublishedContent() {
    try {
      const response = await fetch('content.json', { cache: 'no-cache' });
      if (!response.ok) return;
      const content = await response.json();
      if (content.schema_version !== 1 || !content.strings || typeof content.strings !== 'object') return;
      document.querySelectorAll('[data-content]').forEach((element) => {
        const value = content.strings[element.dataset.content];
        if (typeof value === 'string' && value.length > 0 && value.length <= 3000) element.textContent = value;
      });
      if (!Array.isArray(content.faq) || content.faq.length === 0 || content.faq.length > 25) return;
      const valid = content.faq.every((item) => item && typeof item.question === 'string' && typeof item.answer === 'string' && item.question.length > 0 && item.question.length <= 250 && item.answer.length > 0 && item.answer.length <= 3000);
      if (!valid) return;
      const fragment = document.createDocumentFragment();
      content.faq.forEach((item) => {
        const details = document.createElement('details');
        const summary = document.createElement('summary');
        const marker = document.createElement('span');
        const answer = document.createElement('p');
        summary.append(document.createTextNode(item.question));
        marker.setAttribute('aria-hidden', 'true');
        marker.textContent = '+';
        summary.append(marker);
        answer.textContent = item.answer;
        details.append(summary, answer);
        fragment.append(details);
      });
      document.getElementById('editable-faq').replaceChildren(fragment);
    } catch {
      // The complete HTML remains usable offline or if the content file is unavailable.
    }
  }
  loadPublishedContent();
})();

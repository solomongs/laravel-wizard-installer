(() => {
    'use strict';

    const $ = (id) => document.getElementById(id);
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const panels = [...document.querySelectorAll('[data-panel]')];
    const nav = [...document.querySelectorAll('[data-step]')];
    const titles = {
        1: ["Let's get you set up.", 'Your developer has prepared this website. Enter your setup code to begin.'],
        2: ['A good start begins here.', 'Choose your hosting panel. We will check that your server is ready for this project.'],
        3: ['Make it your website.', 'Add your website details and connect a new, empty MySQL database.'],
        4: ['Ready when you are.', 'Take a moment to review the details before setup changes your website configuration.'],
        5: ['One step closer to live.', 'These statuses come directly from the server as each installation stage runs.'],
        6: ['Your setup is complete.', 'The installation stages have finished, and the installer is locked.'],
    };
    const profiles = { '20i': 'Multi8 Hosting / StackCP', cpanel: 'cPanel', other: 'Another hosting panel' };
    const hostTips = {
        '20i': ['In StackCP, start with PHP and MySQL.', 'Choose the project’s PHP version, create a MySQL database, and copy its actual hostname. Point your domain to the Laravel public folder.'],
        cpanel: ['In cPanel, check PHP and create a database.', 'Use MultiPHP Manager or Select PHP Version, then MySQL Database Wizard. Include account prefixes in the database and user names.'],
        other: ['Keep your control panel nearby.', 'Your hosting provider can help you find PHP settings, MySQL databases, SSL, and the document root.'],
    };
    let state = { authenticated: false, profile: 'other', phase: 'prepared', checks: [], ready: false, steps: [], progress: { done: 0, total: 0, percent: 0 } };
    let current = 1;
    let reached = 1;
    let busy = false;
    let running = false;
    let lastGuide = 'other';
    let focusReturn = null;

    function announce(message) { $('announcement').textContent = message; }
    function showError(message) {
        $('error-message').textContent = typeof message === 'string' ? message : 'Setup could not complete this action. Refresh the status, then try again.';
        $('error-banner').hidden = false;
    }
    function clearError() { $('error-banner').hidden = true; $('error-message').textContent = ''; }

    function lockedPhase() { return ['configured', 'migrating', 'finalizing', 'failed', 'complete'].includes(state.phase); }

    function updateNavigation() {
        nav.forEach((button) => {
            const step = Number(button.dataset.step);
            const accessible = state.authenticated
                ? lockedPhase() ? step === (state.phase === 'complete' ? 6 : 5) : step >= 2 && step <= reached
                : step === 1;
            button.disabled = busy || !accessible;
            if (step === current) button.setAttribute('aria-current', 'step');
            else button.removeAttribute('aria-current');
            button.classList.toggle('is-done', step < current && state.authenticated);
        });
        $('hosting-continue').disabled = busy || !state.ready;
        $('resume-install').disabled = busy || running || state.phase === 'complete' || !state.authenticated;
    }

    function showStep(step, focus = true) {
        current = step;
        reached = Math.max(reached, step);
        panels.forEach((panel) => { panel.hidden = Number(panel.dataset.panel) !== step; });
        $('page-kicker').textContent = `STEP ${String(step).padStart(2, '0')} / 06`;
        $('page-title').textContent = titles[step][0];
        $('page-description').textContent = titles[step][1];
        updateNavigation();
        if (focus) { $('main').focus({ preventScroll: true }); window.scrollTo({ top: 0, behavior: 'auto' }); }
    }

    function setBusy(value, button) {
        busy = value;
        document.querySelectorAll('form input, form button, [data-back], #refresh-status, #resume-install, #toggle-password').forEach((control) => { control.disabled = value; });
        document.querySelectorAll('.button.is-busy').forEach((control) => control.classList.remove('is-busy'));
        if (value && button) button.classList.add('is-busy');
        $('main').setAttribute('aria-busy', String(value));
        updateNavigation();
    }

    async function request(action, data = {}) {
        let response;
        try {
            response = await fetch(window.location.pathname, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ action, csrf, ...data }),
            });
        } catch (_) {
            throw new Error('The connection was interrupted. Check your internet connection, then refresh the status. Reopen this setup address and use the same code if needed.');
        }
        let result;
        try { result = await response.json(); }
        catch (_) {
            if (response.status === 410) throw new Error('This installer is already locked. Open your website to continue.');
            throw new Error('The hosting server returned an unexpected response. Ask your developer to check the PHP version, deployment files, and server logs.');
        }
        if (result.data && typeof result.data === 'object') applyState(result.data);
        if (Array.isArray(result.checks)) { state.checks = result.checks; renderChecks(); }
        if (!response.ok || result.ok !== true) {
            if (['unauthenticated', 'authentication_required', 'token_expired', 'session_expired'].includes(result.code) || response.status === 401) {
                state.authenticated = false; showStep(1);
            }
            throw new Error(result.error || 'The server could not complete this action. Review the settings and try again.');
        }
        return result.data || {};
    }

    function renderHostTip(profile) {
        const tip = hostTips[profile] || hostTips.other;
        $('host-tip').querySelector('strong').textContent = tip[0];
        $('host-tip').querySelector('p').textContent = tip[1];
        $('db-host-help').textContent = profile === '20i'
            ? 'Copy the MySQL hostname shown in StackCP. It may be a remote server address; do not assume localhost.'
            : 'Use the hostname supplied by your provider. It may be a server address or localhost.';
    }

    function renderChecks() {
        const list = $('checks-list');
        list.replaceChildren();
        const checks = Array.isArray(state.checks) ? state.checks : [];
        if (!checks.length) {
            const empty = document.createElement('div'); empty.className = 'check-empty';
            empty.textContent = 'PHP version, extensions, folders, and deployment settings will appear here.';
            list.append(empty); $('checks-summary').textContent = 'Run a check to see what needs attention.'; return;
        }
        const fails = checks.filter((check) => check.status === 'fail').length;
        const warnings = checks.filter((check) => check.status === 'warn').length;
        $('checks-summary').textContent = fails ? `${fails} ${fails === 1 ? 'issue needs' : 'issues need'} attention before continuing.` : warnings ? `Required checks passed. Read ${warnings} ${warnings === 1 ? 'advice item' : 'advice items'} below.` : 'All returned checks passed.';
        checks.forEach((check) => {
            const status = ['pass', 'warn', 'fail'].includes(check.status) ? check.status : 'warn';
            const row = document.createElement('div'); row.className = 'check-item'; row.dataset.status = status;
            const icon = document.createElement('span'); icon.className = `check-symbol ${status}`; icon.setAttribute('aria-hidden', 'true'); icon.textContent = status === 'pass' ? '✓' : '!';
            const copy = document.createElement('div');
            const label = document.createElement('p'); label.className = 'check-title'; label.textContent = check.label || check.id || 'Hosting check'; copy.append(label);
            if (check.detail) { const detail = document.createElement('p'); detail.className = 'check-detail'; detail.textContent = check.detail; copy.append(detail); }
            if (check.help) { const help = document.createElement('div'); help.className = 'check-help'; help.textContent = check.help; copy.append(help); }
            const statusLabel = document.createElement('span'); statusLabel.className = 'check-label'; statusLabel.textContent = status === 'pass' ? 'Passed' : status === 'warn' ? 'Advice' : 'Needs attention';
            row.append(icon, copy, statusLabel); list.append(row);
        });
    }

    function renderInstallation() {
        const progress = state.progress || {};
        const percent = Math.min(100, Math.max(0, Number(progress.percent) || 0));
        $('install-progress').value = percent;
        $('install-progress').textContent = `${percent}%`;
        $('progress-percent').textContent = `${Math.round(percent)}%`;
        const total = Number(progress.total) || 0;
        const done = Number(progress.done) || 0;
        $('progress-label').textContent = total ? `${done} of ${total} installation stages complete` : 'Waiting for server progress';
        const failed = state.phase === 'failed';
        $('installation-badge').textContent = failed ? 'Needs attention' : state.phase === 'complete' ? 'Complete' : running ? 'Installing' : 'Ready to continue';
        $('installation-badge').className = `badge ${failed ? 'badge-danger' : state.phase === 'complete' ? 'badge-success' : 'badge-neutral'}`;
        $('installation-description').textContent = failed ? 'Setup paused. Read the server message below before retrying.' : 'Follow each stage as the server completes it.';
        $('resume-install').textContent = failed ? 'Retry installation step →' : 'Continue installation →';
        const list = $('installation-steps'); list.replaceChildren();
        (Array.isArray(state.steps) ? state.steps : []).forEach((step, index) => {
            const status = ['pending', 'running', 'done', 'failed'].includes(step.status) ? step.status : 'pending';
            const item = document.createElement('li'); item.className = 'installation-step'; item.dataset.status = status;
            const icon = document.createElement('span'); icon.className = 'stage-icon'; icon.setAttribute('aria-hidden', 'true');
            const number = document.createElement('span'); number.textContent = status === 'done' ? '✓' : status === 'failed' ? '!' : String(index + 1); icon.append(number);
            const label = document.createElement('span'); label.className = 'stage-label'; label.textContent = step.label || step.id || 'Installation stage';
            const statusLabel = document.createElement('span'); statusLabel.className = 'stage-state'; statusLabel.textContent = { pending: 'Waiting', running: 'In progress', done: 'Complete', failed: 'Paused' }[status];
            item.append(icon, label, statusLabel); list.append(item);
        });
        $('installation-message').textContent = state.message || (failed ? 'Setup paused. Refresh the status or ask your developer for help.' : 'The installer will report progress here.');
        if (state.phase === 'complete') {
            $('complete-message').textContent = state.message || '';
            if (typeof state.success_url === 'string') {
                try {
                    const target = new URL(state.success_url, window.location.href);
                    if (['https:', 'http:'].includes(target.protocol)) $('open-website').href = target.href;
                } catch (_) { /* Keep the safe default when a URL is malformed. */ }
            }
        }
    }

    function applyState(data) {
        state = { ...state, ...data };
        $('connection-label').textContent = state.authenticated ? 'Connected to your hosting' : 'Private website setup';
        $('connection-dot').className = `connection-dot${state.authenticated ? ' is-connected' : ''}`;
        if (state.authenticated) {
            const profile = Object.prototype.hasOwnProperty.call(profiles, state.profile) ? state.profile : 'other';
            const radio = document.querySelector(`input[name="profile"][value="${profile}"]`);
            if (radio) radio.checked = true;
            renderHostTip(profile);
            renderChecks(); renderInstallation();
            if (state.phase === 'complete') showStep(6, current !== 6);
            else if (lockedPhase()) showStep(5, current !== 5);
            else if (current === 1) showStep(2);
        }
        updateNavigation();
    }

    async function withBusy(button, work) {
        if (busy) return;
        clearError(); setBusy(true, button);
        try { await work(); }
        catch (error) { showError(error.message); }
        finally { setBusy(false); }
    }

    async function install() {
        if (running) return;
        running = true;
        renderInstallation(); updateNavigation();
        try {
            while (state.phase !== 'complete') {
                const before = JSON.stringify({ phase: state.phase, progress: state.progress, steps: state.steps });
                await request('run');
                announce(state.message || `Installation progress: ${Math.round(Number(state.progress?.percent) || 0)} percent.`);
                if (state.phase === 'failed') throw new Error(state.message || 'An installation stage failed. Review the status before retrying.');
                const after = JSON.stringify({ phase: state.phase, progress: state.progress, steps: state.steps });
                if (before === after) throw new Error('The server has not advanced to the next stage. Refresh the status before continuing.');
            }
        } finally { running = false; renderInstallation(); updateNavigation(); }
    }

    $('unlock-form').addEventListener('submit', (event) => {
        event.preventDefault();
        const token = $('setup-token').value.trim();
        withBusy(event.submitter, async () => {
            await request('login', { token });
            $('setup-token').value = '';
            announce('Setup unlocked. Choose your hosting panel and run the checks.');
        });
    });

    document.querySelectorAll('input[name="profile"]').forEach((radio) => radio.addEventListener('change', () => {
        renderHostTip(radio.value); state.ready = false; updateNavigation();
        $('checks-summary').textContent = 'Run the check again for this hosting panel.';
    }));

    $('host-form').addEventListener('submit', (event) => {
        event.preventDefault();
        const profile = new FormData(event.target).get('profile');
        withBusy(event.submitter, async () => {
            await request('profile', { profile }); await request('check');
            announce(state.ready ? 'Hosting checks are ready. You can continue.' : 'Some hosting checks need attention. Read the results below.');
        });
    });
    $('hosting-continue').addEventListener('click', () => { if (state.ready) { clearError(); showStep(3); } });
    $('configuration-form').addEventListener('submit', (event) => {
        event.preventDefault();
        if (!event.target.reportValidity()) return;
        const data = Object.fromEntries(new FormData(event.target));
        ['name', 'url', 'timezone', 'db_host', 'db_database', 'db_username'].forEach((field) => { $(`review-${field.replaceAll('_', '-')}`).textContent = data[field]; });
        $('review-profile').textContent = profiles[state.profile] || profiles.other;
        $('review-db-host').textContent = `${data.db_host}:${data.db_port}`;
        clearError(); showStep(4);
    });
    $('review-form').addEventListener('submit', (event) => {
        event.preventDefault();
        if (!$('confirm-empty').checked || !$('configuration-form').reportValidity()) return;
        const data = Object.fromEntries(new FormData($('configuration-form')));
        data.db_port = String(data.db_port); data.confirm_empty = true;
        withBusy(event.submitter, async () => {
            await request('configure', data);
            data.db_password = '';
            $('db-password').value = ''; $('db-password').type = 'password';
            $('toggle-password').textContent = 'Show'; $('toggle-password').setAttribute('aria-pressed', 'false');
            await install();
        });
    });
    $('toggle-password').addEventListener('click', () => {
        const visible = $('db-password').type === 'text';
        $('db-password').type = visible ? 'password' : 'text';
        $('toggle-password').textContent = visible ? 'Show' : 'Hide';
        $('toggle-password').setAttribute('aria-pressed', String(!visible));
    });
    $('resume-install').addEventListener('click', (event) => withBusy(event.currentTarget, install));
    $('refresh-status').addEventListener('click', (event) => withBusy(event.currentTarget, async () => { await request('status'); announce('Installation status refreshed.'); }));
    $('dismiss-error').addEventListener('click', clearError);
    document.querySelectorAll('[data-back]').forEach((button) => button.addEventListener('click', () => { clearError(); showStep(Number(button.dataset.back)); }));
    nav.forEach((button) => button.addEventListener('click', () => { if (!button.disabled) { clearError(); showStep(Number(button.dataset.step)); } }));

    function selectGuide(profile) {
        lastGuide = profile;
        document.querySelectorAll('[data-guide]').forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.guide === profile)));
        document.querySelectorAll('[data-guide-panel]').forEach((panel) => { panel.hidden = panel.dataset.guidePanel !== profile; });
    }
    function openGuide(event) {
        focusReturn = event.currentTarget;
        selectGuide(document.querySelector('input[name="profile"]:checked')?.value || lastGuide);
        const dialog = $('hosting-guide');
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
    }
    function closeGuide() {
        const dialog = $('hosting-guide');
        if (typeof dialog.close === 'function') dialog.close(); else dialog.removeAttribute('open');
        focusReturn?.focus();
    }
    ['open-guide', 'open-guide-bottom'].forEach((id) => $(id).addEventListener('click', openGuide));
    ['close-guide', 'guide-done'].forEach((id) => $(id).addEventListener('click', closeGuide));
    document.querySelectorAll('[data-guide]').forEach((button) => button.addEventListener('click', () => selectGuide(button.dataset.guide)));
    $('hosting-guide').addEventListener('click', (event) => { if (event.target === $('hosting-guide')) { const bounds = event.target.getBoundingClientRect(); if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) closeGuide(); } });

    $('app-url').value = window.location.origin;
    $('open-website').href = window.location.origin;
    try { $('app-timezone').value = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'; } catch (_) { $('app-timezone').value = 'UTC'; }
    if (typeof Intl.supportedValuesOf === 'function') {
        try { Intl.supportedValuesOf('timeZone').forEach((timezone) => { const option = document.createElement('option'); option.value = timezone; $('timezones').append(option); }); } catch (_) { /* The common timezone options remain available. */ }
    }
    showStep(1, false);
    withBusy(null, async () => { await request('status'); }).then(() => {
        if (!state.authenticated && !$('error-banner').hidden) { $('connection-label').textContent = 'Connection needs attention'; $('connection-dot').classList.add('is-error'); }
    });
    // Refresh paused or resumed installations without sending another install command.
    window.setInterval(() => {
        if (!document.hidden && !busy && !running && state.authenticated && lockedPhase() && state.phase !== 'complete') {
            withBusy($('refresh-status'), async () => { await request('status'); });
        }
    }, 12000);
})();

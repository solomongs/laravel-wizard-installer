(() => {
  'use strict';
  const copyFields = document.getElementById('copy-fields');
  const faqFields = document.getElementById('faq-fields');
  const status = document.getElementById('editor-status');
  const form = document.getElementById('content-form');
  const groups = [
    { title: 'Your first impression', description: 'Hero copy and primary actions.', fields: [
      ['hero.eyebrow', 'Intro label'], ['hero.titleLine1', 'Title: first line'], ['hero.emphasis', 'Title: italic word'], ['hero.titleLine2', 'Title: final phrase'], ['hero.description', 'Introduction'], ['hero.primaryAction', 'Primary button label'], ['hero.secondaryAction', 'Secondary link label']
    ] },
    { title: 'How the wizard works', description: 'Introduce the journey and the safe interactive demonstration.', fields: [
      ['how.title', 'How it works heading'], ['how.description', 'How it works introduction'], ['demo.title', 'Demo heading'], ['demo.description', 'Demo introduction']
    ] },
    { title: 'Helpful hosting guidance', description: 'Frame the guides and compatibility requirements accurately.', fields: [
      ['hosting.title', 'Hosting guides heading'], ['hosting.description', 'Hosting guides introduction'], ['requirements.title', 'Requirements heading'], ['requirements.description', 'Requirements introduction']
    ] },
    { title: 'Developers & final invitation', description: 'Developer introduction, FAQ heading, and closing call to action.', fields: [
      ['developer.title', 'Developers heading'], ['developer.description', 'Developers introduction'], ['faq.title', 'FAQ heading'], ['faq.description', 'FAQ introduction'], ['closing.title', 'Closing heading']
    ] }
  ];
  const allowedKeys = groups.flatMap((group) => group.fields.map(([key]) => key));
  let current;
  let loaded;
  const clone = (value) => JSON.parse(JSON.stringify(value));

  function report(message, error = false) {
    status.textContent = message;
    status.classList.toggle('error', error);
  }

  function validate(content) {
    if (!content || content.schema_version !== 1 || !content.strings || typeof content.strings !== 'object' || Array.isArray(content.strings)) throw new Error('Use a version 1 content.json exported by this editor.');
    for (const key of allowedKeys) {
      const value = content.strings[key];
      if (typeof value !== 'string' || !value.trim() || value.length > 3000) throw new Error(`The field “${key}” must contain 1–3,000 characters.`);
    }
    if (!Array.isArray(content.faq) || content.faq.length < 1 || content.faq.length > 25) throw new Error('Include between 1 and 25 FAQ entries.');
    content.faq.forEach((item, index) => {
      if (!item || typeof item.question !== 'string' || !item.question.trim() || item.question.length > 250 || typeof item.answer !== 'string' || !item.answer.trim() || item.answer.length > 3000) throw new Error(`FAQ ${index + 1} needs a question of 1–250 characters and an answer of 1–3,000 characters.`);
    });
    return { schema_version: 1, strings: Object.fromEntries(allowedKeys.map((key) => [key, content.strings[key]])), faq: content.faq.map(({ question, answer }) => ({ question, answer })) };
  }

  function textArea(key, label, value, maxLength = 3000) {
    const wrapper = document.createElement('div');
    wrapper.className = 'cms-field';
    const textLabel = document.createElement('label');
    textLabel.htmlFor = key;
    textLabel.textContent = label;
    const field = document.createElement('textarea');
    field.id = key;
    field.name = key;
    field.value = value;
    field.maxLength = maxLength;
    field.required = true;
    field.rows = value.length > 100 ? 4 : 2;
    wrapper.append(textLabel, field);
    return wrapper;
  }

  function buildFields() {
    copyFields.replaceChildren();
    groups.forEach((group, index) => {
      const section = document.createElement('section');
      section.className = 'cms-copy-group';
      const heading = document.createElement('h2');
      heading.textContent = group.title;
      const number = document.createElement('span');
      number.className = 'cms-group-number';
      number.textContent = `0${index + 1}`;
      const description = document.createElement('p');
      description.textContent = group.description;
      const grid = document.createElement('div');
      grid.className = 'cms-field-grid';
      group.fields.forEach(([key, label]) => grid.append(textArea(key, label, current.strings[key])));
      section.append(number, heading, description, grid);
      copyFields.append(section);
    });
    buildFaq();
    ['restore-content', 'export-top', 'export-bottom', 'add-faq'].forEach((id) => document.getElementById(id).disabled = false);
  }

  function buildFaq() {
    faqFields.replaceChildren();
    current.faq.forEach((entry, index) => {
      const card = document.createElement('div');
      card.className = 'cms-faq-card';
      const header = document.createElement('div');
      header.className = 'cms-faq-header';
      const heading = document.createElement('h3');
      heading.textContent = `Question ${index + 1}`;
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'reset-button';
      remove.textContent = 'Remove';
      remove.dataset.removeFaq = String(index);
      remove.setAttribute('aria-label', `Remove FAQ question ${index + 1}`);
      remove.disabled = current.faq.length === 1;
      header.append(heading, remove);
      card.append(header, textArea(`faq-question-${index}`, 'Question', entry.question, 250), textArea(`faq-answer-${index}`, 'Answer', entry.answer));
      faqFields.append(card);
    });
    document.getElementById('add-faq').disabled = current.faq.length >= 25;
  }

  function readFields() {
    if (!current) throw new Error('Load or import content.json first.');
    const strings = Object.fromEntries(allowedKeys.map((key) => [key, document.getElementById(key).value]));
    const faq = current.faq.map((_, index) => ({ question: document.getElementById(`faq-question-${index}`).value, answer: document.getElementById(`faq-answer-${index}`).value }));
    return { schema_version: 1, strings, faq };
  }

  function load(content, source) {
    current = validate(content);
    loaded = clone(current);
    buildFields();
    report(`${source} loaded. Changes stay in this browser until you download and publish the file.`);
  }

  function download() {
    try {
      if (!form.reportValidity()) return;
      const result = validate(readFields());
      const blob = new Blob([`${JSON.stringify(result, null, 2)}\n`], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'content.json';
      document.body.append(link);
      link.click();
      link.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 1000);
      report('content.json downloaded. Replace website/content.json in GitHub and commit to publish your changes.');
    } catch (error) {
      report(error.message, true);
    }
  }

  document.getElementById('export-top').addEventListener('click', download);
  form.addEventListener('submit', (event) => { event.preventDefault(); download(); });
  document.getElementById('restore-content').addEventListener('click', () => {
    if (!loaded) return;
    current = clone(loaded);
    buildFields();
    report('Restored the last loaded copy. Your published website has not changed.');
  });
  document.getElementById('add-faq').addEventListener('click', () => {
    if (!current || current.faq.length >= 25) return;
    current = readFields();
    current.faq.push({ question: '', answer: '' });
    buildFaq();
    document.getElementById(`faq-question-${current.faq.length - 1}`).focus();
    report('Question added. Fill in its question and answer before downloading.');
  });
  faqFields.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-faq]');
    if (!button || current.faq.length <= 1) return;
    current = readFields();
    current.faq.splice(Number(button.dataset.removeFaq), 1);
    buildFaq();
    report('Question removed from your draft. Download and commit the file to publish it.');
  });
  document.getElementById('import-content').addEventListener('change', async (event) => {
    const file = event.target.files[0];
    if (!file) return;
    try {
      if (file.size > 256 * 1024) throw new Error('Choose a content.json file smaller than 256 KB.');
      load(JSON.parse(await file.text()), 'Imported content');
    } catch (error) {
      report(error instanceof SyntaxError ? 'This file is not valid JSON. Choose content.json exported by this editor.' : error.message, true);
    }
    event.target.value = '';
  });
  form.addEventListener('input', () => report('Draft changed. Download content.json, then commit the file in GitHub to publish.'));
  async function initialize() {
    try {
      const response = await fetch('content.json', { cache: 'no-cache' });
      if (!response.ok) throw new Error('The published content file is unavailable. Import a saved content.json to start editing.');
      load(await response.json(), 'Published content');
    } catch (error) {
      report('Could not load published content. Open this editor on GitHub Pages or import a saved content.json file.', true);
    }
  }
  initialize();
})();

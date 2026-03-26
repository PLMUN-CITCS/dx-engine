(function initComponentRegistry(global) {
  'use strict';

  if (!global.DX) {
    global.DX = {};
  }

  const registry = new Map();

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '<')
      .replaceAll('>', '>')
      .replaceAll('"', '"')
      .replaceAll("'", '&#039;');
  }

  function attr(name, value) {
    if (value === null || value === undefined || value === '') {
      return '';
    }
    return ` ${name}="${escapeHtml(value)}"`;
  }

  function validationAttrs(validation) {
    if (!validation || typeof validation !== 'object') {
      return '';
    }

    const map = {
      required: 'data-required',
      min_length: 'data-min-length',
      max_length: 'data-max-length',
      regex: 'data-regex',
      min_value: 'data-min-value',
      max_value: 'data-max-value',
      email: 'data-email',
      match_field: 'data-match-field',
    };

    return Object.keys(map)
      .filter((key) => validation[key] !== undefined && validation[key] !== null)
      .map((key) => attr(map[key], validation[key]))
      .join('');
  }

  function resolveValue(component, state) {
    if (state && Object.prototype.hasOwnProperty.call(state, component.key)) {
      return state[component.key];
    }
    return component.value ?? '';
  }

  function renderChildren(children, state) {
    if (!Array.isArray(children) || children.length === 0) {
      return '';
    }
    return ComponentRegistry.renderAll(children, state);
  }

  const ComponentRegistry = {
    register(type, renderFn) {
      if (typeof type !== 'string' || type.trim() === '') {
        throw new TypeError('Component type must be a non-empty string.');
      }
      if (typeof renderFn !== 'function') {
        throw new TypeError('renderFn must be a function.');
      }
      registry.set(type, renderFn);
    },

    render(component, state = {}) {
      const type = component?.component_type;
      const renderer = registry.get(type);

      if (typeof renderer !== 'function') {
        return `<div class="alert alert-warning">Unknown component type: ${escapeHtml(type || 'undefined')}</div>`;
      }

      try {
        return renderer(component || {}, state || {});
      } catch (error) {
        return `<div class="alert alert-warning">Failed to render component: ${escapeHtml(type || 'unknown')}</div>`;
      }
    },

    renderAll(components, state = {}) {
      if (!Array.isArray(components)) {
        return '';
      }
      return components.map((c) => this.render(c, state)).join('');
    },

    isRegistered(type) {
      return registry.has(type);
    },
  };

  // OOTB component registrations
  ComponentRegistry.register('text_input', (component, state) => {
    const value = resolveValue(component, state);
    return `
      <div class="mb-3" data-component-key="${escapeHtml(component.key)}">
        <label class="form-label" for="${escapeHtml(component.key)}">${escapeHtml(component.label || '')}</label>
        <input type="text" class="form-control" id="${escapeHtml(component.key)}" name="${escapeHtml(component.key)}"
          value="${escapeHtml(value)}"${attr('placeholder', component.placeholder)}${validationAttrs(component.validation)}>
      </div>
    `;
  });

  ComponentRegistry.register('number_input', (component, state) => {
    const value = resolveValue(component, state);
    return `
      <div class="mb-3">
        <label class="form-label" for="${escapeHtml(component.key)}">${escapeHtml(component.label || '')}</label>
        <input type="number" class="form-control" id="${escapeHtml(component.key)}" name="${escapeHtml(component.key)}"
          value="${escapeHtml(value)}"${validationAttrs(component.validation)}>
      </div>
    `;
  });

  ComponentRegistry.register('email_input', (component, state) => {
    const value = resolveValue(component, state);
    return `
      <div class="mb-3">
        <label class="form-label" for="${escapeHtml(component.key)}">${escapeHtml(component.label || '')}</label>
        <input type="email" class="form-control" id="${escapeHtml(component.key)}" name="${escapeHtml(component.key)}"
          value="${escapeHtml(value)}"${validationAttrs(component.validation)}>
      </div>
    `;
  });

  ComponentRegistry.register('textarea', (component, state) => {
    const value = resolveValue(component, state);
    return `
      <div class="mb-3">
        <label class="form-label" for="${escapeHtml(component.key)}">${escapeHtml(component.label || '')}</label>
        <textarea class="form-control" id="${escapeHtml(component.key)}" name="${escapeHtml(component.key)}"
          rows="${escapeHtml(component.rows || 4)}"${attr('placeholder', component.placeholder)}${validationAttrs(component.validation)}>${escapeHtml(value)}</textarea>
      </div>
    `;
  });

  ComponentRegistry.register('select_dropdown', (component, state) => {
    const current = String(resolveValue(component, state));
    const options = Array.isArray(component.options) ? component.options : [];
    const renderedOptions = options.map((opt) => {
      const optionValue = typeof opt === 'object' ? String(opt.value ?? '') : String(opt);
      const optionLabel = typeof opt === 'object' ? String(opt.label ?? optionValue) : optionValue;
      const selected = optionValue === current ? ' selected' : '';
      return `<option value="${escapeHtml(optionValue)}"${selected}>${escapeHtml(optionLabel)}</option>`;
    }).join('');

    return `
      <div class="mb-3">
        <label class="form-label" for="${escapeHtml(component.key)}">${escapeHtml(component.label || '')}</label>
        <select class="form-select" id="${escapeHtml(component.key)}" name="${escapeHtml(component.key)}"${validationAttrs(component.validation)}>
          ${renderedOptions}
        </select>
      </div>
    `;
  });

  ComponentRegistry.register('checkbox', (component, state) => {
    const checked = Boolean(resolveValue(component, state)) ? ' checked' : '';
    return `
      <div class="form-check mb-3">
        <input type="checkbox" class="form-check-input" id="${escapeHtml(component.key)}" name="${escapeHtml(component.key)}"${checked}>
        <label class="form-check-label" for="${escapeHtml(component.key)}">${escapeHtml(component.label || '')}</label>
      </div>
    `;
  });

  ComponentRegistry.register('radio_group', (component, state) => {
    const current = String(resolveValue(component, state));
    const options = Array.isArray(component.options) ? component.options : [];
    const radios = options.map((opt, idx) => {
      const optionValue = typeof opt === 'object' ? String(opt.value ?? '') : String(opt);
      const optionLabel = typeof opt === 'object' ? String(opt.label ?? optionValue) : optionValue;
      const id = `${component.key}_${idx}`;
      const checked = optionValue === current ? ' checked' : '';
      return `
        <div class="form-check">
          <input class="form-check-input" type="radio" name="${escapeHtml(component.key)}" id="${escapeHtml(id)}" value="${escapeHtml(optionValue)}"${checked}>
          <label class="form-check-label" for="${escapeHtml(id)}">${escapeHtml(optionLabel)}</label>
        </div>
      `;
    }).join('');

    return `<fieldset class="mb-3"><legend class="col-form-label pt-0">${escapeHtml(component.label || '')}</legend>${radios}</fieldset>`;
  });

  ComponentRegistry.register('date_picker', (component, state) => `<input type="date" class="form-control mb-3" id="${escapeHtml(component.key)}" name="${escapeHtml(component.key)}" value="${escapeHtml(resolveValue(component, state))}">`);
  ComponentRegistry.register('datetime_picker', (component, state) => `<input type="datetime-local" class="form-control mb-3" id="${escapeHtml(component.key)}" name="${escapeHtml(component.key)}" value="${escapeHtml(resolveValue(component, state))}">`);
  ComponentRegistry.register('file_upload', (component) => `<input type="file" class="form-control mb-3" id="${escapeHtml(component.key)}" name="${escapeHtml(component.key)}">`);
  ComponentRegistry.register('display_text', (component) => `<p class="form-text text-body-secondary mb-3">${escapeHtml(component.label || component.value || '')}</p>`);
  ComponentRegistry.register('section_header', (component) => `<h5 class="fw-semibold border-bottom pb-2 mb-3">${escapeHtml(component.label || '')}</h5>`);

  ComponentRegistry.register('data_table', (component) => {
    const rows = Array.isArray(component.rows) ? component.rows : [];
    const headers = rows.length > 0 ? Object.keys(rows[0]) : [];
    const thead = headers.map((h) => `<th>${escapeHtml(h)}</th>`).join('');
    const tbody = rows.map((row) => {
      const cols = headers.map((h) => `<td>${escapeHtml(row[h])}</td>`).join('');
      return `<tr>${cols}</tr>`;
    }).join('');

    return `
      <div class="table-responsive mb-3">
        <table class="table table-striped table-hover table-bordered">
          <thead><tr>${thead}</tr></thead>
          <tbody>${tbody}</tbody>
        </table>
      </div>
    `;
  });

  ComponentRegistry.register('alert_banner', (component) => {
    const variant = component.variant || 'info';
    return `<div class="alert alert-${escapeHtml(variant)}" role="alert">${escapeHtml(component.label || component.message || '')}</div>`;
  });

  ComponentRegistry.register('button_primary', (component) => `<button type="button" class="btn btn-primary me-2" data-dx-action="${escapeHtml(component.action || '')}">${escapeHtml(component.label || 'Submit')}</button>`);
  ComponentRegistry.register('button_secondary', (component) => `<button type="button" class="btn btn-secondary me-2" data-dx-action="${escapeHtml(component.action || '')}">${escapeHtml(component.label || 'Cancel')}</button>`);
  ComponentRegistry.register('button_danger', (component) => `<button type="button" class="btn btn-danger me-2" data-dx-action="${escapeHtml(component.action || '')}">${escapeHtml(component.label || 'Delete')}</button>`);

  ComponentRegistry.register('card_container', (component, state) => `
    <div class="card mb-3">
      <div class="card-body">
        ${renderChildren(component.children, state)}
      </div>
    </div>
  `);

  ComponentRegistry.register('accordion', (component, state) => {
    const sections = Array.isArray(component.sections) ? component.sections : [];
    const idBase = component.key || 'accordion';
    const items = sections.map((section, idx) => {
      const collapseId = `${idBase}_collapse_${idx}`;
      const headingId = `${idBase}_heading_${idx}`;
      return `
        <div class="accordion-item">
          <h2 class="accordion-header" id="${escapeHtml(headingId)}">
            <button class="accordion-button ${idx === 0 ? '' : 'collapsed'}" type="button" data-bs-toggle="collapse" data-bs-target="#${escapeHtml(collapseId)}">
              ${escapeHtml(section.label || `Section ${idx + 1}`)}
            </button>
          </h2>
          <div id="${escapeHtml(collapseId)}" class="accordion-collapse collapse ${idx === 0 ? 'show' : ''}">
            <div class="accordion-body">
              ${renderChildren(section.children, state)}
            </div>
          </div>
        </div>
      `;
    }).join('');

    return `<div class="accordion mb-3" id="${escapeHtml(idBase)}">${items}</div>`;
  });

  ComponentRegistry.register('modal_trigger', (component) => `<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#${escapeHtml(component.modal_id || '')}">${escapeHtml(component.label || 'Open')}</button>`);
  ComponentRegistry.register('progress_bar', (component) => `<div class="progress mb-3"><div class="progress-bar" role="progressbar" style="width:${escapeHtml(component.value || 0)}%">${escapeHtml(component.value || 0)}%</div></div>`);
  ComponentRegistry.register('badge', (component) => `<span class="badge text-bg-${escapeHtml(component.variant || 'secondary')}">${escapeHtml(component.label || '')}</span>`);
  ComponentRegistry.register('separator', () => '<hr class="my-3">');

  global.DX.ComponentRegistry = ComponentRegistry;
})(window);

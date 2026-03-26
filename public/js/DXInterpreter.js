(function initDXInterpreter(global) {
  'use strict';

  if (!global.DX) {
    global.DX = {};
  }

  let config = {
    containerId: 'dx-container',
    dxId: null,
    caseId: null,
    initialETag: null,
  };

  let eTag = null;
  let containerElement = null;
  let submitHandlers = [];
  let active = false;

  function getContainer() {
    if (!containerElement && config.containerId) {
      containerElement = document.getElementById(config.containerId);
    }
    return containerElement;
  }

  function ensureDependencies() {
    const required = ['StateManager', 'ComponentRegistry', 'VisibilityEngine', 'Stepper', 'Validator'];
    required.forEach((key) => {
      if (!global.DX[key]) {
        throw new Error(`DX.${key} is required before DX.Interpreter.`);
      }
    });
  }

  function buildRequestBody(action) {
    return {
      dx_id: config.dxId,
      case_id: config.caseId,
      action: action || 'load',
      dirty_state: global.DX.StateManager.getAll(),
    };
  }

  function showConfirmation(note) {
    const container = getContainer();
    if (!container || !note || typeof note !== 'object') {
      return;
    }

    if (!note.message) {
      return;
    }

    const variant = note.variant || 'info';
    const bannerHtml = global.DX.ComponentRegistry.render({
      component_type: 'alert_banner',
      key: 'confirmation_note',
      variant: variant,
      label: note.message,
    }, {});

    const wrapper = document.createElement('div');
    wrapper.innerHTML = bannerHtml;
    const node = wrapper.firstElementChild;
    if (node) {
      container.prepend(node);
    }
  }

  function ensureModal() {
    let modal = document.getElementById('dx-etag-conflict-modal');
    if (modal) {
      return modal;
    }

    modal = document.createElement('div');
    modal.id = 'dx-etag-conflict-modal';
    modal.className = 'modal fade';
    modal.tabIndex = -1;
    modal.setAttribute('aria-hidden', 'true');
    modal.innerHTML = `
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Update Conflict Detected</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p>This case was updated by another user or session. Refresh to load the latest version.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="dx-etag-refresh-btn">Refresh</button>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modal);

    const refreshBtn = modal.querySelector('#dx-etag-refresh-btn');
    if (refreshBtn) {
      refreshBtn.addEventListener('click', () => {
        DXInterpreter.fetch('load');
      });
    }

    return modal;
  }

  function parseJsonSafe(response) {
    return response.json().catch(() => ({}));
  }

  const DXInterpreter = {
    init(runtimeConfig) {
      ensureDependencies();

      config = {
        ...config,
        ...(runtimeConfig || {}),
      };

      eTag = config.initialETag || null;
      containerElement = document.getElementById(config.containerId);
      active = true;
      submitHandlers = [];

      if (!containerElement) {
        throw new Error(`Container not found: #${config.containerId}`);
      }

      global.DX.Stepper.init(containerElement);
      this.fetch('load');
    },

    async fetch(action = 'load') {
      if (!active) {
        return;
      }

      const headers = {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      };

      if (eTag) {
        headers['If-Match'] = eTag;
      }

      const response = await global.fetch('/public/api/dx.php', {
        method: 'POST',
        headers,
        body: JSON.stringify(buildRequestBody(action)),
      });

      if (!response.ok) {
        await this.handleError(response);
        return;
      }

      const responseETag = response.headers.get('ETag');
      if (responseETag) {
        eTag = responseETag;
      }

      const payload = await parseJsonSafe(response);
      this.render(payload);
    },

    render(payload) {
      if (!active) {
        return;
      }

      const normalizedPayload = payload && typeof payload === 'object'
        ? payload
        : { data: {}, uiResources: [], nextAssignmentInfo: {}, confirmationNote: {} };

      const data = normalizedPayload.data || {};
      const uiResources = Array.isArray(normalizedPayload.uiResources) ? normalizedPayload.uiResources : [];
      const nextAssignmentInfo = normalizedPayload.nextAssignmentInfo || {};
      const confirmationNote = normalizedPayload.confirmationNote || {};

      global.DX.StateManager.setAll(data);

      const container = getContainer();
      if (!container) {
        return;
      }

      const rendered = global.DX.ComponentRegistry.renderAll(uiResources, global.DX.StateManager.getAll());

      container.innerHTML = `
        <div class="dx-stepper mb-3" id="dx-stepper-container"></div>
        <form id="dx-runtime-form" novalidate>
          ${rendered}
        </form>
      `;

      const stepperContainer = container.querySelector('#dx-stepper-container');
      if (stepperContainer) {
        global.DX.Stepper.init(stepperContainer);
        global.DX.Stepper.render(nextAssignmentInfo);
      }

      global.DX.VisibilityEngine.applyAll(container, global.DX.StateManager.getAll());
      global.DX.VisibilityEngine.subscribeToStateChanges(container);

      showConfirmation(confirmationNote);
      this.bindSubmitHandlers(container);
    },

    submit(action) {
      const container = getContainer();
      if (!container) {
        return;
      }

      const form = container.querySelector('#dx-runtime-form');
      if (!form) {
        this.fetch(action);
        return;
      }

      const validation = global.DX.Validator.validate(form);
      if (!validation.isValid) {
        global.DX.Validator.applyErrorStyles(form, validation.errors);
        return;
      }

      global.DX.Validator.clearErrorStyles(form);
      this.fetch(action);
    },

    async handleError(response) {
      const container = getContainer();
      const body = await parseJsonSafe(response);
      const status = response.status;

      if (status === 412) {
        const modalEl = ensureModal();
        if (global.bootstrap && global.bootstrap.Modal) {
          const modal = global.bootstrap.Modal.getOrCreateInstance(modalEl);
          modal.show();
        } else {
          global.alert('Update conflict detected. Please refresh.');
        }
        return;
      }

      if (status === 401) {
        global.location.href = '/login';
        return;
      }

      if (status === 422) {
        if (container) {
          const form = container.querySelector('#dx-runtime-form');
          if (form) {
            global.DX.Validator.applyErrorStyles(form, body.errors || {});
          }
        }
        return;
      }

      if (status >= 500) {
        if (container) {
          const message = body.error || 'An unexpected server error occurred.';
          const html = global.DX.ComponentRegistry.render({
            component_type: 'alert_banner',
            key: 'server_error',
            variant: 'danger',
            label: message,
          }, {});
          container.insertAdjacentHTML('afterbegin', html);
        }
      }
    },

    bindSubmitHandlers(targetContainer) {
      submitHandlers.forEach((unbind) => unbind());
      submitHandlers = [];

      if (!targetContainer) {
        return;
      }

      const actionNodes = targetContainer.querySelectorAll('[data-dx-action]');
      actionNodes.forEach((node) => {
        const handler = (event) => {
          event.preventDefault();
          const action = node.dataset.dxAction || 'submit';
          this.submit(action);
        };

        node.addEventListener('click', handler);
        submitHandlers.push(() => node.removeEventListener('click', handler));
      });
    },

    destroy() {
      submitHandlers.forEach((unbind) => unbind());
      submitHandlers = [];
      active = false;

      global.DX.StateManager.clear();

      const container = getContainer();
      if (container) {
        container.innerHTML = '';
      }

      containerElement = null;
      eTag = null;
    },
  };

  global.DX.Interpreter = DXInterpreter;
})(window);

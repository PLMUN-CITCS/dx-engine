(function initInitiateCaseButton(global) {
  'use strict';

  if (!global.DX) {
    global.DX = {};
  }

  let modalElement = null;
  let modalInstance = null;
  let runtimeContainerId = 'dx-initiate-case-runtime';
  let activeConfig = {
    dxId: 'AnonymousIntakeDX',
    modalTitle: 'Initiate Case',
    loginAction: '/login',
    runtimeContainerId: runtimeContainerId
  };

  function ensureRuntimeContainer(id) {
    let container = document.getElementById(id);
    if (!container) {
      container = document.createElement('div');
      container.id = id;
      container.className = 'd-none';
      document.body.appendChild(container);
    }
    return container;
  }

  function ensureModal(config) {
    if (modalElement) {
      return modalElement;
    }

    modalElement = document.createElement('div');
    modalElement.className = 'modal fade';
    modalElement.tabIndex = -1;
    modalElement.setAttribute('aria-hidden', 'true');
    modalElement.id = 'dx-initiate-case-modal';
    modalElement.innerHTML = `
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">${config.modalTitle || 'Initiate Case'}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div id="${runtimeContainerId}"></div>
          </div>
        </div>
      </div>
    `;

    document.body.appendChild(modalElement);

    modalElement.addEventListener('hidden.bs.modal', function () {
      InitiateCaseButton.closeModal();
    });

    return modalElement;
  }

  function readLatestConfirmation() {
    const state = global.DX && global.DX.StateManager && typeof global.DX.StateManager.getAll === 'function'
      ? global.DX.StateManager.getAll()
      : {};

    return state.confirmationNote || null;
  }

  const InitiateCaseButton = {
    mount(buttonSelector, config) {
      activeConfig = {
        ...activeConfig,
        ...(config || {})
      };

      runtimeContainerId = activeConfig.runtimeContainerId || runtimeContainerId;
      ensureRuntimeContainer(runtimeContainerId);

      const buttons = document.querySelectorAll(buttonSelector);
      buttons.forEach((button) => {
        button.addEventListener('click', (event) => {
          event.preventDefault();
          this.openIntakeModal(activeConfig);
        });
      });
    },

    openIntakeModal(config) {
      activeConfig = {
        ...activeConfig,
        ...(config || {})
      };

      runtimeContainerId = activeConfig.runtimeContainerId || runtimeContainerId;

      const modal = ensureModal(activeConfig);

      if (global.bootstrap && global.bootstrap.Modal) {
        modalInstance = global.bootstrap.Modal.getOrCreateInstance(modal);
        modalInstance.show();
      } else {
        modal.style.display = 'block';
      }

      if (!global.DX || !global.DX.Interpreter) {
        return;
      }

      global.DX.Interpreter.init({
        containerId: runtimeContainerId,
        dxId: activeConfig.dxId || 'AnonymousIntakeDX',
        caseId: null,
        initialETag: null
      });

      this.handleAuthChallenge(modal);
    },

    handleAuthChallenge(modalEl) {
      if (!modalEl) {
        return;
      }

      const listener = () => {
        const confirmation = readLatestConfirmation();
        if (!confirmation || confirmation.action_required !== 'authenticate') {
          return;
        }

        const body = modalEl.querySelector('.modal-body');
        if (!body) {
          return;
        }

        body.innerHTML = `
          <div class="alert alert-warning mb-3">
            Authentication is required to continue your case submission.
          </div>
          <form id="dx-embedded-login-form" class="vstack gap-3">
            <div>
              <label class="form-label" for="dx-login-email">Email</label>
              <input id="dx-login-email" name="email" type="email" class="form-control" required>
            </div>
            <div>
              <label class="form-label" for="dx-login-password">Password</label>
              <input id="dx-login-password" name="password" type="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Authenticate</button>
          </form>
        `;

        const form = body.querySelector('#dx-embedded-login-form');
        if (!form) {
          return;
        }

        form.addEventListener('submit', (event) => {
          event.preventDefault();

          // Demo resume path: in real deployments, authenticate via configured endpoint.
          if (activeConfig.loginAction) {
            window.location.href = activeConfig.loginAction;
            return;
          }

          body.innerHTML = `<div id="${runtimeContainerId}"></div>`;
          if (global.DX && global.DX.Interpreter) {
            global.DX.Interpreter.fetch('load');
          }
        }, { once: true });

        document.removeEventListener('dx:statechange', listener);
      };

      document.addEventListener('dx:statechange', listener);
    },

    closeModal() {
      if (global.DX && global.DX.Interpreter && typeof global.DX.Interpreter.destroy === 'function') {
        global.DX.Interpreter.destroy();
      }

      if (modalInstance && typeof modalInstance.hide === 'function') {
        modalInstance.hide();
      }

      if (modalElement && modalElement.parentNode) {
        modalElement.parentNode.removeChild(modalElement);
      }

      modalElement = null;
      modalInstance = null;
    }
  };

  global.DX.InitiateCaseButton = InitiateCaseButton;
})(window);

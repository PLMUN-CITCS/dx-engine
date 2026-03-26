(function initStepper(global) {
  'use strict';

  if (!global.DX) {
    global.DX = {};
  }

  let container = null;
  let currentStep = 0;
  let stepsState = [];

  function escapeHtml(value) {
    return String(value ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '<')
      .replaceAll('>', '>')
      .replaceAll('"', '"')
      .replaceAll("'", '&#039;');
  }

  function normalizeStatus(status) {
    const allowed = ['completed', 'active', 'pending', 'error'];
    return allowed.includes(status) ? status : 'pending';
  }

  function statusClass(status) {
    switch (status) {
      case 'completed':
        return 'text-bg-success';
      case 'active':
        return 'text-bg-primary';
      case 'error':
        return 'text-bg-danger';
      default:
        return 'text-bg-secondary';
    }
  }

  function renderSteps() {
    if (!container) {
      return;
    }

    const items = stepsState.map((step, index) => {
      const status = normalizeStatus(step.status);
      const isActive = index === currentStep;
      const ariaCurrent = isActive ? ' aria-current="step"' : '';
      const badgeClass = statusClass(status);

      return `
        <li class="list-inline-item me-2 mb-2" role="listitem"${ariaCurrent}>
          <span class="badge ${badgeClass}" data-step-index="${index}">
            ${escapeHtml(step.label || step.key || `Step ${index + 1}`)}
          </span>
        </li>
      `;
    }).join('');

    container.innerHTML = `
      <nav aria-label="Workflow Stepper">
        <ol class="list-inline mb-0" role="list">
          ${items}
        </ol>
      </nav>
    `;
  }

  function updateStatusesByCurrent() {
    stepsState = stepsState.map((step, index) => {
      if (step.status === 'error') {
        return step;
      }
      if (index < currentStep) {
        return { ...step, status: 'completed' };
      }
      if (index === currentStep) {
        return { ...step, status: 'active' };
      }
      return { ...step, status: 'pending' };
    });
  }

  const Stepper = {
    init(containerElement) {
      container = containerElement || null;
      if (!container) {
        return;
      }

      container.innerHTML = `
        <nav aria-label="Workflow Stepper">
          <ol class="list-inline mb-0" role="list"></ol>
        </nav>
      `;
    },

    render(nextAssignmentInfo) {
      const info = nextAssignmentInfo && typeof nextAssignmentInfo === 'object'
        ? nextAssignmentInfo
        : {};

      const steps = Array.isArray(info.steps) ? info.steps : [];
      stepsState = steps.map((step) => ({
        label: step.label || '',
        key: step.key || '',
        status: normalizeStatus(step.status),
      }));

      currentStep = Number.isInteger(info.current_step_index) && info.current_step_index >= 0
        ? info.current_step_index
        : 0;

      if (currentStep >= stepsState.length && stepsState.length > 0) {
        currentStep = stepsState.length - 1;
      }

      updateStatusesByCurrent();
      renderSteps();
    },

    advance(toStepIndex) {
      if (!Number.isInteger(toStepIndex)) {
        return;
      }

      const maxIndex = Math.max(0, stepsState.length - 1);
      currentStep = Math.min(Math.max(toStepIndex, 0), maxIndex);
      updateStatusesByCurrent();
      renderSteps();
    },

    retreat(toStepIndex) {
      this.advance(toStepIndex);
    },

    markStepCompleted(stepIndex) {
      if (!Number.isInteger(stepIndex) || stepIndex < 0 || stepIndex >= stepsState.length) {
        return;
      }

      stepsState[stepIndex] = { ...stepsState[stepIndex], status: 'completed' };
      renderSteps();
    },

    markStepError(stepIndex) {
      if (!Number.isInteger(stepIndex) || stepIndex < 0 || stepIndex >= stepsState.length) {
        return;
      }

      stepsState[stepIndex] = { ...stepsState[stepIndex], status: 'error' };
      renderSteps();
    },

    getCurrentStep() {
      return currentStep;
    },
  };

  global.DX.Stepper = Stepper;
})(window);

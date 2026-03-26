(function initDashboardPage(global) {
  'use strict';

  let refreshIntervalId = null;

  function updateSummary(state) {
    const active = document.getElementById('summary-active');
    const overdue = document.getElementById('summary-overdue');
    const dueToday = document.getElementById('summary-due-today');
    const upcoming = document.getElementById('summary-upcoming');
    const selectedRole = document.getElementById('selected-role');
    const userLabel = document.getElementById('dashboard-user-label');

    if (active && state.my_active_count) active.textContent = state.my_active_count;
    if (overdue && state.my_overdue_count) overdue.textContent = state.my_overdue_count;
    if (dueToday && state.my_due_today_count) dueToday.textContent = state.my_due_today_count;
    if (upcoming && state.my_upcoming_count) upcoming.textContent = state.my_upcoming_count;
    if (selectedRole && state.selected_queue_role) selectedRole.textContent = 'Selected Queue Role: ' + state.selected_queue_role;
    if (userLabel && state.user_id) userLabel.textContent = 'User ' + state.user_id;
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!global.DX || !global.DX.Interpreter) {
      return;
    }

    global.DX.Interpreter.init({
      containerId: 'dx-container',
      dxId: 'WorkDashboardDX',
      caseId: null,
      initialETag: null
    });

    refreshIntervalId = global.setInterval(function () {
      global.DX.Interpreter.fetch('refresh');
    }, 60000);

    document.addEventListener('dx:statechange', function (event) {
      updateSummary(event.detail || {});
    });
  });

  global.addEventListener('beforeunload', function () {
    if (refreshIntervalId !== null) {
      global.clearInterval(refreshIntervalId);
      refreshIntervalId = null;
    }

    if (global.DX && global.DX.Interpreter && typeof global.DX.Interpreter.destroy === 'function') {
      global.DX.Interpreter.destroy();
    }
  });
})(window);

(function initRbacAdminPage(global) {
  'use strict';

  function normalizeView(view) {
    const allowed = ['role_list', 'role_detail', 'permission_list', 'user_role_assignment'];
    return allowed.includes(view) ? view : 'role_list';
  }

  function viewLabel(view) {
    const labels = {
      role_list: 'Role List',
      role_detail: 'Role Detail',
      permission_list: 'Permission List',
      user_role_assignment: 'User Role Assignment'
    };

    return labels[view] || 'Role List';
  }

  function markActiveNav(view) {
    document.querySelectorAll('.js-rbac-nav').forEach((link) => {
      const linkView = link.getAttribute('data-rbac-view');
      link.classList.toggle('active', linkView === view);
    });
  }

  function setBreadcrumb(view) {
    const node = document.getElementById('rbac-breadcrumb-current');
    if (node) {
      node.textContent = viewLabel(view);
    }
  }

  function fetchView(view) {
    const safeView = normalizeView(view);
    if (!global.DX || !global.DX.StateManager || !global.DX.Interpreter) {
      return;
    }

    global.DX.StateManager.set('view', safeView);
    markActiveNav(safeView);
    setBreadcrumb(safeView);
    global.location.hash = '#' + safeView;
    global.DX.Interpreter.fetch(safeView);
  }

  function bindNavigation() {
    document.querySelectorAll('.js-rbac-nav').forEach((link) => {
      link.addEventListener('click', (event) => {
        event.preventDefault();
        const targetView = link.getAttribute('data-rbac-view') || 'role_list';
        fetchView(targetView);
      });
    });

    global.addEventListener('hashchange', () => {
      const viewFromHash = normalizeView(global.location.hash.replace('#', ''));
      fetchView(viewFromHash);
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (!global.DX || !global.DX.Interpreter) {
      return;
    }

    const initialView = normalizeView(global.location.hash.replace('#', ''));

    global.DX.Interpreter.init({
      containerId: 'dx-container',
      dxId: 'RbacAdminDX',
      caseId: null,
      initialETag: null
    });

    bindNavigation();
    markActiveNav(initialView);
    setBreadcrumb(initialView);

    if (global.DX && global.DX.StateManager) {
      global.DX.StateManager.set('view', initialView);
    }

    if (initialView !== 'role_list') {
      global.DX.Interpreter.fetch(initialView);
    }
  });
})(window);

(function initVisibilityEngine(global) {
  'use strict';

  if (!global.DX) {
    global.DX = {};
  }

  function isObject(value) {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
  }

  function isEmpty(value) {
    if (value === null || value === undefined) return true;
    if (typeof value === 'string') return value.trim() === '';
    if (Array.isArray(value)) return value.length === 0;
    if (isObject(value)) return Object.keys(value).length === 0;
    return false;
  }

  function evaluateAtomic(rule, state) {
    const operator = rule.operator;
    const field = rule.field;
    const ruleValue = rule.value;
    const currentValue = state ? state[field] : undefined;

    switch (operator) {
      case 'eq':
        return currentValue === ruleValue;
      case 'neq':
        return currentValue !== ruleValue;
      case 'gt':
        return Number(currentValue) > Number(ruleValue);
      case 'gte':
        return Number(currentValue) >= Number(ruleValue);
      case 'lt':
        return Number(currentValue) < Number(ruleValue);
      case 'lte':
        return Number(currentValue) <= Number(ruleValue);
      case 'contains':
        if (typeof currentValue === 'string') return currentValue.includes(String(ruleValue));
        if (Array.isArray(currentValue)) return currentValue.includes(ruleValue);
        return false;
      case 'in':
        return Array.isArray(ruleValue) ? ruleValue.includes(currentValue) : false;
      case 'not_in':
        return Array.isArray(ruleValue) ? !ruleValue.includes(currentValue) : true;
      case 'empty':
        return isEmpty(currentValue);
      case 'not_empty':
        return !isEmpty(currentValue);
      default:
        return true;
    }
  }

  const VisibilityEngine = {
    evaluate(rule, state) {
      if (!isObject(rule) || Object.keys(rule).length === 0) {
        return true;
      }

      if (Array.isArray(rule.and)) {
        return rule.and.every((subRule) => this.evaluate(subRule, state));
      }

      if (Array.isArray(rule.or)) {
        return rule.or.some((subRule) => this.evaluate(subRule, state));
      }

      return evaluateAtomic(rule, state || {});
    },

    applyAll(containerElement, state) {
      if (!containerElement || typeof containerElement.querySelectorAll !== 'function') {
        return;
      }

      const nodes = containerElement.querySelectorAll('[data-visibility-rule]');
      nodes.forEach((node) => {
        const ruleString = node.getAttribute('data-visibility-rule') || '';
        const rule = this.parseRule(ruleString);
        const isVisible = this.evaluate(rule, state || {});

        if (isVisible) {
          node.classList.remove('d-none');
        } else {
          node.classList.add('d-none');
        }
      });
    },

    parseRule(ruleString) {
      if (typeof ruleString !== 'string' || ruleString.trim() === '') {
        return {};
      }

      try {
        const parsed = JSON.parse(ruleString);
        return isObject(parsed) ? parsed : {};
      } catch (error) {
        return {};
      }
    },

    subscribeToStateChanges(containerElement) {
      document.addEventListener('dx:statechange', () => {
        const stateManager = global.DX && global.DX.StateManager;
        const state = stateManager && typeof stateManager.getAll === 'function'
          ? stateManager.getAll()
          : {};
        this.applyAll(containerElement, state);
      });
    },
  };

  global.DX.VisibilityEngine = VisibilityEngine;
})(window);

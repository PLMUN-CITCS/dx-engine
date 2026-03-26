(function initStateManager(global) {
  'use strict';

  if (!global.DX) {
    global.DX = {};
  }

  const STATE_CHANGE_EVENT = 'dx:statechange';

  /**
   * In-memory only state store.
   * Never persisted to localStorage/sessionStorage/cookies.
   */
  let state = {};

  function cloneShallow(obj) {
    return { ...obj };
  }

  function dispatchChange() {
    document.dispatchEvent(
      new CustomEvent(STATE_CHANGE_EVENT, {
        detail: cloneShallow(state),
      })
    );
  }

  function isObject(value) {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
  }

  function deepMerge(base, patch) {
    const output = { ...base };

    Object.keys(patch).forEach((key) => {
      const baseValue = output[key];
      const patchValue = patch[key];

      if (isObject(baseValue) && isObject(patchValue)) {
        output[key] = deepMerge(baseValue, patchValue);
      } else {
        output[key] = patchValue;
      }
    });

    return output;
  }

  const StateManager = {
    get(key) {
      return state[key];
    },

    set(key, value) {
      state[key] = value;
      dispatchChange();
    },

    setAll(data) {
      state = isObject(data) ? cloneShallow(data) : {};
      dispatchChange();
    },

    getAll() {
      return cloneShallow(state);
    },

    clear() {
      state = {};
      dispatchChange();
    },

    patch(key, partialValue) {
      const currentValue = isObject(state[key]) ? state[key] : {};
      const nextValue = isObject(partialValue)
        ? deepMerge(currentValue, partialValue)
        : partialValue;

      state[key] = nextValue;
      dispatchChange();
    },

    subscribe(callback) {
      if (typeof callback !== 'function') {
        throw new TypeError('StateManager.subscribe requires a callback function.');
      }

      const handler = (event) => callback(event.detail);

      document.addEventListener(STATE_CHANGE_EVENT, handler);

      return function unsubscribe() {
        document.removeEventListener(STATE_CHANGE_EVENT, handler);
      };
    },
  };

  global.DX.StateManager = StateManager;
})(window);

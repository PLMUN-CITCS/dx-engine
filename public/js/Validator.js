(function initValidator(global) {
  'use strict';

  if (!global.DX) {
    global.DX = {};
  }

  function toNumber(value) {
    const n = Number(value);
    return Number.isFinite(n) ? n : null;
  }

  function getFieldKey(input) {
    return input.name || input.id || input.getAttribute('data-field-key') || 'unknown';
  }

  function isEmpty(value) {
    return value === null || value === undefined || String(value).trim() === '';
  }

  function emailValid(value) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(String(value));
  }

  const Validator = {
    validate(formElement) {
      const result = {
        isValid: true,
        errors: {},
      };

      if (!formElement || typeof formElement.querySelectorAll !== 'function') {
        return result;
      }

      const inputs = formElement.querySelectorAll('input, textarea, select');
      inputs.forEach((input) => {
        const fieldErrors = this.validateField(input);
        if (fieldErrors.length > 0) {
          const key = getFieldKey(input);
          result.errors[key] = fieldErrors;
          result.isValid = false;
        }
      });

      return result;
    },

    validateField(inputElement) {
      const errors = [];
      if (!inputElement) {
        return errors;
      }

      const value = inputElement.value;
      const required = inputElement.getAttribute('data-required') === 'true';
      const minLength = toNumber(inputElement.getAttribute('data-min-length'));
      const maxLength = toNumber(inputElement.getAttribute('data-max-length'));
      const regexPattern = inputElement.getAttribute('data-regex');
      const minValue = toNumber(inputElement.getAttribute('data-min-value'));
      const maxValue = toNumber(inputElement.getAttribute('data-max-value'));
      const email = inputElement.getAttribute('data-email') === 'true';
      const matchField = inputElement.getAttribute('data-match-field');

      if (required && isEmpty(value)) {
        errors.push('This field is required.');
      }

      if (!isEmpty(value) && minLength !== null && String(value).length < minLength) {
        errors.push(`Minimum length is ${minLength} characters.`);
      }

      if (!isEmpty(value) && maxLength !== null && String(value).length > maxLength) {
        errors.push(`Maximum length is ${maxLength} characters.`);
      }

      if (!isEmpty(value) && regexPattern) {
        try {
          const re = new RegExp(regexPattern);
          if (!re.test(String(value))) {
            errors.push('Value format is invalid.');
          }
        } catch (error) {
          errors.push('Validation pattern is invalid.');
        }
      }

      if (!isEmpty(value) && minValue !== null) {
        const numeric = toNumber(value);
        if (numeric === null || numeric < minValue) {
          errors.push(`Minimum value is ${minValue}.`);
        }
      }

      if (!isEmpty(value) && maxValue !== null) {
        const numeric = toNumber(value);
        if (numeric === null || numeric > maxValue) {
          errors.push(`Maximum value is ${maxValue}.`);
        }
      }

      if (!isEmpty(value) && email && !emailValid(value)) {
        errors.push('Email address is invalid.');
      }

      if (matchField) {
        const form = inputElement.closest('form') || document;
        const target = form.querySelector(`[name="${matchField}"], #${matchField}`);
        const targetValue = target ? target.value : '';
        if (String(value) !== String(targetValue)) {
          errors.push('This field must match the related field.');
        }
      }

      return errors;
    },

    applyErrorStyles(formElement, errors) {
      if (!formElement || !errors || typeof errors !== 'object') {
        return;
      }

      this.clearErrorStyles(formElement);

      Object.keys(errors).forEach((fieldKey) => {
        const messages = Array.isArray(errors[fieldKey]) ? errors[fieldKey] : [String(errors[fieldKey])];
        const input = formElement.querySelector(`[name="${fieldKey}"], #${fieldKey}`);

        if (!input) {
          return;
        }

        input.classList.add('is-invalid');

        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.textContent = messages.join(' ');
        input.insertAdjacentElement('afterend', feedback);
      });
    },

    clearErrorStyles(formElement) {
      if (!formElement || typeof formElement.querySelectorAll !== 'function') {
        return;
      }

      const invalidInputs = formElement.querySelectorAll('.is-invalid');
      invalidInputs.forEach((input) => input.classList.remove('is-invalid'));

      const feedbacks = formElement.querySelectorAll('.invalid-feedback');
      feedbacks.forEach((node) => node.remove());
    },
  };

  global.DX.Validator = Validator;
})(window);

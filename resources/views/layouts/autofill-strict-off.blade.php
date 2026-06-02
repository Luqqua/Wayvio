<script data-global-autofill-isolation="strict-off">
  (function () {
    const fieldSelector = [
      'input',
      'textarea',
      'select',
    ].join(',');

    const shouldSkipField = function (field) {
      if (!(field instanceof HTMLElement)) {
        return true;
      }

      if (field.dataset.autofillAllow === 'true' || field.closest('[data-autofill-allow="true"]')) {
        return true;
      }

      if (field.hasAttribute('disabled')) {
        return true;
      }

      return false;
    };

    const supportsReadonlyIsolation = function (field) {
      if (field instanceof HTMLTextAreaElement) {
        return true;
      }

      if (!(field instanceof HTMLInputElement)) {
        return false;
      }

      const inputType = String(field.type || 'text').toLowerCase();
      return ![
        'checkbox',
        'radio',
        'file',
        'hidden',
        'button',
        'submit',
        'reset',
        'range',
        'color',
        'date',
        'datetime-local',
        'month',
        'week',
        'time',
      ].includes(inputType);
    };

    const bindReadonlyIsolation = function (field) {
      if (field.dataset.autofillReadonlyBound === '1') {
        return;
      }

      field.dataset.autofillReadonlyBound = '1';

      if (!field.hasAttribute('readonly')) {
        field.setAttribute('readonly', 'readonly');
      }

      const unlock = function () {
        field.removeAttribute('readonly');
      };

      field.addEventListener('focus', unlock, { passive: true });
      field.addEventListener('pointerdown', unlock, { passive: true });
      field.addEventListener('touchstart', unlock, { passive: true });
      field.addEventListener('blur', function () {
        field.setAttribute('readonly', 'readonly');
      });
    };

    const hardenField = function (field) {
      if (shouldSkipField(field)) {
        return;
      }

      field.setAttribute('autocomplete', 'off');
      field.setAttribute('aria-autocomplete', 'none');
      field.setAttribute('autocapitalize', 'off');
      field.setAttribute('autocorrect', 'off');
      field.setAttribute('spellcheck', 'false');
      field.setAttribute('data-lpignore', 'true');
      field.setAttribute('data-1p-ignore', 'true');
      if (supportsReadonlyIsolation(field)) {
        bindReadonlyIsolation(field);
      }
    };

    const hardenForm = function (form) {
      if (!(form instanceof HTMLFormElement)) {
        return;
      }

      if (form.dataset.autofillAllow === 'true' || form.closest('[data-autofill-allow="true"]')) {
        return;
      }

      form.setAttribute('autocomplete', 'off');
      form.setAttribute('data-lpignore', 'true');
    };

    const hardenTree = function (root) {
      if (!(root instanceof HTMLElement) && root !== document) {
        return;
      }

      if (root instanceof HTMLFormElement) {
        hardenForm(root);
      }

      if (root.querySelectorAll) {
        root.querySelectorAll('form').forEach(hardenForm);
        root.querySelectorAll(fieldSelector).forEach(hardenField);
      }
    };

    const run = function () {
      hardenTree(document);
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', run);
    } else {
      run();
    }

    const observer = new MutationObserver(function (records) {
      records.forEach(function (record) {
        record.addedNodes.forEach(function (node) {
          if (!(node instanceof HTMLElement)) {
            return;
          }

          hardenTree(node);
        });
      });
    });

    observer.observe(document.documentElement, {
      childList: true,
      subtree: true,
    });
  })();
</script>

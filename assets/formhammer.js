document.addEventListener('DOMContentLoaded', function () {
    var forms = document.querySelectorAll('form[data-formhammer]');

    forms.forEach(function (form) {
        var startTime = Date.now();
        var formId = form.getAttribute('data-formhammer') || form.getAttribute('id');

        if (formId) {
            fetchToken(form, formId);
        }

        form.addEventListener('submit', function () {
            var elapsedField = form.querySelector('input[name="hl_elapsed"]');

            if (!elapsedField) {
                return;
            }

            elapsedField.value = String(Date.now() - startTime);
        });
    });
});

function fetchToken(form, formId) {
    if (typeof fetch !== 'function') {
        return;
    }

    fetch(restTokenUrl(formId), {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json'
        }
    })
        .then(function (response) {
            if (!response.ok) {
                return null;
            }

            return response.json();
        })
        .then(function (data) {
            if (!data || typeof data.token !== 'string') {
                return;
            }

            tokenField(form).value = data.token;
        })
        .catch(function () {
        });
}

function restTokenUrl(formId) {
    var root = '/wp-json/';

    if (window.formhammerRestUrl) {
        root = window.formhammerRestUrl;
    } else if (window.wpApiSettings && window.wpApiSettings.root) {
        root = window.wpApiSettings.root;
    }

    return root.replace(/\/?$/, '/') + 'formhammer/v1/token?form_id=' + encodeURIComponent(formId);
}

function tokenField(form) {
    var field = form.querySelector('input[name="hl_token"]');

    if (field) {
        return field;
    }

    field = document.createElement('input');
    field.type = 'hidden';
    field.name = 'hl_token';
    form.appendChild(field);

    return field;
}

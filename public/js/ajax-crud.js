(function () {
    'use strict';

    function toast(message, isError) {
        var notice = document.createElement('div');
        notice.className = 'ajax-crud-notice' + (isError ? ' is-error' : '');
        notice.setAttribute('role', 'status');
        notice.textContent = message;
        document.body.appendChild(notice);
        requestAnimationFrame(function () { notice.classList.add('is-visible'); });
        setTimeout(function () {
            notice.classList.remove('is-visible');
            setTimeout(function () { notice.remove(); }, 200);
        }, 2800);
    }

    function errorMessage(data, response) {
        if (data && data.errors) {
            return Object.values(data.errors).flat().join('\n');
        }
        return (data && data.message) || ('Request failed (' + response.status + ').');
    }

    async function submit(form) {
        var button = form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;

        try {
            var response = await fetch(form.action, {
                method: form.method || 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new FormData(form)
            });
            var data = await response.json().catch(function () { return {}; });
            if (!response.ok) throw new Error(errorMessage(data, response));

            if (form.dataset.ajaxRemove) {
                var target = form.closest(form.dataset.ajaxRemove) || document.querySelector(form.dataset.ajaxRemove);
                if (target) target.remove();
            }

            if (form.dataset.ajaxReset === 'true') form.reset();
            toast(data.message || form.dataset.successMessage || 'Saved.', false);
            form.dispatchEvent(new CustomEvent('ajax-crud:success', { bubbles: true, detail: data }));

            if (data.redirect) window.location.assign(data.redirect);
        } catch (error) {
            toast(error.message, true);
            form.dispatchEvent(new CustomEvent('ajax-crud:error', { bubbles: true, detail: error }));
        } finally {
            if (button && document.contains(button)) button.disabled = false;
        }
    }

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('form[data-ajax-crud]');
        if (!form || event.defaultPrevented) return;
        event.preventDefault();
        submit(form);
    });

    var pendingAdminRow = null;
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-delete-url]');
        if (!trigger) return;
        pendingAdminRow = trigger.closest('tr');
        var deleteForm = document.getElementById('deleteAttractionForm');
        if (deleteForm) deleteForm.dataset.ajaxCrud = '';
    });

    document.addEventListener('ajax-crud:success', function (event) {
        var form = event.target;
        var data = event.detail || {};
        if (form.dataset.ajaxWishlist !== undefined && data.wishlisted !== undefined) {
            var button = form.querySelector('button[type="submit"]');
            form.action = data.action;
            var method = form.querySelector('input[name="_method"]');
            if (data.wishlisted && !method) {
                method = document.createElement('input');
                method.type = 'hidden';
                method.name = '_method';
                form.appendChild(method);
            }
            if (method) {
                if (data.wishlisted) method.value = 'DELETE';
                else method.remove();
            }
            if (button) {
                button.textContent = data.label;
                button.setAttribute('aria-label', data.ariaLabel || data.label);
                button.classList.toggle('is-saved', data.wishlisted);
                button.classList.toggle('wishlisted', data.wishlisted);
            }
        }

        if (form.id === 'deleteAttractionForm') {
            if (pendingAdminRow) pendingAdminRow.remove();
            var dialog = form.closest('dialog');
            if (dialog) dialog.close();
            pendingAdminRow = null;
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.alert-success, .alert-danger, .admin-alert, .validation-errors').forEach(function (message) {
            message.classList.add('global-message-popup');
            message.setAttribute('role', message.matches('.alert-danger, .validation-errors') ? 'alert' : 'status');
            setTimeout(function () {
                message.classList.add('is-leaving');
                setTimeout(function () { message.remove(); }, 250);
            }, 4000);
        });
    });

    var style = document.createElement('style');
    style.textContent = '.ajax-crud-notice,.global-message-popup{position:fixed!important;top:112px!important;right:auto!important;bottom:auto!important;left:50%!important;z-index:10000!important;width:max-content;max-width:min(520px,calc(100vw - 32px));margin:0!important;padding:14px 18px!important;border:1px solid #cde4d2!important;border-radius:12px!important;background:#e7f5ea!important;color:#24643a!important;font-size:12px;font-weight:700;box-shadow:0 12px 30px rgba(18,60,49,.16);text-align:center;white-space:pre-line;transform:translate(-50%,-10px);transition:opacity .2s ease,transform .2s ease}.ajax-crud-notice{opacity:0}.ajax-crud-notice.is-error,.global-message-popup.alert-danger,.global-message-popup.validation-errors{border-color:#efc8b8!important;background:#fff0eb!important;color:#9d452d!important}.ajax-crud-notice.is-visible,.global-message-popup{opacity:1;transform:translate(-50%,0)}.global-message-popup.is-leaving{opacity:0;transform:translate(-50%,-10px)}@media(max-width:720px){.ajax-crud-notice,.global-message-popup{top:88px!important;width:calc(100vw - 28px)}}';
    document.head.appendChild(style);
}());

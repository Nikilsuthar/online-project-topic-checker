// Online Project Topic Availability Checker - client side helpers

document.addEventListener('DOMContentLoaded', function () {
    // Mobile sidebar toggle
    var toggle = document.querySelector('[data-menu-toggle]');
    var sidebar = document.querySelector('[data-sidebar]');
    if (toggle && sidebar) {
        toggle.addEventListener('click', function () { sidebar.classList.toggle('open'); });
    }

    // Auto-hide flash messages
    document.querySelectorAll('[data-autohide]').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(function () { el.remove(); }, 400);
        }, 5000);
    });

    // Live table filter: <input data-filter="#table"> + optional <select data-status-filter="#table">
    document.querySelectorAll('[data-filter], [data-status-filter]').forEach(function (control) {
        var table = document.querySelector(control.getAttribute('data-filter') || control.getAttribute('data-status-filter'));
        if (!table) return;
        control.addEventListener('input', function () { filterTable(table); });
        control.addEventListener('change', function () { filterTable(table); });
    });

    function filterTable(table) {
        var sel = '#' + table.id;
        var textInput = document.querySelector('[data-filter="' + sel + '"]');
        var statusSelect = document.querySelector('[data-status-filter="' + sel + '"]');
        var q = textInput ? textInput.value.trim().toLowerCase() : '';
        var status = statusSelect ? statusSelect.value : '';
        var shown = 0;
        table.querySelectorAll('tbody tr[data-row]').forEach(function (row) {
            var okText = !q || row.textContent.toLowerCase().indexOf(q) !== -1;
            var okStatus = !status || row.getAttribute('data-status') === status;
            row.style.display = okText && okStatus ? '' : 'none';
            if (okText && okStatus) shown++;
        });
        var empty = table.querySelector('tr[data-empty-filter]');
        if (empty) empty.style.display = shown ? 'none' : '';
    }

    // Confirmation for dangerous actions
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('submit', function (ev) {
            if (!confirm(el.getAttribute('data-confirm'))) ev.preventDefault();
        });
    });

    // Show / hide password
    document.querySelectorAll('.pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = btn.parentElement.querySelector('input');
            var hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            btn.textContent = hidden ? 'Hide' : 'Show';
        });
    });

    // Client side validation for forms marked data-validate (server validates again)
    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            var ok = true;
            form.querySelectorAll('.field-error.js').forEach(function (n) { n.remove(); });
            form.querySelectorAll('.is-invalid').forEach(function (n) { n.classList.remove('is-invalid'); });

            form.querySelectorAll('[data-rule]').forEach(function (input) {
                var msg = checkRule(input, form);
                if (msg) {
                    ok = false;
                    input.classList.add('is-invalid');
                    var div = document.createElement('div');
                    div.className = 'field-error js';
                    div.textContent = msg;
                    (input.closest('.pw-wrap') || input).insertAdjacentElement('afterend', div);
                }
            });
            if (!ok) ev.preventDefault();
        });
    });

    function checkRule(input, form) {
        var v = input.value.trim();
        var rules = input.getAttribute('data-rule').split('|');
        for (var i = 0; i < rules.length; i++) {
            var r = rules[i].split(':');
            var name = r[0], arg = r[1];
            if (name === 'required' && !v) return 'This field is required.';
            if (!v) continue;
            if (name === 'min' && v.length < +arg) return 'Minimum ' + arg + ' characters.';
            if (name === 'max' && v.length > +arg) return 'Maximum ' + arg + ' characters.';
            if (name === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) return 'Enter a valid email address.';
            if (name === 'username' && !/^[a-zA-Z0-9_]{3,30}$/.test(v)) return 'Use 3-30 letters, numbers or underscore.';
            if (name === 'topic' && !/^[A-Za-z0-9 .,&()\/'+:-]+$/.test(v)) return 'Only letters, numbers and basic punctuation are allowed.';
            if (name === 'letters' && !/[A-Za-z]{2,}/.test(v)) return 'Please enter meaningful text.';
            if (name === 'match') {
                var other = form.querySelector('[name="' + arg + '"]');
                if (other && other.value !== input.value) return 'Passwords do not match.';
            }
        }
        return '';
    }
});

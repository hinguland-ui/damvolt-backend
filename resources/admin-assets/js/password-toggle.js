// Adds an eye button to every <input type="password"> so it can be shown / hidden.
(function () {
    function enhance(root) {
        (root || document).querySelectorAll('input[type="password"]:not([data-eye])').forEach(function (input) {
            input.dataset.eye = '1';
            var wrap = document.createElement('div');
            wrap.className = 'pw-wrap';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pw-eye';
            btn.setAttribute('aria-label', 'Show password');
            btn.tabIndex = -1;
            btn.innerHTML = '<i class="bi bi-eye"></i>';
            btn.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.innerHTML = '<i class="bi ' + (show ? 'bi-eye-slash' : 'bi-eye') + '"></i>';
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
            wrap.appendChild(btn);
        });
    }
    window.enhancePasswordInputs = enhance;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { enhance(); });
    else enhance();
})();

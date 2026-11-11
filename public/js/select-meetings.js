(function () {
    var form = document.getElementById('select-form');
    if (!form) return;
    var boxes = Array.prototype.slice.call(form.querySelectorAll('input[name="ids[]"]'));
    var bar = document.getElementById('action-bar');
    var count = document.getElementById('selected-count');

    function refresh() {
        var n = boxes.filter(function (b) { return b.checked; }).length;
        count.textContent = n + ' selected';
        bar.hidden = n === 0;
        lift();
    }

    function lift() {
        document.documentElement.style.setProperty('--bottom-ui', bar.hidden ? '0px' : bar.offsetHeight + 'px');
    }
    window.addEventListener('resize', lift);

    form.addEventListener('change', refresh);
    form.addEventListener('click', function (e) {
        var mode = e.target.getAttribute && e.target.getAttribute('data-select');
        if (!mode) return;
        boxes.forEach(function (b) {
            b.checked = mode === 'all' ? true
                : mode === 'none' ? false
                : b.getAttribute('data-' + mode) === '1';
        });
        refresh();
    });
    refresh();
}());

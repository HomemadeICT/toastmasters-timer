(function() {
    if (localStorage.getItem('obs_mode') === 'true') {
        document.body.classList.add('obs-safe-mode');
    }

    const btn = document.createElement('button');
    btn.className = 'no-print obs-global-toggle';
    btn.innerHTML = 'OBS Shift';
    btn.title = "Toggle Permanent OBS Safespace";
    
    btn.onclick = () => {
        const isActive = document.body.classList.toggle('obs-safe-mode');
        localStorage.setItem('obs_mode', isActive ? 'true' : 'false');
    };
    
    /* Ensure body exists just in case */
    if (document.body) {
        document.body.appendChild(btn);
    } else {
        document.addEventListener('DOMContentLoaded', () => document.body.appendChild(btn));
    }
})();

(function() {
    // Inject the CSS styles for the modal
    const style = document.createElement('style');
    style.innerHTML = `
        .zoom-global-toggle { 
            position: fixed; 
            bottom: 70px; /* Above OBS Shift */
            right: 20px; 
            z-index: 10000; 
            background: #004165 !important; 
            color: #ffffff !important; 
            padding: 10px 20px; 
            border-radius: 8px; 
            border: 2px solid #ffffff; 
            font-size: 14px; 
            font-weight: 800; 
            cursor: pointer; 
            transition: all 0.2s; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
            text-transform: uppercase;
            font-family: 'Outfit', sans-serif;
        }
        .zoom-global-toggle:hover { 
            background: #00304a !important; 
            transform: scale(1.05);
        }
        
        #zoomConfigModal {
            display: none;
            position: fixed;
            bottom: 120px;
            right: 20px;
            background: #ffffff;
            color: #1e293b;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 320px;
            z-index: 10001;
            font-family: 'Outfit', sans-serif;
            border: 2px solid #e2e8f0;
        }
        
        #zoomConfigModal h3 { margin-top: 0; color: #004165; margin-bottom: 15px; font-weight: 700; }
        .zoom-slider-group { margin-bottom: 15px; }
        .zoom-slider-group label { display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 600; color: #64748b; margin-bottom: 5px; }
        .zoom-slider-group input[type=range] { width: 100%; cursor: pointer; }
        
        .zoom-controls-btns { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 15px; }
        .zoom-btn { padding: 8px; font-size: 0.85rem; border: none; border-radius: 6px; cursor: pointer; font-family: inherit; font-weight: 600; }
        .zoom-btn-primary { background: #004165; color: white; }
        .zoom-btn-secondary { background: #e2e8f0; color: #334155; }
        .zoom-btn-danger { background: #ef4444; color: white; }
        
        @media print {
            .zoom-global-toggle, #zoomConfigModal { display: none !important; }
            .zoom-global, body.zoom-global, .zoom-timer, .zoom-meta, .zoom-logo {
                zoom: 1 !important;
            }
        }
        
        /* Zoom Scaling Rules */
        .zoom-timer {
            zoom: var(--zoom-timer, 1);
        }

        .zoom-meta {
            zoom: var(--zoom-meta, 1);
        }

        .zoom-logo {
            zoom: var(--zoom-logo, 1);
        }

        .zoom-global {
            zoom: var(--zoom-global, 1);
        }
    `;
    document.head.appendChild(style);

    const DEFAULT_CONFIG = {
        '--zoom-timer': 1.0,
        '--zoom-meta': 1.0,
        '--zoom-logo': 1.0,
        '--zoom-global': 1.0
    };

    let currentConfig = { ...DEFAULT_CONFIG };

    function applyConfig(config) {
        for (const [key, val] of Object.entries(config)) {
            document.documentElement.style.setProperty(key, val);
            const slider = document.getElementById('slider' + key);
            if (slider) {
                slider.value = val;
                document.getElementById('val' + key).innerText = parseFloat(val).toFixed(2) + 'x';
            }
        }
        currentConfig = { ...config };
        localStorage.setItem('toastmasters_zoom_current', JSON.stringify(currentConfig));
    }

    // Load from local storage
    try {
        const saved = localStorage.getItem('toastmasters_zoom_current');
        if (saved) {
            const parsed = JSON.parse(saved);
            applyConfig({ ...DEFAULT_CONFIG, ...parsed });
        }
    } catch (e) { console.error('Error loading zoom config', e); }

    // Floating Button
    const btn = document.createElement('button');
    btn.className = 'no-print zoom-global-toggle';
    btn.innerHTML = 'Zoom Config';
    btn.title = "Configure Panel Zoom & Scaling";

    // Modal
    const modal = document.createElement('div');
    modal.id = 'zoomConfigModal';
    modal.className = 'no-print';
    
    modal.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px;">
            <h3 style="margin:0;">Zoom & Scaling</h3>
            <button id="closeZoomBtn" style="background:none; border:none; font-size:1.2rem; cursor:pointer; color:#64748b;">&times;</button>
        </div>
        
        <div class="zoom-slider-group">
            <label>Timer Display <span id="val--zoom-timer">1.00x</span></label>
            <input type="range" id="slider--zoom-timer" min="0.5" max="3.0" step="0.05" value="${currentConfig['--zoom-timer']}">
        </div>
        <div class="zoom-slider-group">
            <label>Speaker Info <span id="val--zoom-meta">1.00x</span></label>
            <input type="range" id="slider--zoom-meta" min="0.5" max="3.0" step="0.05" value="${currentConfig['--zoom-meta']}">
        </div>
        <div class="zoom-slider-group">
            <label>Logo <span id="val--zoom-logo">1.00x</span></label>
            <input type="range" id="slider--zoom-logo" min="0.5" max="3.0" step="0.05" value="${currentConfig['--zoom-logo']}">
        </div>
        <div class="zoom-slider-group">
            <label>Global (Page Content) <span id="val--zoom-global">1.00x</span></label>
            <input type="range" id="slider--zoom-global" min="0.5" max="3.0" step="0.05" value="${currentConfig['--zoom-global']}">
        </div>

        <div style="margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 15px;">
            <div class="zoom-controls-btns" style="margin-top:0;">
                <button id="resetZoomBtn" class="zoom-btn zoom-btn-danger">Reset Default</button>
                <select id="presetSelect" style="padding: 6px; border-radius: 6px; border: 1px solid #cbd5e1; font-family: inherit; font-size: 0.8rem;">
                    <option value="">-- Load Preset --</option>
                </select>
            </div>
            <div class="zoom-controls-btns">
                <button id="savePresetBtn" class="zoom-btn zoom-btn-primary">Save Preset</button>
                <button id="deletePresetBtn" class="zoom-btn zoom-btn-secondary">Delete Preset</button>
            </div>
            <div class="zoom-controls-btns">
                <button id="exportPresetBtn" class="zoom-btn zoom-btn-secondary">Export JSON</button>
                <button id="importPresetBtn" class="zoom-btn zoom-btn-secondary">Import JSON</button>
            </div>
        </div>
    `;

    document.addEventListener('DOMContentLoaded', () => {
        document.body.appendChild(btn);
        document.body.appendChild(modal);

        // Bind events
        btn.onclick = () => {
            modal.style.display = modal.style.display === 'block' ? 'none' : 'block';
        };
        
        document.getElementById('closeZoomBtn').onclick = () => {
            modal.style.display = 'none';
        };

        const keys = ['--zoom-timer', '--zoom-meta', '--zoom-logo', '--zoom-global'];
        keys.forEach(key => {
            const slider = document.getElementById('slider' + key);
            if (slider) {
                slider.addEventListener('input', (e) => {
                    const val = e.target.value;
                    document.getElementById('val' + key).innerText = parseFloat(val).toFixed(2) + 'x';
                    document.documentElement.style.setProperty(key, val);
                    currentConfig[key] = parseFloat(val);
                    localStorage.setItem('toastmasters_zoom_current', JSON.stringify(currentConfig));
                });
            }
        });

        document.getElementById('resetZoomBtn').onclick = () => {
            applyConfig(DEFAULT_CONFIG);
        };

        // Preset Management
        function loadPresetsDropdown() {
            const sel = document.getElementById('presetSelect');
            sel.innerHTML = '<option value="">-- Load Preset --</option>';
            let presets = {};
            try { presets = JSON.parse(localStorage.getItem('toastmasters_zoom_presets')) || {}; } catch(e){}
            
            for (const name in presets) {
                const opt = document.createElement('option');
                opt.value = name;
                opt.innerText = name;
                sel.appendChild(opt);
            }
        }
        loadPresetsDropdown();

        document.getElementById('presetSelect').onchange = (e) => {
            const name = e.target.value;
            if (!name) return;
            let presets = {};
            try { presets = JSON.parse(localStorage.getItem('toastmasters_zoom_presets')) || {}; } catch(e){}
            if (presets[name]) {
                applyConfig(presets[name]);
            }
        };

        document.getElementById('savePresetBtn').onclick = () => {
            const name = prompt("Enter a name for this preset:");
            if (!name) return;
            let presets = {};
            try { presets = JSON.parse(localStorage.getItem('toastmasters_zoom_presets')) || {}; } catch(e){}
            presets[name] = currentConfig;
            localStorage.setItem('toastmasters_zoom_presets', JSON.stringify(presets));
            loadPresetsDropdown();
            document.getElementById('presetSelect').value = name;
        };

        document.getElementById('deletePresetBtn').onclick = () => {
            const sel = document.getElementById('presetSelect');
            const name = sel.value;
            if (!name) return alert("Select a preset to delete first.");
            if (!confirm("Delete preset '" + name + "'?")) return;
            
            let presets = {};
            try { presets = JSON.parse(localStorage.getItem('toastmasters_zoom_presets')) || {}; } catch(e){}
            delete presets[name];
            localStorage.setItem('toastmasters_zoom_presets', JSON.stringify(presets));
            loadPresetsDropdown();
        };

        document.getElementById('exportPresetBtn').onclick = () => {
            const allData = {
                current: currentConfig,
                presets: JSON.parse(localStorage.getItem('toastmasters_zoom_presets') || '{}')
            };
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(allData, null, 2));
            const downloadAnchorNode = document.createElement('a');
            downloadAnchorNode.setAttribute("href", dataStr);
            downloadAnchorNode.setAttribute("download", "zoom_presets.json");
            document.body.appendChild(downloadAnchorNode);
            downloadAnchorNode.click();
            downloadAnchorNode.remove();
        };

        document.getElementById('importPresetBtn').onclick = () => {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';
            input.onchange = e => {
                const file = e.target.files[0];
                const reader = new FileReader();
                reader.readAsText(file,'UTF-8');
                reader.onload = readerEvent => {
                    try {
                        const content = JSON.parse(readerEvent.target.result);
                        if (content.presets) {
                            localStorage.setItem('toastmasters_zoom_presets', JSON.stringify(content.presets));
                            loadPresetsDropdown();
                        }
                        if (content.current) {
                            applyConfig(content.current);
                        }
                        alert("Presets imported successfully!");
                    } catch(err) {
                        alert("Invalid JSON file.");
                    }
                }
            }
            input.click();
        };
        
        // Initial apply to ensure elements reflect DOM
        applyConfig(currentConfig);
    });

})();

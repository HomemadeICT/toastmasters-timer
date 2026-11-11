function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

async function apiPost(url, params) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken(), 'X-Requested-With': 'fetch' },
        body: new URLSearchParams(params)
    });
    let data = null;
    try { data = await res.json(); } catch (e) { /* non-JSON error body */ }
    if (!res.ok) {
        throw new Error((data && data.error) || 'Request failed (' + res.status + ')');
    }
    return data;
}

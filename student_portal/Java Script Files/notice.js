document.addEventListener('DOMContentLoaded', () => {
    const style = document.createElement('style'); style.textContent = `.portal-banner{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:25;padding:13px 18px;border-radius:8px;background:#1769e8;color:#fff}.portal-banner button{margin-left:14px;border:0;background:transparent;color:#fff;font-size:18px;cursor:pointer}`; document.head.appendChild(style);
    const banner = document.createElement('div'); banner.className = 'portal-banner'; banner.innerHTML = 'You have 3 latest notices <button type="button" aria-label="Dismiss notification">&times;</button>'; banner.querySelector('button').onclick = () => banner.remove(); document.body.appendChild(banner);
    document.querySelectorAll('.notices li').forEach(item => item.addEventListener('click', () => { item.style.fontWeight = item.style.fontWeight === '700' ? '' : '700'; }));
});

document.addEventListener('DOMContentLoaded', () => {
    const style = document.createElement('style');
    style.textContent = `.portal-banner{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:25;padding:13px 18px;border-radius:8px;background:#1769e8;color:#fff;box-shadow:0 8px 24px #0003}.portal-banner button{margin-left:14px;border:0;background:transparent;color:#fff;font-size:18px;cursor:pointer}.hero-art img{transition:opacity .5s ease}`;
    document.head.appendChild(style);

    const banner = document.createElement('div');
    banner.className = 'portal-banner';
    banner.innerHTML = 'Welcome back, Dhruv! <button type="button" aria-label="Dismiss notification">&times;</button>';
    banner.querySelector('button').addEventListener('click', () => banner.remove());
    document.body.appendChild(banner);

    const image = document.querySelector('.hero-art img');
    if (image) {
        const images = ['images/campus-building.jpg', 'images/university-quad.jpg', 'images/students-campus.jpg', 'images/lecture-hall.jpg', 'images/study-campus.jpg', 'images/campus-walkway.jpg'];
        let current = 0;
        window.setInterval(() => {
            current = (current + 1) % images.length;
            image.style.opacity = '0';
            window.setTimeout(() => {
            image.src = images[current];
            image.style.opacity = '1';
            }, 250);
        }, 4000);
    }
});

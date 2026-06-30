const revealItems = document.querySelectorAll('.reveal');
const yearNode = document.querySelector('#year');
const parallaxRoot = document.querySelector('[data-parallax-root]');
const parallaxItems = document.querySelectorAll('[data-parallax-item]');

if (yearNode) {
    yearNode.textContent = new Date().getFullYear().toString();
}

const revealObserver = new IntersectionObserver(
    (entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                revealObserver.unobserve(entry.target);
            }
        });
    },
    {
        threshold: 0.16,
    },
);

revealItems.forEach((item) => {
    revealObserver.observe(item);
});

if (parallaxRoot && parallaxItems.length > 0) {
    parallaxRoot.addEventListener('pointermove', (event) => {
        const bounds = parallaxRoot.getBoundingClientRect();
        const offsetX = (event.clientX - bounds.left) / bounds.width - 0.5;
        const offsetY = (event.clientY - bounds.top) / bounds.height - 0.5;

        parallaxItems.forEach((item, index) => {
            const depth = (index + 1) * 7;
            const rotateX = offsetY * -depth;
            const rotateY = offsetX * depth;
            const translateX = offsetX * depth;
            const translateY = offsetY * depth;

            item.style.transform =
                `translate3d(${translateX}px, ${translateY}px, 0) ` +
                `rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
        });
    });

    parallaxRoot.addEventListener('pointerleave', () => {
        parallaxItems.forEach((item) => {
            item.style.transform = '';
        });
    });
}

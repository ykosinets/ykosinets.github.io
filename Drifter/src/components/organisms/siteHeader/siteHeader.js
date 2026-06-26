const menuToggles = [...document.querySelectorAll('.menu-toggle')];
const mobileMenu = document.querySelector('.mobile-menu');
if (menuToggles.length && mobileMenu) {
  const setMenu = (open) => {
    menuToggles.forEach((toggle) => toggle.setAttribute('aria-expanded', String(open)));
    document.body.classList.toggle('menu-open', open);
  };
  const closeMenu = () => setMenu(false);
  menuToggles.forEach((toggle) => toggle.addEventListener('click', () => setMenu(!document.body.classList.contains('menu-open'))));
  mobileMenu.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));
  window.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeMenu(); });
}
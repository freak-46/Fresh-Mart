let nav = document.getElementById('nav-links');
let hamburger = document.getElementById('hamburger');

hamburger.addEventListener('click', () => {
    nav.classList.toggle('unhidden');
})

window.onload = function() {
    // 1. Look for our 'cart_count' cookie
    let name = "cart_count=";
    let decodedCookie = decodeURIComponent(document.cookie);
    let ca = decodedCookie.split(';');
    let count = "0"; // Default to 0 if cookie isn't set yet

    for(let i = 0; i < ca.length; i++) {
        let c = ca[i].trim();
        if (c.indexOf(name) == 0) {
            count = c.substring(name.length, c.length);
        }
    }

    // 2. Put that number directly into your red desktop badge text
    let desktopBadge = document.getElementById('cart-count')
    if (desktopBadge) {
        desktopBadge.textContent = count;
    }
};


/*
 * Builds the footer contact link from a reversed address.
 *
 * The server never writes the plain email address into the HTML, so simple
 * scrapers that read the page source do not find it. The address is stored
 * reversed in `data-contact`; this script restores it, sets the mailto href
 * and un-hides the link. Without JavaScript the link stays hidden and the
 * <noscript> fallback is shown instead.
 */
(function () {
    document.querySelectorAll('a[data-contact]').forEach(function (link) {
        var address = link.getAttribute('data-contact').split('').reverse().join('');
        link.setAttribute('href', 'mailto:' + address);
        link.removeAttribute('data-contact');
        link.hidden = false;
    });
})();

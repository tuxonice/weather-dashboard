/*
 * Applies `data-style` attributes as inline styles.
 *
 * The Content-Security-Policy forbids `style="..."` attributes, so server-
 * rendered inline styles are written as `data-style="..."` and applied here
 * through the CSSOM (element.style), which the policy allows. Call
 * applyInlineStyles(container) after inserting markup from JavaScript.
 */
(function () {
    function applyInlineStyles(root) {
        var scope = root || document;
        if (scope.nodeType === 1 && scope.hasAttribute('data-style')) {
            applyOne(scope);
        }
        if (scope.querySelectorAll) {
            scope.querySelectorAll('[data-style]').forEach(applyOne);
        }
    }

    function applyOne(el) {
        el.style.cssText = el.getAttribute('data-style');
        el.removeAttribute('data-style');
    }

    window.applyInlineStyles = applyInlineStyles;
    applyInlineStyles(document);
})();

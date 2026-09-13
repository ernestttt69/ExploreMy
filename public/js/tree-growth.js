(function () {
    'use strict';
    const tree = document.querySelector('.hero-tree[data-growth-from]');
    if (!tree || !tree.animate || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const previous = Number(tree.dataset.growthFrom);
    const current = Number(tree.dataset.treeLevel);
    if (!Number.isFinite(previous) || !Number.isFinite(current)) return;
    const stage = previous >= 10 ? 'ancient' : previous >= 5 ? 'mature' : previous >= 3 ? 'growing' : previous >= 2 ? 'small' : 'seed';
    const before = tree.cloneNode(true);
    before.className = 'hero-tree tree-stage-' + stage + ' tree-level-' + previous;
    before.removeAttribute('data-growth-from');
    before.setAttribute('aria-hidden', 'true');
    before.style.visibility = 'hidden';
    tree.parentNode.appendChild(before);

    // Interpolate the rendered geometry so each level keeps its own silhouette.
    ['.tree-trunk', '.tree-crown-one'].forEach(function (selector) {
        const element = tree.querySelector(selector);
        const oldStyle = getComputedStyle(before.querySelector(selector));
        const newStyle = getComputedStyle(element);
        const from = {}, to = {};
        ['height', 'width', 'left', 'bottom', 'borderRadius', 'boxShadow'].forEach(function (property) {
            from[property] = oldStyle[property];
            to[property] = newStyle[property];
        });
        element.style.animation = 'none';
        // Keep the trunk's translateX(-50%) so its center stays under the canopy.
        element.animate([from, to], { duration: 1100, easing: 'cubic-bezier(.22,.7,.3,1)' });
    });
    before.remove();

    const canopy = tree.querySelector('.tree-crown-one');
    canopy.animate([
        { transform: 'scale(1)' },
        { transform: 'scale(1.06,.96)', offset: .4 },
        { transform: 'scale(.98,1.03)', offset: .7 },
        { transform: 'scale(1)' }
    ], { delay: 1000, duration: 650, easing: 'ease-in-out' });

    if (current > previous) {
        tree.animate([
            { filter: 'drop-shadow(0 0 0 transparent)' },
            { filter: 'drop-shadow(0 0 20px rgba(255,218,115,.85))', offset: .45 },
            { filter: 'drop-shadow(0 0 0 transparent)' }
        ], { duration: 1900, easing: 'ease-in-out' });
    }
}());

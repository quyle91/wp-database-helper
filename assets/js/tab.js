document.addEventListener('DOMContentLoaded', function () {
    // Event delegation cho tất cả button trong ___tab_nav
    document.addEventListener('click', function (e) {
        const button = e.target.closest('.___tab_nav .button');
        if (!button) return;

        const tabNav = button.closest('.___tab_nav');
        if (!tabNav) return;

        const tabGroup = tabNav.getAttribute('tab_group');
        const container = tabNav.closest('[data-has-tabs]') || document;
        // dùng attr tùy ý (vd: data-has-tabs) để đánh dấu wrap, fallback = document

        // hide all tab content cùng group
        container.querySelectorAll('.___tab_content[tab_group="' + tabGroup + '"]').forEach(tabContent => {
            tabContent.classList.add('hidden');
        });

        // show tab content theo data-id
        const _id = button.getAttribute('data-id');
        container.querySelectorAll('.___tab_content[tab_group="' + tabGroup + '"][data-id="' + _id + '"]').forEach(tabContent => {
            tabContent.classList.remove('hidden');
        });

        // toggle class cho button
        tabNav.querySelectorAll('.button').forEach(btn => {
            btn.classList.add("button-primary");
            btn.classList.remove("zactive");
        });
        button.classList.remove('button-primary');
        button.classList.add('zactive');
    });

    // Auto click tab đầu tiên (init state)
    document.querySelectorAll('.___tab_nav').forEach(tabNav => {
        const firstBtn = tabNav.querySelector('.button');
        if (firstBtn) firstBtn.click();
    });
});

(function () {
    const form = document.querySelector('.catalog-search');
    const input = document.getElementById('catalogSearchInput');
    const category = form?.querySelector('select[name="category"]');
    const suggestions = document.getElementById('catalogSearchSuggestions');
    let request = null;

    if (!form || !input || !suggestions) return;

    function hideSuggestions() {
        suggestions.hidden = true;
        suggestions.innerHTML = '';
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>'"]/g, function (character) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'}[character];
        });
    }

    function renderSuggestions(items) {
        if (!items.length) {
            hideSuggestions();
            return;
        }

        suggestions.innerHTML = items.map(function (item) {
            return '<a class="catalog-search-suggestion" href="' + escapeHtml(item.url) + '">'
                + '<img src="' + escapeHtml(item.image) + '" alt="">'
                + '<span><strong>' + escapeHtml(item.name) + '</strong><small>' + escapeHtml(item.category) + '</small></span>'
                + '</a>';
        }).join('');
        suggestions.hidden = false;
    }

    async function loadSuggestions() {
        const query = input.value.trim();
        if (query.length < 2) {
            hideSuggestions();
            return;
        }

        if (request) request.abort();
        request = new AbortController();
        const params = new URLSearchParams({ q: query });
        if (category?.value) params.set('category', category.value);

        try {
            const response = await fetch(form.action.replace('search.php', 'search_suggestions.php') + '?' + params, {
                signal: request.signal,
                headers: { Accept: 'application/json' }
            });
            if (response.ok) renderSuggestions(await response.json());
        } catch (error) {
            if (error.name !== 'AbortError') hideSuggestions();
        }
    }

    input.addEventListener('input', loadSuggestions);
    category?.addEventListener('change', loadSuggestions);
    input.addEventListener('focus', loadSuggestions);
    document.addEventListener('click', function (event) {
        if (!form.contains(event.target)) hideSuggestions();
    });
})();

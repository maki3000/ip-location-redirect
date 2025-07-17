jQuery(() => {
    const url = new URL(window.location.href);
    const params = new URLSearchParams(url.search);

    // Define the parameters to remove
    const paramsToRemove = [
        'redirected',
        'ip_location_redirected_to',
        'redirect_chosen',
        'redirected_to',
        'redirect_to',
    ];
    let paramsRemoved = false;

    // Check for and delete only the specified parameters
    paramsToRemove.forEach(paramKey => {
        if (params.has(paramKey)) {
            params.delete(paramKey);
            paramsRemoved = true;
        }
    });

    // If any parameters were removed, update the URL using history.replaceState
    if (paramsRemoved) {
        const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + window.location.hash;
        history.replaceState(null, '', newUrl);
    }
});

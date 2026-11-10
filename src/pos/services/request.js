import { isInternetConnected } from './connectivity';
import { getPosObject } from './runtime';
import { hasEntries } from '../utils/value';

const isLocalApiRequest = url => {
    const requestUrl = String(url || '');
    const siteUrl = getPosObject().site_url || '';

    return (siteUrl && requestUrl.includes(siteUrl)) || requestUrl.startsWith('/');
};
const getProgressBarElement = () => document.querySelector('.ddfcpos-progress-bar');
const buildRequestOptions = (url, requestBody) => ({
    method: 'POST',
    headers: buildRequestHeaders(url),
    body: JSON.stringify(requestBody),
});
const isUnauthorizedResponse = response => hasEntries(response) && response.success === false && response.status === 401;
const UNAUTHORIZED_RETRY_LIMIT = 1;
const parseResponseJson = response => response.json().catch(() => ([]));
const toggleProgressBar = isVisible => {
    const progressBarElement = getProgressBarElement();

    if (progressBarElement && progressBarElement.classList.contains('ddfcpos-progress-bar')) {
        progressBarElement.style.display = isVisible ? 'block' : 'none';
    }
};
const buildRequestHeaders = url => {
    const requestHeaders = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    };

    if (isLocalApiRequest(url)) {
        requestHeaders['X-WP-Nonce'] = getPosObject().restNonce || '';
    }

    return new Headers(requestHeaders);
};

export const fetchRequest = (url, post, retryCount = 0) => {
    const requestBody = {
        ...post,
        cashier_id: getPosObject().user && getPosObject().user.ID,
    };

    toggleProgressBar(true);

    if (!isInternetConnected()) {
        return Promise.resolve([]);
    }

    if (!url) {
        toggleProgressBar(false);
        return Promise.resolve([]);
    }

    return fetch(url, buildRequestOptions(url, requestBody))
        .then(parseResponseJson)
        .then(response => {
            if (isUnauthorizedResponse(response)) {
                if (retryCount >= UNAUTHORIZED_RETRY_LIMIT) {
                    return [];
                }

                return fetchRequest(url, requestBody, retryCount + 1);
            }

            return response;
        })
        .catch(() => {
            return [];
        })
        .finally(() => {
            toggleProgressBar(false);
        });
}

const LOCAL_HOSTNAMES = ['localhost', '127.0.0.1'];
const isLocalHost = hostname => LOCAL_HOSTNAMES.includes(hostname);
const isBrowserOnline = () => navigator.onLine;
const getCurrentHostname = () => (window.location && window.location.hostname ? window.location.hostname : '');

export const isInternetConnected = () => (isLocalHost(getCurrentHostname()) ? true : isBrowserOnline());

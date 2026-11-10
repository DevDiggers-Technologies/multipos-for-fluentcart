import { getPosConfig, getPosObject } from './runtime';

const getPosEndpoint = () => getPosConfig().endpoint || '';
const buildPosPath = suffix => `${getPosEndpoint()}${suffix}`;
const getPosSiteUrl = () => ( getPosObject().siteUrl || '' ).replace( /\/$/, '' );
const sanitizeRouteSuffix = suffix => suffix || '';

export const getPosBasePath = () => `${getPosSiteUrl()}/${buildPosPath( '' )}`;

export const getPosRoute = ( suffix = '' ) => `${getPosBasePath()}${sanitizeRouteSuffix( suffix )}`;

export const getRoutePath = ( suffix = '' ) => `/${buildPosPath( sanitizeRouteSuffix( suffix ) )}`;

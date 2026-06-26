// Corex.dev k6 Performance Test Configuration

export const BASE_URL = __ENV.BASE_URL || 'http://localhost:8080';
export const AI_GATEWAY_URL = __ENV.AI_GATEWAY_URL || 'http://localhost:8000';
export const API_URL = `${BASE_URL}/api`;

export const DEFAULT_HEADERS = {
  'Content-Type': 'application/json',
  'User-Agent': 'Corex-k6/1.0',
};

export const AUTH_HEADERS = {
  ...DEFAULT_HEADERS,
  'Authorization': `Bearer ${__ENV.AUTH_TOKEN || 'test-token'}`,
};

export const THRESHOLDS = {
  http_req_failed: ['rate<0.01'],
  http_req_duration: ['p(95)<2000', 'p(99)<5000'],
  iterations: [`count>${__ENV.ITERATIONS || 100}`],
};

export const OPTIONS_DEFAULTS = {
  discardResponseBodies: true,
  noCookieReset: true,
  setupTimeout: '30s',
  teardownTimeout: '30s',
};

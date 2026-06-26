// Corex.dev Smoke Testing Script
// Quick sanity check: 1-2 VUs, short duration, all endpoints
//
// Usage:
//   k6 run k6/scripts/smoke.js

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { BASE_URL, API_URL, AI_GATEWAY_URL, DEFAULT_HEADERS } from './config.js';

export const options = {
  vus: 2,
  duration: '30s',
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<2000'],
  },
};

export default function () {
  group('Smoke: Frontend', () => {
    const resp = http.get(`${BASE_URL}/`);
    check(resp, { 'landing page loads': (r) => r.status === 200 });
  });

  group('Smoke: Backend API', () => {
    const resp = http.get(`${API_URL}/health`);
    check(resp, { 'backend health': (r) => r.status === 200 && r.json('status') === 'ok' });
  });

  group('Smoke: AI Gateway', () => {
    const resp = http.get(`${AI_GATEWAY_URL}/health`);
    check(resp, { 'gateway health': (r) => r.status === 200 });
  });

  group('Smoke: Agent Workflows', () => {
    const resp = http.get(`${AI_GATEWAY_URL}/v1/agent/workflows`);
    check(resp, {
      'workflows endpoint': (r) => r.status === 200,
      'has workflows': (r) => r.json('workflows').length > 0,
    });
  });

  sleep(1);

  group('Smoke: Console', () => {
    const resp = http.get(`${BASE_URL}/console`);
    check(resp, { 'console loads': (r) => r.status < 500 });
  });
}

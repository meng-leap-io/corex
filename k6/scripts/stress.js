// Corex.dev Stress Testing Script
// Finds breaking point: ramps from 0 to 500 users over 15 minutes
//
// Usage:
//   k6 run k6/scripts/stress.js
//   k6 run k6/scripts/stress.js -e BASE_URL=https://staging.corex.dev

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Trend } from 'k6/metrics';
import { BASE_URL, API_URL, AI_GATEWAY_URL, DEFAULT_HEADERS } from './config.js';

const errorRate = new Rate('stress_errors');
const responseTime = new Trend('stress_response_time');

export const options = {
  stages: [
    { duration: '2m', target: 50 },
    { duration: '3m', target: 100 },
    { duration: '3m', target: 200 },
    { duration: '3m', target: 300 },
    { duration: '3m', target: 500 },
    { duration: '2m', target: 0 },
  ],
  thresholds: {
    http_req_failed: ['rate<0.10'],
    http_req_duration: ['p(95)<10000', 'p(99)<15000'],
    stress_errors: ['rate<0.15'],
  },
  noConnectionReuse: true,
  userAgent: 'Corex-k6-stress/1.0',
};

export default function () {
  const endpoints = [
    { url: `${BASE_URL}/health`, name: 'health' },
    { url: `${AI_GATEWAY_URL}/health`, name: 'gateway_health' },
    { url: `${AI_GATEWAY_URL}/v1/agent/workflows`, name: 'workflows' },
    { url: `${API_URL}/health`, name: 'api_health' },
  ];

  const endpoint = endpoints[Math.floor(Math.random() * endpoints.length)];

  group(`Stress: ${endpoint.name}`, () => {
    const resp = http.get(endpoint.url, {
      headers: DEFAULT_HEADERS,
      tags: { endpoint: endpoint.name },
    });

    check(resp, {
      [`${endpoint.name} under stress`]: (r) => r.status < 500,
    });

    responseTime.add(resp.timings.duration);
    errorRate.add(resp.status >= 500);
  });

  if (__VU % 3 === 0) {
    group('Auth under stress', () => {
      const payload = JSON.stringify({
        email: `stress-user-${__VU}@corex.dev`,
        password: 'TestPass123!',
      });

      const resp = http.post(`${API_URL}/auth/login`, payload, {
        headers: DEFAULT_HEADERS,
        tags: { endpoint: 'auth_stress' },
      });

      check(resp, { 'auth under 500': (r) => r.status < 500 });
      errorRate.add(resp.status >= 500);
      responseTime.add(resp.timings.duration);
    });
  }

  sleep(Math.random() * 2 + 0.5);
}

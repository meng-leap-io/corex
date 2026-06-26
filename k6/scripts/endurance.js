// Corex.dev Endurance / Soak Testing Script
// Validates system stability under sustained load over 4 hours
//
// Usage:
//   k6 run k6/scripts/endurance.js
//   k6 run k6/scripts/endurance.js -e BASE_URL=https://staging.corex.dev

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Trend, Gauge } from 'k6/metrics';
import { BASE_URL, API_URL, AI_GATEWAY_URL, DEFAULT_HEADERS, AUTH_HEADERS } from './config.js';

const errorRate = new Rate('endurance_errors');
const avgLatency = new Trend('endurance_latency');
const memoryUsage = new Gauge('endurance_memory');

export const options = {
  stages: [
    { duration: '5m', target: 30 },
    { duration: '230m', target: 30 },
    { duration: '5m', target: 0 },
  ],
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<5000', 'p(99)<8000'],
    http_req_waiting: ['p(95)<4000'],
    endurance_errors: ['rate<0.02'],
  },
  summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)', 'count'],
};

const SEQUENCE = [
  { url: `${BASE_URL}/health`, method: 'GET', group: 'health' },
  { url: `${AI_GATEWAY_URL}/health`, method: 'GET', group: 'gateway' },
  { url: `${API_URL}/health`, method: 'GET', group: 'api_health' },
];

export default function () {
  const elapsed = __ITER * __VU;

  group('Endurance: Health Checks', () => {
    for (const endpoint of SEQUENCE) {
      const resp = http.get(endpoint.url, {
        headers: DEFAULT_HEADERS,
        tags: { stage: 'endurance', endpoint: endpoint.group },
      });

      check(resp, {
        [`${endpoint.group} healthy after ${elapsed}s`]: (r) => r.status === 200,
      });

      errorRate.add(resp.status !== 200);
      avgLatency.add(resp.timings.duration);
    }
  });

  sleep(5);

  group('Endurance: User Operations', () => {
    const userIdx = __VU % 50;
    const loginPayload = JSON.stringify({
      email: `endurance-user-${userIdx}@corex.dev`,
      password: 'EndurancePass789!',
    });

    let resp = http.post(`${API_URL}/auth/login`, loginPayload, {
      headers: DEFAULT_HEADERS,
      tags: { stage: 'endurance', endpoint: 'auth' },
    });
    errorRate.add(resp.status !== 200);
    avgLatency.add(resp.timings.duration);
    check(resp, { 'auth works': (r) => r.status === 200 });

    const token = resp.json('token');
    const headers = token
      ? { ...DEFAULT_HEADERS, Authorization: `Bearer ${token}` }
      : AUTH_HEADERS;

    resp = http.get(`${API_URL}/user`, {
      headers,
      tags: { stage: 'endurance', endpoint: 'profile' },
    });
    errorRate.add(resp.status !== 200);
    avgLatency.add(resp.timings.duration);
  });

  sleep(10);

  group('Endurance: Agent Workflows', () => {
    const resp = http.get(`${AI_GATEWAY_URL}/v1/agent/workflows`, {
      headers: DEFAULT_HEADERS,
      tags: { stage: 'endurance', endpoint: 'agent_workflows' },
    });

    check(resp, { 'agent workflows': (r) => r.status === 200 });
    errorRate.add(resp.status !== 200);
    avgLatency.add(resp.timings.duration);

    if (__VU % 5 === 0) {
      const detailResp = http.get(`${AI_GATEWAY_URL}/v1/agent/workflows/build_blog_website`, {
        headers: DEFAULT_HEADERS,
        tags: { stage: 'endurance', endpoint: 'agent_detail' },
      });
      errorRate.add(detailResp.status !== 200);
    }
  });

  sleep(Math.random() * 5 + 5);
}

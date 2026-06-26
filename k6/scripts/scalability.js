// Corex.dev Scalability Testing Script
// Tests horizontal scaling: sudden traffic spikes and recovery
//
// Usage:
//   k6 run k6/scripts/scalability.js
//   k6 run k6/scripts/scalability.js -e BASE_URL=https://staging.corex.dev

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Trend } from 'k6/metrics';
import { BASE_URL, API_URL, AI_GATEWAY_URL, DEFAULT_HEADERS } from './config.js';

const errorRate = new Rate('scalability_errors');
const spikeLatency = new Trend('spike_latency');

export const options = {
  stages: [
    { duration: '1m', target: 20 },
    { duration: '30s', target: 200 },
    { duration: '30s', target: 200 },
    { duration: '1m', target: 20 },
    { duration: '30s', target: 500 },
    { duration: '30s', target: 500 },
    { duration: '1m', target: 20 },
    { duration: '30s', target: 1000 },
    { duration: '30s', target: 1000 },
    { duration: '1m', target: 20 },
  ],
  thresholds: {
    http_req_failed: ['rate<0.15'],
    http_req_duration: ['p(90)<15000'],
    scalability_errors: ['rate<0.20'],
  },
};

const SCENARIOS = ['health', 'gateway', 'api', 'workflows'];

export default function () {
  const scenario = SCENARIOS[__VU % SCENARIOS.length];

  group(`Scalability: ${scenario}`, () => {
    let url;
    switch (scenario) {
      case 'health':
        url = `${BASE_URL}/health`;
        break;
      case 'gateway':
        url = `${AI_GATEWAY_URL}/health`;
        break;
      case 'api':
        url = `${API_URL}/health`;
        break;
      case 'workflows':
        url = `${AI_GATEWAY_URL}/v1/agent/workflows`;
        break;
    }

    const start = Date.now();
    const resp = http.get(url, {
      headers: DEFAULT_HEADERS,
      tags: { scenario, stage: __ITER < 5 ? 'ramp_up' : 'spike' },
    });
    spikeLatency.add(Date.now() - start);

    check(resp, {
      [`${scenario} handles spike`]: (r) => r.status < 500,
    });
    errorRate.add(resp.status >= 500);
  });

  sleep(Math.random() * 1 + 0.2);
}

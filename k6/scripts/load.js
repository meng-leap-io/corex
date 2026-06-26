// Corex.dev Load Testing Script
// Simulates normal daily traffic: 50 concurrent users, 10 minute duration
//
// Usage:
//   k6 run k6/scripts/load.js
//   k6 run k6/scripts/load.js -e BASE_URL=https://staging.corex.dev

import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Trend, Counter } from 'k6/metrics';
import { BASE_URL, API_URL, AI_GATEWAY_URL, DEFAULT_HEADERS, AUTH_HEADERS } from './config.js';

const errorRate = new Rate('errors');
const apiLatency = new Trend('api_latency');
const aiLatency = new Trend('ai_latency');

export const options = {
  stages: [
    { duration: '2m', target: 10 },
    { duration: '5m', target: 50 },
    { duration: '3m', target: 50 },
    { duration: '2m', target: 0 },
  ],
  thresholds: {
    http_req_failed: ['rate<0.02'],
    http_req_duration: ['p(95)<3000', 'p(99)<5000'],
    api_latency: ['p(95)<2000'],
    ai_latency: ['p(95)<5000'],
    errors: ['rate<0.05'],
  },
};

export default function () {
  group('Health Checks', () => {
    const resp = http.get(`${BASE_URL}/health`, { tags: { type: 'health' } });
    check(resp, { 'health returns 200': (r) => r.status === 200 });
    errorRate.add(resp.status !== 200);
  });

  group('API Gateway Health', () => {
    const resp = http.get(`${AI_GATEWAY_URL}/health`, { tags: { type: 'health_gateway' } });
    check(resp, { 'gateway health returns 200': (r) => r.status === 200 });
    errorRate.add(resp.status !== 200);
  });

  sleep(1);

  group('Authentication', () => {
    const payload = JSON.stringify({
      email: `user${__VU}@corex.dev`,
      password: 'TestPass123!',
    });

    const start = Date.now();
    const resp = http.post(`${API_URL}/auth/login`, payload, {
      headers: DEFAULT_HEADERS,
      tags: { type: 'auth' },
    });
    apiLatency.add(Date.now() - start);

    check(resp, {
      'login returns 200': (r) => r.status === 200,
      'login has token': (r) => r.json('token') !== undefined,
    });
    errorRate.add(resp.status !== 200);
  });

  sleep(2);

  group('Chat Completion', () => {
    const payload = JSON.stringify({
      model: 'gpt-4o-mini',
      messages: [
        { role: 'system', content: 'You are a helpful assistant.' },
        { role: 'user', content: `Write a PHP function to sort an array. Iteration ${__ITER}` },
      ],
      max_tokens: 100,
    });

    const start = Date.now();
    const resp = http.post(`${AI_GATEWAY_URL}/v1/chat/completions`, payload, {
      headers: AUTH_HEADERS,
      tags: { type: 'chat' },
    });
    aiLatency.add(Date.now() - start);

    check(resp, {
      'chat returns 200': (r) => r.status === 200,
      'chat has choices': (r) => r.json('choices') !== undefined,
    });
    errorRate.add(resp.status !== 200);
  });

  sleep(3);

  group('User Profile', () => {
    const resp = http.get(`${API_URL}/user`, { headers: AUTH_HEADERS });
    check(resp, { 'profile returns 200': (r) => r.status === 200 });
    errorRate.add(resp.status !== 200);
  });

  sleep(2);

  group('Agent Workflows', () => {
    const resp = http.get(`${AI_GATEWAY_URL}/v1/agent/workflows`, {
      headers: DEFAULT_HEADERS,
      tags: { type: 'agent_workflows' },
    });

    check(resp, {
      'workflows list returns 200': (r) => r.status === 200,
      'workflows has items': (r) => r.json('workflows').length > 0,
    });
    errorRate.add(resp.status !== 200);
  });

  sleep(Math.random() * 3 + 1);
}

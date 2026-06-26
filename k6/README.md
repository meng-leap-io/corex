# Corex.dev Performance Testing (k6)

## Quick Start

```bash
# Smoke test (2 VUs, 30s)
k6 run k6/scripts/smoke.js

# Load test (50 concurrent users, 10 min)
k6 run k6/scripts/load.js

# Stress test (ramp to 500 users)
k6 run k6/scripts/stress.js

# Endurance test (2h soak)
k6 run k6/scripts/endurance.js

# Scalability test (spikes to 1000 users)
k6 run k6/scripts/scalability.js
```

## Pointing at Different Environments

```bash
k6 run k6/scripts/load.js \
  -e BASE_URL=https://staging.corex.dev \
  -e AI_GATEWAY_URL=https://api-staging.corex.dev \
  -e AUTH_TOKEN=your-token \
  -e ITERATIONS=500
```

## Test Descriptions

| Script | Type | Pattern | Metrics |
|--------|------|---------|---------|
| `smoke.js` | Sanity | 2 VUs, 30s, all endpoints | Quick pass/fail |
| `load.js` | Load | 10→50→0 over 10 min | p95 < 3s, error < 2% |
| `stress.js` | Stress | 50→500 over 15 min | Find breaking point |
| `endurance.js` | Soak | 30 VUs for 4 hours | Memory leaks, stability |
| `scalability.js` | Spike | 20→200→500→1000 VUs | Recovery after spikes |

## Scenarios Covered

- Health checks (frontend, backend, gateway)
- Authentication (login, profile fetch)
- Chat completions (AI Gateway non-streaming)
- Agent workflow listing and detail
- Static asset serving
- Concurrent user ramp-up/down
- Sustained load over hours
- Sudden traffic spikes

## Local vs CI

Local:
```bash
k6 run k6/scripts/smoke.js --out json=reports/smoke.json
k6 run k6/scripts/load.js --out json=reports/load.json
```

CI (GitHub Actions would use grafana/k6 Docker image):
```bash
docker run --rm -i grafana/k6 run - <k6/scripts/smoke.js
```

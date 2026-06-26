# Security Hardening Checklist

## Legend
- ✅ Implemented
- ⏳ In Progress
- ❌ Not Yet Planned

---

## Backend (Laravel 11)

### Input Validation
| # | Check | Status | Location | Notes |
|---|-------|--------|----------|-------|
| 1.1 | All API inputs validated via Form Requests | ✅ | `app/Http/Requests/` | PSR-4 FormRequest classes for every mutation endpoint |
| 1.2 | Custom validators for XSS/SQLi blocking | ✅ | `app/Providers/SecurityServiceProvider.php` | `no_scripts`, `no_sql`, `safe_string` rules |
| 1.3 | `safe_string` validator applied to text fields | ⏳ | Form Requests | Add `safe_string` rule to name/description/content fields |
| 1.4 | Input length limits on all string fields | ✅ | Form Requests | `max:255` or `max:5000` constraints |

### SQL Injection Prevention
| # | Check | Status | Location | Notes |
|---|-------|--------|----------|-------|
| 2.1 | Eloquent ORM used instead of raw queries | ✅ | `app/` | No `DB::raw()` or `DB::select()` in business logic |
| 2.2 | Parameterized queries for any raw SQL | ✅ | `app/` | Parameter binding via Eloquent |
| 2.3 | `safe_string` validator catches SQL patterns | ✅ | `SecurityServiceProvider` | Blocks UNION SELECT, DROP TABLE, etc. |

### XSS Prevention
| # | Check | Status | Location | Notes |
|---|-------|--------|----------|-------|
| 3.1 | Blade auto-escapes `{{ }}` output | ✅ | `resources/views/` | All views use `{{ }}` not `{!! !!}` |
| 3.2 | CSP header configured | ✅ | `SecurityHeaders.php`, nginx `ssl.conf` | `script-src 'self' 'unsafe-inline' 'unsafe-eval' https://...` |
| 3.3 | Input sanitization middleware rejects `<script>` | ✅ | `ValidateUserAgent.php` | Blocks script tags in query params |
| 3.4 | X-XSS-Protection header | ✅ | `SecurityHeaders.php` | `1; mode=block` |
| 3.5 | `@sanitize` Blade directive | ✅ | `SecurityServiceProvider` | `<?php echo e(strip_tags($expression)); ?>` |

### CSRF Protection
| # | Check | Status | Location | Notes |
|---|-------|--------|----------|-------|
| 4.1 | Sanctum token-based API auth | ✅ | `bootstrap/app.php` | `EnsureFrontendRequestsAreStateful` middleware |
| 4.2 | CSRF token on web routes | ✅ | Laravel default | `web` middleware group |
| 4.3 | CORS restricted to known origins | ✅ | `SetCorsHeaders.php`, nginx `cors_origins` | `corex.dev`, `console.corex.dev` |
| 4.4 | `@csrf` Blade directive | ✅ | `SecurityServiceProvider` | Registered as Blade directive |

### Authentication & Authorization
| # | Check | Status | Location | Notes |
|---|-------|--------|----------|-------|
| 5.1 | Sanctum tokens with expiry | ✅ | `AuthService.php` | `addDays($this->tokenExpirationDays)` |
| 5.2 | Rate limiting on login | ✅ | nginx `limit_req zone=login:10m rate=10r/m` | 10 requests per minute |
| 5.3 | Brute force protection middleware | ✅ | `BruteForceProtection.php` | 5 attempts → block 15 min after 3 cycles |
| 5.4 | Password validation rules | ✅ | `RegisterRequest.php` | `Password::defaults()` (min 8, mixed case, etc.) |
| 5.5 | Email verification | ✅ | `AuthService.php` | `sendEmailVerification`, `markAsVerified` |
| 5.6 | No email enumeration in forgot/reset password | ✅ | `ForgotPasswordRequest.php`, `ResetPasswordRequest.php` | Removed `exists:users,email` validation |
| 5.7 | Token revocation on password reset | ✅ | `AuthService.php` | `revokeAllTokens($user)` |
| 5.8 | All tokens revocable individually | ✅ | `AuthService.php` | `revokeTokenById` |

### Security Headers
| # | Check | Status | Location | Notes |
|---|-------|--------|----------|-------|
| 6.1 | Strict-Transport-Security | ✅ | `SecurityHeaders.php`, nginx | `max-age=63072000; includeSubDomains; preload` |
| 6.2 | X-Frame-Options | ✅ | `SecurityHeaders.php`, nginx | `SAMEORIGIN` |
| 6.3 | X-Content-Type-Options | ✅ | `SecurityHeaders.php`, nginx | `nosniff` |
| 6.4 | Content-Security-Policy | ✅ | `SecurityHeaders.php`, nginx | Multi-directive CSP |
| 6.5 | Referrer-Policy | ✅ | `SecurityHeaders.php`, nginx | `strict-origin-when-cross-origin` |
| 6.6 | Permissions-Policy | ✅ | `SecurityHeaders.php`, nginx | Camera/mic/geo disabled |
| 6.7 | Cross-Origin-* policies | ✅ | `SecurityHeaders.php` | COEP require-corp, COOP same-origin, CORP same-origin |
| 6.8 | Server header removed | ✅ | `SecurityHeaders.php`, nginx | `more_clear_headers Server` |

### Request Validation
| # | Check | Status | Location | Notes |
|---|-------|--------|----------|-------|
| 7.1 | Suspicious User-Agent blocking | ✅ | `ValidateUserAgent.php` | Blocks 28 common scanning tools/bots |
| 7.2 | Blocked file extensions | ✅ | `ValidateUserAgent.php` | `.env`, `.sql`, `.bak`, `.swp`, etc. |
| 7.3 | Blocked paths enumeration | ✅ | `ValidateUserAgent.php` | `wp-admin`, `.git`, `vendor/`, etc. |
| 7.4 | Suspicious parameter detection | ✅ | `ValidateUserAgent.php` | SQLi, XSS, path traversal in query params |
| 7.5 | Inter-service request signing | ✅ | `RequestSigning.php` | HMAC-SHA256 with app key |
| 7.6 | Clock skew check for signatures | ✅ | `RequestSigning.php` | `MAX_CLOCK_SKEW = 300` seconds |

---

## AI Gateway (FastAPI Python)

### Input Sanitization
| # | Check | Status | Location | Notes |
|---|-------|--------|----------|-------|
| 8.1 | HTML/script tag detection | ✅ | `InputSanitizer` | 13 HTML injection patterns |
| 8.2 | SQL injection pattern detection | ✅ | `InputSanitizer` | 17 SQL injection patterns |
| 8.3 | Path traversal detection | ✅ | `InputSanitizer` | `../`, system files |
| 8.4 | Command injection detection | ✅ | `InputSanitizer` | Shell metacharacters, backtick commands |
| 8.5 | Object depth/string length limits | ✅ | `InputSanitizer` | `max_depth=5`, `max_length=10000` |
| 8.6 | Request body size limit | ✅ | `InputValidationMiddleware` | 10MB limit |

### Output Filtering
| # | Check | Status | Location | Notes |
|---|-------|--------|----------|-------|
| 9.1 | Sensitive field redaction | ✅ | `OutputFilter` | 23 sensitive field name patterns |
| 9.2 | API key regex redaction | ✅ | `OutputFilter` | `sk-...`, `pk-...` patterns |
| 9.3 | Credit card number redaction | ✅ | `OutputFilter` | `****-****-****-****` |
| 9.4 | Error message sanitization | ✅ | `OutputFilter` | Removes paths, file, line info |
| 9.5 | AI prompt output sanitization | ✅ | `OutputFilter` | `sanitize_prompt_output()` |

### API Key Management
| # | Check | Status | Location | Notes |
|---|-------|--------|----------|-------|
| 10.1 | Secure key generation (256-bit) | ✅ | `APIKeyManager` | `secrets.token_bytes(32)` |
| 10.2 | Key hashing with SHA-512 | ✅ | `APIKeyManager` | `hashlib.sha512` |
| 10.3 | Key rotation support | ✅ | `APIKeyManager` | `rotate_key()` method |
| 10.4 | Key revocation | ✅ | `APIKeyManager` | `revoke_key()` with timestamp |
| 10.5 | Expired key cleanup | ✅ | `APIKeyManager` | `cleanup_expired_keys()` |
| 10.6 | Constant-time comparison | ✅ | `APIKeyManager` | `hmac.compare_digest` |

### Rate Limiting
| # | Check | Status | Location | Notes |
|---|-------|--------|----------|-------|
| 11.1 | Per-endpoint rate limits | ✅ | `RateLimitMiddleware` | Strict: 20/60 chat, 10/60 agent |
| 11.2 | Redis-backed distributed limiting | ✅ | `RateLimitMiddleware` | Falls back to local Dict |
| 11.3 | Per-IP tracking | ✅ | `RateLimitMiddleware` | `client_ip:path` key |
| 11.4 | 429 response with Retry-After | ✅ | `RateLimitMiddleware` | `headers={"Retry-After": str(window)}` |

### Security Headers
| # | Check | Status | Location | Notes |
|---|-------|--------|----------|-------|
| 12.1 | All standard security headers | ✅ | `SecurityHeadersMiddleware` | HSTS, XFO, XCTO, XXSS, RP, PP, COEP, COOP, CORP |
| 12.2 | Server header removed | ✅ | `SecurityHeadersMiddleware` | `del response.headers["Server"]` |

---

## Infrastructure (Nginx/Docker/K8s)

### Nginx Security
| # | Check | Status | Location | Notes |
|---|-------|--------|----------|-------|
| 13.1 | `server_tokens off` | ✅ | `nginx.conf` | Hides nginx version |
| 13.2 | TLS 1.2/1.3 only | ✅ | `nginx.conf`, `ssl.conf` | No SSLv3/TLSv1.0/1.1 |
| 13.3 | Strong ciphers | ✅ | `nginx.conf`, `ssl.conf` | ECDHE + AES-GCM + CHACHA20 |
| 13.4 | HSTS preload | ✅ | `nginx.conf` | `max-age=63072000; includeSubDomains; preload` |
| 13.5 | OCSP stapling | ✅ | `ssl.conf` | `ssl_stapling on` |
| 13.6 | Session tickets disabled | ✅ | `ssl.conf` | `ssl_session_tickets off` |

### WAF Rules
| # | Check | Status | Location | Notes |
|---|-------|--------|----------|-------|
| 14.1 | SQL injection blocking | ✅ | `waf.conf` | UNION SELECT, DROP TABLE, etc. |
| 14.2 | XSS blocking | ✅ | `waf.conf` | Script tags, event handlers, `javascript:` |
| 14.3 | Path traversal blocking | ✅ | `waf.conf` | `../`, `/etc/passwd`, `.git/config` |
| 14.4 | Remote file inclusion blocking | ✅ | `waf.conf` | `file=http://`, `include=http://` |
| 14.5 | Command injection blocking | ✅ | `waf.conf` | Shell pipes, `id`, `whoami`, etc. |
| 14.6 | HTTP verb restriction | ✅ | `waf.conf` | Blocks CONNECT, TRACE, TRACK, DEBUG |
| 14.7 | Malicious upload blocking | ✅ | `waf.conf` | PHP, Perl, Python content types |

### DDoS Protection
| # | Check | Status | Location | Notes |
|---|-------|--------|----------|-------|
| 15.1 | Connection limiting per IP | ✅ | `ddos.conf` | `limit_conn addr_per_ip 10` |
| 15.2 | Request rate limiting per IP | ✅ | `ddos.conf` | `rate=30r/s` burst 20 |
| 15.3 | Slowloris protection | ✅ | `ddos.conf` | `client_body_timeout 10s`, `client_header_timeout 10s` |
| 15.4 | Global rate limiting zones | ✅ | `nginx.conf` | API 100r/s, Login 10r/m, WP 30r/m |

### Sensitive File Protection
| # | Check | Status | Location | Notes |
|---|-------|--------|----------|-------|
| 16.1 | Dotfiles blocked | ✅ | `default.conf` | `location ~ /\.(?!well-known)` |
| 16.2 | Env/config file blocking | ✅ | `default.conf` | `.env`, `composer.json`, `artisan`, `storage` |

### Monitoring & Audit
| # | Check | Status | Location | Notes |
|---|-------|--------|----------|-------|
| 17.1 | Structured JSON logging | ✅ | `nginx.conf` | JSON log format |
| 17.2 | WAF blocked request logging | ✅ | `security.conf` | `/var/log/nginx/waf_blocked.log` |
| 17.3 | Rate limit violation logging | ✅ | `ddos.conf`, all rate limit middleware | structlog + `limit_req_log_level warn` |

---

## Password & Secrets Policy

| # | Check | Status | Location | Notes |
|---|-------|--------|----------|-------|
| 18.1 | Passwords hashed with bcrypt | ✅ | Laravel default | `Hash::make()` |
| 18.2 | API keys stored in env vars | ✅ | `.env`, `pydantic-settings` | No keys in DB or code |
| 18.3 | JWT secret configured | ⏳ | `config.py` | Add dev fallback if JWT_SECRET empty |
| 18.4 | Redis password configurable | ✅ | `config.py`, `config/database.php` | `redis_password` env var |
| 18.5 | Database password env-based | ✅ | `.env.example` | `DB_PASSWORD` env var |

---

## Audit Procedures

### Weekly
- [ ] Review WAF blocked logs for false positives
- [ ] Check rate limit violation patterns
- [ ] Verify Sentry has no unusual error spikes
- [ ] Review failed authentication logs

### Monthly
- [ ] Rotate AI provider API keys
- [ ] Review and update WAF rule sets
- [ ] Run dependency vulnerability scan (`composer audit`, `pip audit`)
- [ ] Review CORS allowed origins
- [ ] Check certificate expiry dates

### Quarterly
- [ ] Full security audit (OWASP Top 10)
- [ ] Penetration test on staging environment
- [ ] Review user data access logs
- [ ] Update CSP directives as needed
- [ ] Rotate internal service signing keys

### Incident Response
1. Identify: Monitor Sentry + Prometheus alerts
2. Contain: Revoke compromised keys via `APIKeyManager.revoke_key()`
3. Eradicate: Rotate all secrets, update firewall rules
4. Recover: Restore from backup, verify integrity
5. Post-mortem: Document in `docs/incidents/`

## Tools

```bash
# Scan PHP dependencies
cd backend && composer audit

# Scan Python dependencies
cd ai-gateway && pip-audit

# Test CSP headers
curl -sI https://corex.dev | grep -i content-security-policy

# Test HSTS
curl -sI https://corex.dev | grep -i strict-transport

# Check for exposed .env
curl -s -o /dev/null -w "%{http_code}" https://corex.dev/.env

# Verify TLS configuration
nmap --script ssl-enum-ciphers -p 443 corex.dev

# Run OWASP ZAP scan (requires ZAP)
zap-cli quick-scan --self-contained https://staging.corex.dev

# Check rate limiting
for i in $(seq 1 15); do curl -s -o /dev/null -w "%{http_code}\n" https://api.corex.dev/health; done
```

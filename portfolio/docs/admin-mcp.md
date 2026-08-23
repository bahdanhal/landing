# Administrative MCP access

The same HTTPS MCP endpoint at `https://bahdanhal.pl/mcp` exposes public tools and a small set of administrative tools. Administrative calls fail closed unless the request contains a valid Bearer token.

## Configuration

Generate a dedicated, high-entropy token and set it only in `production.env`:

```dotenv
MARKET_ADMIN_TOKEN=replace-with-at-least-32-random-bytes
```

Configure the MCP client to send the token as an HTTP header:

```text
Authorization: Bearer <MARKET_ADMIN_TOKEN>
```

Never pass the token as a tool argument, place it in a prompt, commit it to the repository, or include it in logs. Use the endpoint only over HTTPS. Rotating `MARKET_ADMIN_TOKEN` immediately invalidates the previous credential after the application container is restarted.

## Read-only administrative tools

- `get_admin_dashboard_statistics`: privacy-preserving traffic for seven and thirty days, submission totals, SEO audit outcomes, popular lead sources and requests, active price-tip counts, observation coverage, missing histories, and products not reviewed in thirty days.
- `list_admin_contact_leads`: recent consultation requests with email, phone, message, source, and timestamp.
- `list_admin_product_requests`: recent products requested for the price index.
- `list_admin_price_tips`: active normalized listing links awaiting manual review, including their automatic expiry dates.
- `list_admin_recent_audits`: recent SEO audit runs with sanitized targets, completion status, score, pages crawled, cache state, and duration.

The four list tools accept an optional `limit` from 1 to 100. They never return stored IP hashes.

`update_polish_used_price_observation` remains the only mutating administrative MCP tool. It uses the same Bearer authorization and writes only aggregate manually reviewed observations.

## Privacy and operations

Administrative output can contain personal data and private review material. Do not forward it to unrelated services, paste it into third-party prompts, or enable verbose request/response logging in the MCP client. Community listing links must never be fetched automatically or republished.

Traffic analytics are collected without cookies. PostgreSQL stores only the request path, a source category, an external referring hostname when present, and an HMAC-SHA256 client-IP hash. It never stores raw IP addresses, query strings, full referrer URLs, or user-agent strings. Bot-like user agents, admin and MCP paths, health checks, and requests carrying DNT or Global Privacy Control are excluded. Page-view rows are retained for at most `ANALYTICS_RETENTION_DAYS` (90 by default).

If the token may have been exposed, replace it in `production.env`, restart the application container, and remove any affected client logs.

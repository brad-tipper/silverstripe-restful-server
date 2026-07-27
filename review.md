# silverstripe-restful-server Review — Outstanding Items

Reviewed 28 July 2026. All critical and high-severity items have been fixed.
Remaining items are low-priority or informational.

---

## Open Items

### 1. `normaliseType()` typo: `'dbdatet'` should be `'dbdatetime'`

**Severity:** low (harmless)  
**Description:** `RestfulDataObject.php` line 143 maps `'dbdatet' =>
'datetime'` — missing an `i`. In practice, `str_starts_with('dbdatetime',
'dbdatet')` is true, so `DBDatetime` fields still map correctly.

**Recommended fix:** Change to `'dbdatetime' => 'datetime'` and reorder before
`'date' => 'date'` to prevent partial-match ambiguity.

Decision: approved - though noting we have users in multiple timezones.

---

## Resolved

- **Refresh token cookie-only delivery (high)** — Added `refreshToken` to JSON response body in `issueTokens()`.
- **Action resolution case-sensitivity (medium)** — SilverStripe normalizes URL params to lowercase; not a real risk.
- **N+1 count query (low)** — Acceptable at current data volumes.
- **RequestHandler route-param mutation (low)** — Acceptable workaround for SilverStripe's routing constraints.
- **Cookie order in refresh (low)** — Cookie writes are essentially synchronous; reorder not needed.
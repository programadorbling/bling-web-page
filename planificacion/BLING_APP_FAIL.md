# BLING_APP Guard — Problem & Fix

## What went wrong in `api/config.php`

The generated `config.php` placed `define('BLING_APP', true)` on the very first line,
immediately followed by the guard:

```php
define('BLING_APP', true);   // ← constant is set here

if (!defined('BLING_APP')) { // ← always false; dead code
    http_response_code(403);
    exit('Forbidden');
}
```

Because the constant is defined before the check, the `if` block can never be entered.
A direct browser request to `api/config.php` bypasses the guard entirely.

---

## How the pattern is supposed to work

The guard only fires when the constant is **not already defined** at the moment
`config.php` is loaded. That means:

- `config.php` must contain **only the guard** — no `define` inside it.
- Every PHP file that legitimately needs config must define the constant
  **before** calling `require_once`.

### Correct `api/config.php` (guard only, no define)

```php
<?php
if (!defined('BLING_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

// ... rest of constants ...
```

### Correct `api/submit-membership.php` (define before require)

```php
<?php
define('BLING_APP', true);
require_once __DIR__ . '/config.php';

// ... rest of endpoint logic ...
```

### Result

| Who loads config.php | BLING_APP defined? | Guard fires? |
|---|---|---|
| Browser hits it directly | No | Yes → 403 |
| `submit-membership.php` includes it | Yes (set on line 1) | No → continues |

---

## Action required when creating `submit-membership.php`

1. Open `api/config.php` and **delete** the `define('BLING_APP', true)` line.
2. In `submit-membership.php`, add `define('BLING_APP', true)` as the very first
   statement, before `require_once __DIR__ . '/config.php'`.
3. Apply the same pattern to any future PHP file that includes `config.php`.

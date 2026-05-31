# Formhammer — Working Rules

## Constraints (never break these)
- No external PHP dependencies. No Composer. No npm. No build step.
- No stored form submissions. Ever. The plugin is a filter, not a mailbox.
- No jQuery. Vanilla JS only.
- No hardcoded secret keys. Always read from WP-Options.
- Never touch unrelated WordPress files or options.
- No new DB table without asking first (logger is the only exception, opt-in).
- Elementor integration only loads when ElementorPro\Plugin class exists.
- Prefix everything: functions formhammer_*, classes Formhammer_*, options formhammer_*

## Process
- Prefer 3–5 small focused prompts over one large prompt.
- Never rewrite a file without showing the diff first.
- After each slice: list changed files, passing tests, open issues, next 3 todos.
- If a bug appears twice: fix it AND add a rule here.
- One slice at a time. Do not start Slice N+1 before Slice N has passing tests.

## Code Style
- PSR-4 class naming, snake_case functions
- No magic numbers — all thresholds as named constants or WP-Options
- Comments only for non-obvious logic
- Error messages user-facing: generic. Logs: verbose.
- Never echo directly — use wp_die() or return WP_Error

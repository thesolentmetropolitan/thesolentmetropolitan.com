# Contact form improvements — /about/contact

Date: 2026-08-30 (evening session)

## 1. Form styling

**New** `web/themes/custom/customsolent/css/webform.css`, registered in
`customsolent.libraries.yml`. Scoped to `.paragraph--type--webform` so any future
webform placed via the webform paragraph inherits it.

- Desktop (≥800px): each `.form-item` is a two-column grid
  (`--webform-label-col: 8em` | field). Labels right-justified against the common
  vertical line; verified all four fields **and** the submit button start at the same
  x-coordinate. Textarea label top-aligned with its field.
- Inputs match the search box: 2px solent-blue border, Atkinson Hyperlegible Next,
  1rem, white background; pink `:focus-visible` ring (site convention).
- Send message button styled like `.slnt-cta` (font, weight, padding, radius,
  transition, hover opacity, pink focus ring). Its background/text colours still come
  from the paragraph's colour fields (inline `<style>` in `paragraph--webform.html.twig`).
- Mobile (≤799px): labels above full-width fields, CTA sized as mobile CTAs.

## 2. /contact → /about/contact redirect

**New** `scripts/create-contact-redirect.php` (idempotent, modelled on
`create-collection-redirects.php`). It deletes the duplicate `/contact` alias on
node 89 and creates a 301 redirect `contact → internal:/node/89` (redirect module
resolves this to the canonical alias, so it keeps working if the alias ever changes).

Run locally: verified `curl -I /contact` → `301 Location: /about/contact`.
**Must be run on production** (aliases/redirects are content):
`drush php:script scripts/create-contact-redirect.php`

## 3. Email misconfiguration — found and fixed

`webform.webform.contact.yml`, handler `email_notification` had:

- `from_mail: '[webform_submission:values:email:raw]'` — the **visitor's address as
  From**. Sending "From: gmail.com user" through Zoho SMTP fails SPF/DMARC; providers
  rewrite or spam-flag it. This is almost certainly why the received mail "looks like
  something isn't set up properly".
- `reply_to: ''` — combined with the above, PHPMailer on prod threw
  `Invalid address: (Reply-To)` (watchdog wid 281318/281319, in the prod DB copy,
  30 Aug 15:34) — some submissions **fail to send and even fail to save**.
- `body: '[webform_submission:values:message:value]'` — bare message only: no name,
  no email, no subject context in the mail body.

Fixed (exported config, imported and verified locally via Mailpit with SMTP
temporarily bypassed):

- `from_mail: _default` → site mail (authenticated sender, DMARC-clean)
- `from_name` stays the visitor's name → "Jane Doe <site-mail>"
- `reply_to: '[webform_submission:values:email:raw]'` → **Reply** in the mail client
  goes to the visitor
- `body: _default` → full submission (submitted on/by + all values, line breaks kept)

The disabled `email_confirmation` handler was left untouched.

### Related fragility (not changed — needs Rob's decision)

`system.site.yml` has `mail: ''` in exported config (deliberate: keep the mailbox out
of the repo). But that means **every `drush cim` on prod resets the site mail to
empty**, and both `to_mail: _default` and the new `from_mail: _default` resolve to
`[site:mail]`. Recommendation: set the address in prod's `settings.local.php`
(gitignored, never in the repo):
`$config['system.site']['mail'] = 'the-real-address';`
— survives every deploy, stays out of the codebase.

Also noted: `system.mail interface.default: SMTPMailSystem` is used even when
`smtp_on` is false (local sends try Zoho and time out after 30s; watchdog logs the
failure). Harmless locally, matches the "no mail from dev" intent.

## 4. Anti-spam recommendations (submissions 162–172 are mostly bot spam)

Free, no-cookie, no-user-friction — in recommended order:

1. **Honeypot** (`drupal/honeypot`): invisible trap field + minimum time-to-submit.
   Catches the bulk of dumb bots. Zero user impact, no cookies.
2. **Antibot** (`drupal/antibot`): requires JS + human interaction before the form
   action is revealed. Complements Honeypot (different bot class). No cookies.
3. If spam persists: a privacy-friendly CAPTCHA — **ALTCHA** (self-hosted
   proof-of-work, free, GDPR-friendly, no cookies) or Friendly Captcha (free tier).
   **Avoid Google reCAPTCHA**: sets cookies/tracking → would need Klaro consent
   wiring and contradicts the site's privacy stance.
4. Webform-level extras already available: per-form
   "limit total submissions per user/IP" and the spam words module
   (`webform_spam_words`) if targeted patterns continue.

## 5. Cookies / Klaro

No Klaro changes needed. The webform sets no cookies for anonymous browsing;
submitting may start a Drupal session (SESS…) for the confirmation message — strictly
necessary, already covered by the Klaro "Functional" service (`^[SESS|SSESS]` regex).
Honeypot/Antibot set no cookies. reCAPTCHA would (another reason to avoid it).

## Files

- A `web/themes/custom/customsolent/css/webform.css`
- M `web/themes/custom/customsolent/customsolent.libraries.yml`
- M `config/sync/webform.webform.contact.yml`
- A `scripts/create-contact-redirect.php`
- A this log

## Production release steps

1. Deploy code + `drush cim` (deploy.sh) — picks up the handler fix.
2. `drush php:script scripts/create-contact-redirect.php`
3. Recommended: add `$config['system.site']['mail']` to prod `settings.local.php`.
4. Decide on Honeypot/Antibot install (separate task).

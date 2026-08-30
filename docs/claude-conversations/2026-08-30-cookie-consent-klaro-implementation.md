# Cookie consent (Klaro) — theme implementation log

Date: 2026-08-30 · Issue #141 · Brief: `2026-08-30-cookie-consent-klaro-brief.md`

Rob installed/enabled `drupal/klaro` 3.1.1, configured it in admin and exported config
(commit feeae54). Claude Code did steps 5–6 of the brief: site-specific CSS and library
registration, verified live in ddev with Playwright.

## What was done

- **New** `web/themes/custom/customsolent/css/klaro-overrides.css`
- **Modified** `customsolent.libraries.yml` — registered the file (last in the component list)
- **New** this log

## Verified markup (Klaro module 3.1.1)

The brief's selectors were guesses; actual rendered markup differs:

| Brief assumed | Actual |
|---|---|
| `.cn-body .title` | `h2#id-cookie-title` |
| `.cm-btn-accept-all` | `button.cm-btn.cm-btn-success` |
| `.cm-btn-decline` | `button.cm-btn.cm-btn-danger.cn-decline` |
| — | wrapper carries `.klaro.klaro-theme-customsolent` |

The consent modal is `.cookie-modal > .cm-modal.cm-klaro > .cm-header / .cm-body / .cm-footer`
with `.cm-list-input:checked + .cm-list-label .slider` toggles.

## Approach — variables, not `!important`

Load order is `klaro.min.css` (library) → `klaro-extend.css` + `klaro-override.css`
(module, enabled by "Adjust the UI to Drupal themes") → `klaro-overrides.css` (theme).
The library's `light` theme is applied as inline CSS custom properties on `#klaro`, and both
the library and module override sheet are driven by custom properties (`--notice-*`,
`--klaro-button-*`, `--klaro-link-*`, `--klaro-slider-*`, `--font-family`, …). So the theme
file re-declares those variables on `.klaro.klaro-theme-customsolent` and scopes button
variants by setting `--klaro-button-*` per button class. No `!important` is needed — the
theme-class selector out-specifies the module sheet and loads after it.

Full-width bottom banner: the library defaults to a 400px bottom-right box at ≥1024px;
`--notice-left/right/bottom: 0`, `--notice-max-width: 100%` and `border-radius: 0` make it
span the viewport, with `.cn-body` constrained to `max-width: 1200px; margin: 0 auto`.

## Verification (Playwright, ddev)

- Desktop 1280px: banner spans full width at the bottom, solent blue `#2c4f6e`, magenta-dark
  3px top border, white title/text, Customise link left, buttons right. Accept (white fill,
  blue text) and Decline (white outline) both 44px tall, same font/size/weight.
- Modal: body-bg background, solent-blue heading, magenta active sliders, blue buttons
  (Decline outlined), pink/magenta focus rings.
- iPhone SE 375×667: banner 237px = **36 %** of viewport (brief cap 40 %). Buttons are
  **side by side, equal width (157×44 each)** rather than stacked — stacked they push the
  banner to 289px / 43 %. The brief's fallback for exceeding 40 % is shortening the text;
  side-by-side keeps Rob's wording. Switching back to stacked is a one-line change
  (`flex-direction: column` on `.cn-buttons` in the mobile block).
- Accept sets `klaro={"cms":true,"klaro":true,…}`; Decline records `false` for optional
  services; banner does not reappear on reload.
- An `<a href="#klaro">` present at page load reopens the modal (module binds
  `[rel*="open-consent-manager"], [href*="#klaro"], .open-consent-manager` via `once`,
  so links injected after load are not bound).
- The 7px horizontal page overflow visible at 1280px is pre-existing (identical with `#klaro`
  removed) — not from the banner.

## For Rob — follow-ups spotted during verification

1. **Vimeo and YouTube services are enabled** (`status: true`) and appear in the modal.
   The brief says only the Functional service for now; disable them at
   `/admin/config/user-interface/klaro/services` until embeds are actually used.
2. **Accept button reads "Accept", not "Accept all"** — the notice uses the `ok` text
   (`klaro.texts.yml: ok: Accept`); `acceptAll` is only used in the modal. Change `ok` to
   "Accept all" at `/admin/config/user-interface/klaro/texts` if you want parity with the brief.
3. **Footer "Cookie settings" link**: the footer nav paragraph renders the *main* menu (the
   `footer` menu is empty), so a menu item would also show in the header. Simplest: add a
   link with URL `#klaro` (text "Cookie settings") to the copyright/open-source paragraph
   text in the footer — it inherits footer link styling and Klaro binds it automatically.
4. Privacy Policy page update (brief step 10) — `/about/privacy-policy` is linked from the
   modal already.

## Follow-up (same day)

- Customise link changed to **white, pink (`--pink-hover`) on hover** per site convention —
  done via the existing `--klaro-link-color` / `--klaro-link-color-hover` overrides on
  `a.cn-learn-more`, no `!important`. Verified computed colours `#fff` → `#f5b0d8`.
- **Important:** after Rob disabled Vimeo/YouTube (commit 185c79b) the banner stopped
  rendering entirely. Cause: `KlaroHelper::consentManagementRequired()` only returns TRUE if
  at least one *enabled and non-required* service exists (`getApps(TRUE, TRUE)`). With only
  Functional + Consent manager (both required) enabled, the module attaches no JS/CSS/markup
  at all — so no banner, no modal, and footer `#klaro` links do nothing. This is the module's
  intent: strictly-necessary-only sites don't legally need a consent prompt.
  Options: (a) accept no banner until Matomo/embeds arrive (defensible under PECR/DUA);
  (b) re-enable one opt-in service such as YouTube (default off, loads nothing unless an
  embed exists) to keep the notice visible now, as the brief intends.
  Verification of the colour change was done with YouTube temporarily enabled in the DB, then
  restored with `drush cim`.

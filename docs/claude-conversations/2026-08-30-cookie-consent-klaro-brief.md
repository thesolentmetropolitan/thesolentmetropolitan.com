# The Solent Metropolitan — Cookie Consent (Klaro) Brief

## Overview

Install and configure the Klaro Cookie & Consent Management module to comply with UK/EU cookie regulations. The site is publicly available and needs a consent mechanism even during its prototype phase.

**Use the free/open-source version only — no paid Klaro subscription.**

**Drupal 11 compatible.**

---

## What cookies does the site currently set?

### Drupal core — anonymous visitors
A plain Drupal 11 site sets **no cookies for anonymous visitors** who don't interact with forms. The session cookie (`SESS...`) is only created when a user logs in or submits a form. This is classified as "strictly necessary" and does not require consent.

### Drupal core — authenticated users
The `SESS...` session cookie is set on login. This is strictly necessary for the site to function for logged-in users. No consent required.

### Klaro's own cookie
Klaro stores consent decisions in a cookie named `klaro` (configurable). This is strictly necessary for the consent mechanism itself and is exempt from consent requirements.

### Current third-party services
**None at present.** The site does not currently use Google Analytics, Matomo, YouTube embeds, SoundCloud embeds, social media tracking, or any other third-party service that sets cookies.

### Planned future services (not yet active)

| Service | Cookie type | Consent required | When planned |
|---------|-----------|-----------------|-------------|
| **Matomo analytics** (self-hosted) | Analytics — first-party cookies (`_pk_*`) | See note below | Later |
| **YouTube embeds** | Third-party tracking cookies | Yes | Later |
| **SoundCloud embeds** | Third-party tracking cookies | Yes | Later |
| **Authenticated user sessions** (contributor logins) | Session cookie (strictly necessary) | No | Later |

**Note on Matomo:** Under the UK's Data (Use and Access) Act 2025 (in force since February 2026), self-hosted Matomo configured for data minimisation (IP anonymisation, no cross-site tracking, first-party only, statistical purposes only) may qualify for the UK's statistical purposes exception — no consent required. However, this exception does not apply across all EU jurisdictions. If the site serves EU visitors, consent for Matomo analytics cookies should still be requested. Alternatively, Matomo can be configured in cookieless mode (no cookies at all), eliminating the consent question entirely. **This decision is deferred to when Matomo is implemented.**

---

## Klaro services to configure

### For now (MVP)

Only one "service" needs configuring:

**Drupal functional cookies**
- Purpose: Strictly necessary / Functional
- Description: "These cookies are essential for the website to function. They enable core features like page navigation, form submissions, and user login. No personal data is collected."
- Required: Yes (cannot be declined — strictly necessary)
- Default: Enabled
- Cookies: `SESS*`, `Drupal.visitor`, `klaro`

### For later (when services are added)

**Analytics (Matomo)**
- Purpose: Analytics / Statistics
- Description: "We use privacy-friendly analytics to understand how people use the site, so we can improve it. Your data stays on our server and is never shared with third parties."
- Required: No
- Default: Disabled (opt-in)
- Cookies: `_pk_ref`, `_pk_cvar`, `_pk_id`, `_pk_ses`, `mtm_consent`

**Embedded media (YouTube)**
- Purpose: External media
- Description: "We embed videos from YouTube. YouTube may set cookies on your device when you play a video."
- Required: No
- Default: Disabled (opt-in)
- Contextual blocking: Klaro replaces YouTube iframes with a placeholder until consent is given

**Embedded media (SoundCloud)**
- Purpose: External media
- Description: "We embed audio from SoundCloud. SoundCloud may set cookies on your device when you play audio."
- Required: No
- Default: Disabled (opt-in)
- Contextual blocking: Same approach as YouTube

---

## Klaro configuration

### Installation

```bash
composer require drupal/klaro
drush en klaro
drush cr
```

### Admin configuration

Navigate to: `/admin/config/user-interface/klaro`

**General settings:**
- Dialog type: **Notice** (banner at the bottom of the page — not a modal that blocks the page)
- Accept all: **Enabled** — adds an "Accept all" button
- Decline all: **Enabled** — adds a "Decline all" button alongside accept. This is required by UK/EU regulation — the reject option must be as easy as the accept option.
- Link to open consent dialog: **Enabled** — adds a persistent link (in the footer) for users to revisit their choices
- Display link as button: **No** — a text link in the footer is sufficient
- Show button to toggle consent modal: **No** (the footer link serves this purpose)

**Texts:**
- Notice title: "Cookies on The Solent Metropolitan"
- Notice description: "We use cookies to make this site work. In the future, we may use privacy-friendly analytics and embedded media that set additional cookies. You can manage your preferences here."
- Accept button text: "Accept all"
- Decline button text: "Decline all"
- Privacy policy URL: `/about/privacy-policy`

**Permissions:**
- Configure which user roles see the consent dialog. Set to: **Anonymous user** and **Authenticated user** (all visitors). Exclude the administrator role if preferred (admins have implicitly consented by managing the site).

### Styling configuration

**Override Klaro CSS variables:** In the Klaro settings under the Advanced/Styling tab, use the "Override Klaro CSS variables" field. Enter `light` to use Klaro's light theme as the base (better fit for the site's warm, light colour scheme than the default dark theme).

Then add custom CSS in the theme to align with the site's design system.

---

## Appearance and theming

### Position
**Bottom of the viewport** — a horizontal banner that spans the full width, sitting above the footer. This is the standard position for cookie notices and doesn't obscure the main content. On mobile, the banner takes the full width and stacks the text and buttons vertically.

### Sizing
- **Desktop:** Full width, fixed to the bottom of the viewport. Content area constrained to the site's max-width (1200px), centred. Height determined by content — typically 80–120px.
- **Mobile (including iPhone SE3):** Full width, buttons stack below the text. The banner should not exceed 40% of the viewport height — if it does, the text should be shortened.

### Visual design

The banner should feel like part of the site, not a generic overlay. Use the site's colour system:

```css
/* ══════════════════════════════════════
   Klaro cookie consent — site-specific overrides
   ══════════════════════════════════════ */

/* ── Notice banner ── */
.klaro .cookie-notice {
  background: var(--solent-blue, #2c4f6e) !important;
  color: #ffffff !important;
  font-family: 'Atkinson Hyperlegible Next', sans-serif !important;
  border-top: 3px solid var(--magenta-dark, #a30065) !important;
  box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15) !important;
  padding: 1.2rem 2rem !important;
}

/* ── Notice text ── */
.klaro .cookie-notice .cn-body p {
  font-size: 0.88rem !important;
  line-height: 1.5 !important;
  color: #ffffff !important;
  margin-bottom: 0.5rem !important;
}

/* ── Notice title ── */
.klaro .cookie-notice .cn-body .title {
  font-size: 1.1rem !important;
  font-weight: 700 !important;
  color: #ffffff !important;
  margin-bottom: 0.4rem !important;
}

/* ── Accept button — primary CTA ── */
.klaro .cookie-notice .cm-btn.cm-btn-accept-all {
  background: #ffffff !important;
  color: var(--solent-blue, #2c4f6e) !important;
  border: 2px solid #ffffff !important;
  border-radius: 4px !important;
  font-family: 'Atkinson Hyperlegible Next', sans-serif !important;
  font-weight: 700 !important;
  font-size: 0.85rem !important;
  padding: 0.5em 1.2em !important;
  cursor: pointer !important;
  transition: all 0.2s !important;
}

.klaro .cookie-notice .cm-btn.cm-btn-accept-all:hover {
  background: var(--pink-hover, #f5b0d8) !important;
  border-color: var(--pink-hover, #f5b0d8) !important;
  color: var(--solent-blue, #2c4f6e) !important;
}

/* ── Decline button — equal prominence ── */
.klaro .cookie-notice .cm-btn.cm-btn-decline {
  background: transparent !important;
  color: #ffffff !important;
  border: 2px solid rgba(255, 255, 255, 0.6) !important;
  border-radius: 4px !important;
  font-family: 'Atkinson Hyperlegible Next', sans-serif !important;
  font-weight: 700 !important;
  font-size: 0.85rem !important;
  padding: 0.5em 1.2em !important;
  cursor: pointer !important;
  transition: all 0.2s !important;
}

.klaro .cookie-notice .cm-btn.cm-btn-decline:hover {
  background: rgba(255, 255, 255, 0.15) !important;
  border-color: #ffffff !important;
}

/* ── Manage preferences link ── */
.klaro .cookie-notice .cn-learn-more {
  color: var(--pink-hover, #f5b0d8) !important;
  text-decoration: underline !important;
  font-size: 0.82rem !important;
}

.klaro .cookie-notice .cn-learn-more:hover {
  color: #ffffff !important;
}

/* ── Modal (consent detail view) ── */
.klaro .cookie-modal .cm-modal {
  background: var(--body-bg, #faf9f7) !important;
  color: var(--text, #1a1a1a) !important;
  font-family: 'Atkinson Hyperlegible Next', sans-serif !important;
  border-radius: 8px !important;
  box-shadow: 0 8px 40px rgba(0, 0, 0, 0.2) !important;
}

.klaro .cookie-modal .cm-modal .cm-header h1 {
  color: var(--solent-blue, #2c4f6e) !important;
  font-family: 'Atkinson Hyperlegible Next', sans-serif !important;
  font-weight: 700 !important;
}

.klaro .cookie-modal .cm-modal .cm-body {
  color: var(--text, #1a1a1a) !important;
}

/* ── Toggle switches in modal — use magenta for active ── */
.klaro .cookie-modal .cm-list-input:checked + .cm-list-label .slider {
  background: var(--magenta, #c5007a) !important;
}

/* ── Mobile adjustments ── */
@media (max-width: 799px) {
  .klaro .cookie-notice {
    padding: 1rem 1.2rem !important;
  }

  .klaro .cookie-notice .cn-body p {
    font-size: 0.82rem !important;
  }

  .klaro .cookie-notice .cn-buttons {
    flex-direction: column !important;
    gap: 0.5rem !important;
  }

  .klaro .cookie-notice .cm-btn {
    width: 100% !important;
    text-align: center !important;
  }
}
```

**Notes on CSS:**
- The `!important` declarations are needed to override Klaro's built-in CSS specificity. This is standard practice for theming Klaro.
- The solent blue banner with white text and magenta accent creates a cohesive feel with the site's navigation bar.
- The accept and decline buttons have **equal visual weight** — the accept button is white (filled), the decline is white-bordered (outlined). Both are the same size, same font weight, side by side. This avoids dark patterns where the accept button is prominent and the decline is hidden.
- The magenta top border on the banner provides a visual signature that ties it to the site's design.
- Claude Code should inspect Klaro's actual rendered HTML class names to verify the selectors above match. Klaro's markup may vary between versions.

### Contrast verification

| Element | Foreground | Background | Ratio | Passes |
|---------|-----------|------------|-------|--------|
| Banner text | White #fff | Solent blue #2c4f6e | 8.1:1 | AAA ✓ |
| Accept button | Solent blue #2c4f6e | White #fff | 8.1:1 | AAA ✓ |
| Decline button text | White #fff | Solent blue #2c4f6e | 8.1:1 | AAA ✓ |
| Manage link | Pink #f5b0d8 | Solent blue #2c4f6e | 5.0:1 | AA ✓ |

---

## Footer link for revisiting consent

Klaro provides a toggle button/link that users can click to reopen the consent dialog after they've made their initial choice. Configure this to appear in the site footer.

Position it near the Privacy Policy and Terms of Use links in the footer — e.g. "Cookie settings" as a text link. Klaro handles the functionality; the styling should match the existing footer link style.

---

## Future-proofing

The Klaro configuration is designed to grow as the site adds services:

1. **Matomo analytics** — add a new service in Klaro admin when Matomo is installed. Klaro blocks the Matomo tracking script until consent is given (unless cookieless mode is used).
2. **YouTube/SoundCloud embeds** — Klaro's contextual blocking replaces iframes with a consent placeholder. The visitor sees "Click to load this YouTube video — this will set cookies from YouTube" and can choose to load it. Configure by adding the service and marking the relevant `<iframe>` elements with Klaro's `data-type` attributes.
3. **Authenticated user features** — session cookies for logged-in contributors are strictly necessary and don't need consent configuration. No Klaro changes needed.

As each service is added, update the Klaro services configuration at `/admin/config/user-interface/klaro/services` and update the site's Privacy Policy page.

---

## Checklist: What Rob should configure

### Installation
- [ ] `composer require drupal/klaro`
- [ ] `drush en klaro && drush cr`

### Klaro settings (`/admin/config/user-interface/klaro`)
- [ ] Dialog type: Notice (bottom banner)
- [ ] Accept all: Enabled
- [ ] Decline all: Enabled
- [ ] Link to open consent dialog: Enabled
- [ ] Styling override: `light`
- [ ] Privacy policy URL: `/about/privacy-policy`
- [ ] Notice title and description text as specified above

### Services (`/admin/config/user-interface/klaro/services`)
- [ ] Add "Drupal functional cookies" service — required, enabled by default

### Permissions
- [ ] Set Klaro dialog visibility for Anonymous user and Authenticated user roles

### Privacy Policy page
- [ ] Update `/about/privacy-policy` to mention cookies, what they're used for, and that visitors can manage preferences via the cookie settings link

---

## Implementation order

| Step | Task | Who |
|------|------|-----|
| 1 | Install Klaro module | Rob or Claude Code |
| 2 | Configure Klaro settings (dialog type, buttons, texts) | Rob |
| 3 | Add the "Drupal functional cookies" service | Rob |
| 4 | Set permissions for which roles see the dialog | Rob |
| 5 | Add custom CSS for site-specific styling | Claude Code |
| 6 | Add CSS to theme library | Claude Code |
| 7 | Verify banner appearance on desktop and mobile | Rob |
| 8 | Verify accept/decline buttons have equal prominence | Rob |
| 9 | Verify footer "Cookie settings" link reopens the dialog | Rob |
| 10 | Update Privacy Policy page | Rob |

---

## Testing

1. **Banner appears:** Visit the site as an anonymous user (clear cookies first). The cookie consent banner appears at the bottom of the viewport.
2. **Banner styling:** Solent blue background, white text, magenta top border. Matches the site's design language.
3. **Accept/decline parity:** Both buttons are the same size and equally accessible. No dark patterns — decline is not hidden, smaller, or less prominent.
4. **Accept all:** Click "Accept all." Banner disappears. The `klaro` cookie is set storing the consent.
5. **Decline all:** Click "Decline all." Banner disappears. The `klaro` cookie records the refusal.
6. **Manage preferences:** Click the "Manage preferences" link on the banner. The consent modal opens showing the service list with toggle switches.
7. **Footer link:** After dismissing the banner, scroll to the footer. A "Cookie settings" link is visible. Clicking it reopens the consent dialog.
8. **Mobile (iPhone SE3):** The banner doesn't exceed 40% of the viewport. Buttons stack vertically. Text is readable.
9. **Mobile general:** The banner is usable on various mobile widths. Buttons are tappable (min 44px height).
10. **No interference with content:** The banner sits at the bottom and doesn't prevent interaction with the page content above it. Scrolling works normally.
11. **Repeat visit:** Visit the site again (same browser). The banner does NOT reappear — Klaro remembers the consent decision via its cookie.
12. **Privacy Policy link:** The banner includes a link to the Privacy Policy page. The link works.
13. **Strictly necessary service:** The "Drupal functional cookies" service is marked as required and cannot be toggled off in the manage preferences modal.

---

## Files to create or modify

```
M  web/themes/custom/customsolent/css/klaro-overrides.css (new file)
     — site-specific Klaro styling overrides

M  web/themes/custom/customsolent/customsolent.libraries.yml
     — register klaro-overrides.css

M  (Privacy Policy node content — manual update by Rob)
```

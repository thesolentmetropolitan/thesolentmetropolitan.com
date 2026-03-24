# The Solent Metropolitan — Playwright MCP Setup Brief

## Overview

Set up the Playwright MCP server with Claude Code so that Claude Code can drive a real browser window. This enables visual debugging, testing, and inspection of the live site — particularly useful for issues that only appear when logged in (e.g. the navigation menu conflict with Drupal's Gin admin theme).

---

## Prerequisites

- **Node.js 18+** — check with `node --version`. Playwright MCP won't work on older versions.
- **Claude Code** installed and working on the project.

---

## Step 1: Register the Playwright MCP server

Run this from within your project directory:

```bash
claude mcp add playwright npx @playwright/mcp@latest
```

This registers the Playwright MCP server in your Claude Code configuration. It persists in `~/.claude.json` and only affects the directory you run it in.

To make it available across all your projects instead:

```bash
claude mcp add --scope user playwright npx @playwright/mcp@latest
```

Or to share it with collaborators via version control:

```bash
claude mcp add --scope project playwright npx @playwright/mcp@latest
```

## Step 2: Install browser binaries

If you haven't used Playwright before, install the browser binaries:

```bash
npx playwright install
```

On Linux, you may also need system dependencies:

```bash
npx playwright install-deps
```

---

## Step 3: Verify the setup

1. Start Claude Code: `claude`
2. Run `/mcp` to see available tools — Playwright should appear in the list
3. Test with a simple command: "Use playwright mcp to open a browser to https://thesolentmetropolitan.com"

A visible Chrome window should open and navigate to the site.

**Important:** The first time you use it, explicitly say "playwright mcp" in your prompt. Claude Code may otherwise try to run Playwright via bash commands instead of the MCP server.

---

## Step 4: Authenticated debugging workflow

For debugging issues that only appear when logged in (e.g. the navigation menu vs Gin admin theme conflict):

1. Tell Claude Code: "Use playwright mcp to open a browser to https://thesolentmetropolitan.com/user/login"
2. The browser window opens — **you log in manually** with your own credentials
3. Tell Claude Code: "I've logged in. Take a screenshot of the current page."
4. Now Claude Code can see the page with the admin toolbar present
5. Ask Claude Code to interact with and inspect the page:
   - "Take a screenshot of the navigation menu area"
   - "Click on the Culture menu item and take a screenshot"
   - "Get the HTML of the #slnt-header element"
   - "Check what CSS is applied to .main-menu-item-container"
   - "Look at the console for any JavaScript errors"
   - "Check if any elements are overlapping the navigation"

Cookies persist for the session, so you stay logged in throughout.

---

## Step 5: Useful commands for your project

### Visual debugging
- "Open my site and take screenshots at 800px width and 1200px width"
- "Take a screenshot of the footer"
- "Resize the browser to 799px wide and take a screenshot of the mobile menu"

### DOM inspection
- "Get the computed styles for the .sub-menu-container element"
- "List all elements with z-index values in the header area"
- "Check if the Gin admin toolbar has any position:fixed or position:sticky elements"

### JavaScript debugging
- "Open the site logged in and check the browser console for errors"
- "Check what event listeners are attached to the .main_nav_link buttons"

### Testing fixes
After Claude Code makes a code change:
- "Refresh the browser and check if the menu works now"
- "Click through each main menu item and verify the submenus open correctly"

### Responsive testing
- "Test the navigation at these widths: 400px, 799px, 800px, 960px, 1200px — take a screenshot at each"

---

## Notes

- **Visible browser by default.** Playwright MCP opens a real browser window you can see, not a headless one. This is helpful for debugging because you can watch what's happening.
- **Local development.** If you're running a local development server (e.g. via DDEV, Lando, or PHP's built-in server), Claude Code can access localhost URLs too.
- **Performance.** The MCP server runs locally on your machine. Browser interactions happen on your local network — nothing is sent to external servers beyond normal web requests to your site.
- **Session scope.** Each Claude Code session gets its own browser instance. When you end the session, the browser closes and cookies are cleared.
- **The menu/Gin issue specifically.** The most likely cause is z-index conflicts, position:fixed/sticky stacking, or the Gin admin toolbar's JavaScript interfering with your custom menu JS event handlers. With Playwright, Claude Code can inspect all of these in the live page state that only exists when logged in, which is hard to debug from code alone.

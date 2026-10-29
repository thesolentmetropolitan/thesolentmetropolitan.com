# Notes for Claude

- Content scripts, release scripts and working notes (including conversation
  logs) live in a separate **private** repository, not in this one. In a
  checkout it is cloned into `scripts/` (git-ignored here) by
  `tools/run-private-script.sh fetch`; see the README for how to run scripts.
- Commit new scripts, release scripts and conversation logs **inside
  `scripts/`** (the private repo), never in this repo. Keep commit messages
  here free of script names and editorial reasoning.
- Older references to `docs/claude-conversations/…` now mean
  `scripts/docs/claude-conversations/…`.
- If `scripts/` is missing, run `tools/run-private-script.sh fetch` (needs
  access to the private repo).

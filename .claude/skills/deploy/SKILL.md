---
name: deploy
description: Release the r2luna/brain package by tagging a GitHub release so Packagist syncs. Use when the user runs /deploy or asks to deploy/release/publish a new version of the package (to the v3, v4, or current line). Takes an optional argument like a branch/line (3.x, 4.x, main), a version (e.g. 4.1.2), and/or a bump kind (patch, minor, major).
---

# Deploy (release) the package

This project is a Composer library published on **Packagist as `r2luna/brain`**. There is no release CI workflow — "deploying" means creating a **GitHub release with a git tag**; Packagist syncs automatically via webhook within seconds.

## Project release conventions (do not deviate without asking)

- **Parallel major lines, each on its own branch:**
  - `4.x` branch → the **v4** line (latest released line)
  - `3.x` branch → the **v3** line (still maintained)
  - `main` → current development of the latest line
- **Tag name has NO `v` prefix** (e.g. `4.1.2`, `3.9.3`).
- **Release title HAS the `v` prefix** (e.g. `v4.1.2`). Match the existing releases exactly.
- A change can be shipped to **both** the v3 and v4 lines (one release per branch), each with its own version number from that line.
- Packagist package metadata: `https://repo.packagist.org/p2/r2luna/brain.json`.

## Steps

1. **Determine the target line(s).** From the argument or by asking. Common cases: release one line (`4.x`) or the same fix to both (`4.x` + `3.x`). Map line → branch (`v4`→`4.x`, `v3`→`3.x`).

2. **Fetch and inspect state** for each target branch:
   ```bash
   git fetch origin --tags --quiet
   git tag --sort=-v:refname | grep '^<MAJOR>\.' | head -5   # latest tags on that line
   git log <LATEST_TAG>..origin/<BRANCH> --oneline            # what's shipping
   ```
   Confirm there are unreleased commits. If none, tell the user and stop.

3. **Decide the version number.** Bump from the line's latest tag:
   - `patch` → bug fixes / changes that don't add features (default if unsure for a small change)
   - `minor` → new features or notable changes (e.g. dropping a supported dependency version)
   - `major` → would normally be a breaking change, but **new majors get their own branch**, so within an existing line prefer minor/patch and confirm with the user.
   Because the v3 and v4 lines are fixed majors, **never auto-pick a number that collides with the other line** (e.g. v3 cannot become `4.0.0`). When the semver call is ambiguous, ask the user (offer the computed patch/minor numbers).

4. **Pre-flight checks** (report, don't silently skip):
   - Verify the branch tip is what you'll tag: `git rev-parse origin/<BRANCH>`.
   - Optional but recommended: check CI on the branch tip is green
     (`gh run list --commit $(git rev-parse origin/<BRANCH>) --json name,status,conclusion`).
     Note: the test workflow does **not** run on PRs targeting `4.x` (its `on.pull_request.branches` is `main`/`2.x`/`3.x`), so a `4.x` release may have no PR test run — say so rather than blocking.

5. **Confirm before tagging.** Tagging/releasing is outward-facing and hard to reverse (Packagist will pick it up). Show the user: line(s), version(s), target commit(s), and a draft of the notes. Get explicit go-ahead unless they already gave the version(s) in the request.

6. **Write release notes** from the commits since the last tag. Keep them concise and grouped; lead with anything user-impacting (e.g. dropped support, breaking-ish changes) in a callout. End with a compare link:
   `**Full Changelog**: https://github.com/r2luna/brain/compare/<LAST_TAG>...<NEW_TAG>`

7. **Create the release** (this creates the tag at the branch tip):
   ```bash
   gh release create <NEW_TAG> --target <BRANCH> --title "v<NEW_TAG>" --notes "<NOTES>"
   ```
   Repeat per line.

8. **Verify:**
   ```bash
   git fetch origin --tags --quiet
   git rev-parse <NEW_TAG>            # must equal origin/<BRANCH> tip
   curl -s "https://repo.packagist.org/p2/r2luna/brain.json" \
     | python3 -c 'import sys,json;v=[x["version"] for x in json.load(sys.stdin)["packages"]["r2luna/brain"]];print(v[:8])'
   ```
   Confirm each new version appears on Packagist and the tag points at the expected commit. Report the release URLs.

## Notes / gotchas

- Use the `gh` CLI for all GitHub operations (releases, runs).
- Do not push tags by hand with `git tag`/`git push --tags` — use `gh release create` so the GitHub release and tag are created together and consistently.
- Follow the repo/global commit & PR rules (no AI attribution).
- If asked to "deploy the last fix to v4 and v3", that means: one release on `4.x` and one on `3.x`, each versioned from its own line.

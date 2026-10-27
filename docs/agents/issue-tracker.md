# Issue tracker: GitHub

Issues and specs live in GitHub Issues in `dineshinau/bulk-cogs-editor-for-woocommerce`.
Use the connected GitHub tools when they support the operation; otherwise use an installed, authenticated `gh` CLI.
Read operations are available during investigation. Publish, comment, assign, close, or change labels only when the user's task or an explicitly invoked skill authorizes the action.

## Conventions

- Create an issue: `gh issue create --repo dineshinau/bulk-cogs-editor-for-woocommerce --title "..." --body-file <file>`.
- Read an issue: `gh issue view <number> --repo dineshinau/bulk-cogs-editor-for-woocommerce --json number,title,body,labels,comments`.
- List issues: `gh issue list --repo dineshinau/bulk-cogs-editor-for-woocommerce --state open --json number,title,body,labels`. Add label filters as needed.
- Comment: `gh issue comment <number> --repo dineshinau/bulk-cogs-editor-for-woocommerce --body-file <file>`.
- Apply or remove labels: `gh issue edit <number> --repo dineshinau/bulk-cogs-editor-for-woocommerce --add-label "..."` or `--remove-label "..."`.
- Close: `gh issue close <number> --repo dineshinau/bulk-cogs-editor-for-woocommerce`.

Write multiline issue bodies and comments to a temporary UTF-8 file and pass it with `--body-file`.
When a skill says "publish to the issue tracker", create a GitHub issue within the authorized scope.
When it says "fetch the relevant ticket", read the GitHub issue.

## Pull requests as a triage surface

**PRs as a request surface: no.**

GitHub issues and pull requests share a number space. Resolve the resource type before acting on a bare number.

## Wayfinding operations

- Store the map in one issue labelled `wayfinder:map`, with Notes, Decisions-so-far, and Fog sections.
- Link child tickets as native GitHub sub-issues when supported. Otherwise, add a task list to the map and `Part of #<map>` to each child body.
- Use `wayfinder:research`, `wayfinder:prototype`, `wayfinder:grilling`, or `wayfinder:task` for child ticket types.
- Record blockers with native GitHub issue dependencies when supported. Otherwise, put `Blocked by: #<number>` at the top of the child body. A ticket is unblocked when every blocker is closed.
- Select the first open, unassigned child in map order with no open blockers. Assign the ticket to the driving developer when claiming it.
- On resolution, record the answer on the ticket, close it, and add a short finding and link to the map's Decisions-so-far section.

Check that required labels exist before applying them. Setup records the vocabulary; remote label availability remains unverified because `gh` is unavailable and the connected tools do not expose label management.

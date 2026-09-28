---
description: Run WordPress-specific linting using @wordpress/scripts and PHPStan.
---

# WordPress Lint Workflow

This workflow ensures your code follows WordPress Coding Standards (WPCS) and passes static analysis.

1. **Check for Node dependencies**
   - Check if `node_modules` and `@wordpress/scripts` are present.
   - If not, advise running `npm install`.

2. **Run JS/Block Linting**
// turbo
   - Run `npm run lint:js` or `npx wp-scripts lint-js`.

3. **Run CSS Linting**
// turbo
   - Run `npm run lint:css` or `npx wp-scripts lint-style`.

4. **Run PHP Static Analysis**
// turbo
   - Use `wp-phpstan` skill if configured.
   - Run `vendor/bin/phpstan analyze`.

5. **Report Results**
   - Summarize findings and provide fix recommendations.

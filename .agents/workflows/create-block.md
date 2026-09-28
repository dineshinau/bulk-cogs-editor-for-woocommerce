---
description: Scaffold a new WordPress Gutenberg block using @wordpress/create-block.
---

# Create WordPress Block Workflow

This workflow guides you through the process of scaffolding a high-quality, modern Gutenberg block.

1. **Prerequisites**
   - Ensure Node.js and npm are installed.
   - Verify if the project already uses `@wordpress/scripts`.

2. **Scaffold the Block**
// turbo
   - Run `npx @wordpress/create-block@latest` with the desired name and namespace.
   - Use the `--interactive` flag if custom interactivity is needed.

3. **Configure block.json**
   - Update `apiVersion` to `3`.
   - Set up `attributes`, `supports`, and `category`.

4. **Initialize Build**
// turbo
   - Run `npm run start` to begin the development build.

5. **Verify Registration**
   - Check if the block is registered correctly in PHP using `register_block_type_from_metadata`.

Here is a clean, comprehensive **Developer Workflow** section optimized for your project's `README.md`. It clearly explains how to spin up the environment, sync types, and run tests.

---

## 🛠️ Developer Workflow

This monorepo uses **Nx** to orchestrate tasks across both the Svelte frontend and the asynchronous PHP backend. You do not need to manage multiple terminal processes manually.

### 1. Prerequisites & Installation

Ensure you have Node.js, PHP 8.2+, and Composer installed locally.

First, install dependencies for both ecosystems from the repository root:

```bash
# Install frontend Javascript dependencies
npm install

# Install backend PHP dependencies
cd apps/backend && composer install

```

### 2. Local Development

To spin up the entire application concurrently (Vite asset compilation, the ReactPHP background worker, and the FrankenPHP web server proxy), run the following command from the workspace root:

```bash
nx run backend:dev

```

Once running, you can access your application at `http://localhost:8000`.

### 3. Synchronizing Types (PHP ➡️ TypeScript)

Data models are authored as strictly-typed PHP classes within our local libraries. To automatically generate and sync their matching TypeScript interfaces for the Svelte frontend, execute:

```bash
nx run backend:generate-types

```

*This updates the generated definitions file located at `apps/frontend/src/types/generated.d.ts`.*

### 4. Running Tests

Automated testing is powered by **Pest**. You can execute tests for specific backend modules directly from the workspace root:

```bash
# Run tests for the isolated docker library package
nx test docker-core

```

---

### Workflow Summary Reference

| Operational Task | Command | Scope |
| --- | --- | --- |
| **Dependency Injection** | `npm install && cd apps/backend && composer install` | Full Workspace Initialization |
| **Boot Application Stack** | `nx run backend:dev` | Vite + ReactPHP Worker + FrankenPHP |
| **Generate Type Contracts** | `nx run backend:generate-types` | Syncs PHP DTOs to Svelte `generated.d.ts` |
| **Execute Test Suites** | `nx test docker-core` | Runs Pest assertions on the core logic |
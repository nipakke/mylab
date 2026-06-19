Here is a precise, step-by-step implementation guide tailored for an AI agent or automated developer script to configure this local-to-remote release workflow.

---

## 🛠️ Implementation Guide: Local-First Application Release Workflow

### Overview

This workflow allows the user to trigger a version bump **locally** using standard commit patterns via `bumpp` without pushing local git tags. The remote GitHub Actions workflow intercepts changes to `package.json`, securely generates the git tag on the server, and publishes a structured GitHub Release.

---

### Step 1: Local Dependency Configuration

The agent should inspect the repository's `package.json` and ensure `bumpp` is present as a development dependency.

1. **Add the dependency:**
Execute the following command in the repository root:
```bash
npm install -D bumpp

```


2. **Add a release script shortcut:**
Inject a `release` script into the `scripts` object of `package.json` to unify execution:
```json
"scripts": {
  "release": "bumpp --commit --no-tag"
}

```


*Note: `--no-tag` is critical. It ensures local development environments remain free of untracked local tags.*

---

### Step 2: Configure the GitHub Release Note Categories

To ensure GitHub formats the changelog cleanly, create a release configuration file at `.github/release.yml`. This maps standard commit labels to logical headers.

Create `.github/release.yml` with the following contents:

```yaml
changelog:
  categories:
    - title: "🚀 Features"
      labels:
        - enhancement
        - feature
    - title: "🐛 Fixes"
      labels:
        - bug
        - fix
  exclude:
    - labels:
        - chore
        - ignore

```

---

### Step 3: Create the Automating Workflow File

Create the core CI automation file at `.github/workflows/release.yml`. This script runs on the GitHub runner when it detects modification to `package.json` inside the `main` branch.

Create `.github/workflows/release.yml` with the following contents:

```yaml
name: Handle Version Bump & Release

on:
  push:
    branches:
      - main
    paths:
      - 'package.json'

jobs:
  tag-and-release:
    runs-on: ubuntu-latest
    permissions:
      contents: write
    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Extract Version from package.json
        id: get_version
        run: |
          VERSION=$(node -p "require('./package.json').version")
          echo "VERSION=$VERSION" >> $GITHUB_OUTPUT

      - name: Create and Push Tag
        run: |
          git config user.name "github-actions[bot]"
          git config user.email "github-actions[bot]@users.noreply.github.com"
          
          TAG="v${{ steps.get_version.outputs.VERSION }}"
          
          # Idempotency check: prevents loop execution if workflows stack
          if ! git rev-parse "$TAG" >/dev/null 2>&1; then
            git tag $TAG
            git push origin $TAG
          else
            echo "Tag $TAG already exists. Skipping tag creation."
          fi

      - name: Create GitHub Release
        uses: softprops/action-gh-release@v2
        with:
          tag_name: v${{ steps.get_version.outputs.VERSION }}
          name: Release v${{ steps.get_version.outputs.VERSION }}
          generate_release_notes: true

```

---

## 📋 Verification Checklist for the Agent

Before completing the task, the agent must verify the following state:

1. [ ] `bumpp` is listed in `package.json` under `devDependencies`.
2. [ ] Running `npm run release -- --help` executes correctly without syntax faults.
3. [ ] GitHub Actions file paths are explicitly `.github/release.yml` and `.github/workflows/release.yml`.
4. [ ] The Git user configurations inside the workflow leverage the default `github-actions[bot]` identities to avoid permissions anomalies.




Here is the extension to the implementation guide that adds automated Docker image building and pushing to the pipeline. It hooks directly into the tag creation step so that your Docker images are only built and pushed when a new, valid version tag is generated.

---

## 🐳 Extension Guide: Automated Docker Release Step

### Overview

This step extends the remote GitHub Actions workflow (`.github/workflows/release.yml`). Once the server confirms the new version tag is valid and pushes it, the runner will log into GitHub Packages (GHCR), build your Docker image, tag it with both the exact version (e.g., `v1.0.1`) and `latest`, and push it to the registry.

---

### Step 1: Add Environment Variables

The agent should update the top of the existing `.github/workflows/release.yml` file to define the Docker registry and image naming conventions.

Add the `env` block right under the workflow `name`:

```yaml
name: Handle Version Bump & Release

on:
  push:
    branches:
      - main
    paths:
      - 'package.json'

env:
  REGISTRY: ghcr.io
  # Converts the repository name to lowercase automatically to prevent Docker tagging errors
  IMAGE_NAME: ${{ github.repository }}

```

---

### Step 2: Append Docker Build and Push Steps

The agent must add the following blocks to the bottom of the `tag-and-release` job in `.github/workflows/release.yml`. These steps must happen **after** the `Create and Push Tag` step.

```yaml
      # ... [Previous steps: Checkout, Extract Version, Create and Push Tag, Create GitHub Release] ...

      - name: Log in to the Container registry
        uses: docker/login-action@v3
        with:
          registry: ${{ env.REGISTRY }}
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Extract Docker metadata
        id: meta
        uses: docker/metadata-action@v5
        with:
          images: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}
          # Automatically generates tags for the specific version number and 'latest'
          tags: |
            type=semver,pattern={{version}},value=v${{ steps.get_version.outputs.VERSION }}
            type=raw,value=latest

      - name: Build and push Docker image
        uses: docker/build-push-action@v5
        with:
          context: .
          push: true
          tags: ${{ steps.meta.outputs.tags }}
          labels: ${{ steps.meta.outputs.labels }}
          # Optional: inject the version as a build argument if your app reads it at build-time
          build-args: |
            APP_VERSION=v${{ steps.get_version.outputs.VERSION }}

```

---

## 📋 Additional Verification Checklist for the Agent

Before completing the task, the agent must verify the following Docker-specific prerequisites:

1. [ ] A valid `Dockerfile` exists in the repository root directory (or the context path matches where the Dockerfile is located).
2. [ ] The GitHub repository has its container permissions set correctly (GHCR is enabled by default for organizations/users, but the workflow requires `contents: write` which is already included).
3. [ ] The `IMAGE_NAME` variable uses `${{ github.repository }}` which formats it as `owner/repo-name`. Ensure this matches the desired target container destination.
# MyLab

A self-updating link page that reads Docker container labels and renders them as a clean dashboard. No database, no config files — add labels to your containers and MyLab picks them up.

> 🥤 100% vibe coded

---

## Install

Pull the image from Docker Hub:

```bash
docker pull nipakke/mylab
```

### Run with Docker

```bash
docker run -d \
  --name mylab \
  -p 8080:80 \
  -v /var/run/docker.sock:/var/run/docker.sock \
  nipakke/mylab
```

Visit **`http://localhost:8080`**.

### Run with Docker Compose

```yaml
# compose.yml
services:
  mylab:
    image: nipakke/mylab
    container_name: mylab
    ports:
      - "8080:80"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
    environment:
      - TRUST_PROXY=auto            # trust reverse proxy on same Docker network
    restart: unless-stopped
```

```bash
docker compose up -d
```

### Use with a Docker socket proxy

Mounting the raw Docker socket gives full root access to the host. For better security, use a socket proxy like [`tecnativa/docker-socket-proxy`](https://github.com/Tecnativa/docker-socket-proxy) that exposes only the API endpoints MyLab needs.

MyLab requires only two endpoints:
- `GET /containers/json` — list running containers
- `GET /events` — stream container start/stop events

```yaml
# compose.yml
services:
  socket-proxy:
    image: tecnativa/docker-socket-proxy
    container_name: socket-proxy
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
    environment:
      - CONTAINERS=1
      - EVENTS=1
    restart: unless-stopped

  mylab:
    image: nipakke/mylab
    container_name: mylab
    ports:
      - "8080:80"
    environment:
      - DOCKER_HOST=tcp://socket-proxy:2375
      - TRUST_PROXY=auto
    restart: unless-stopped
```

---

## Docker Container Labels

MyLab shows any container that has at least one `mylab.*` label. Everything else is ignored.

| Label | Required | Description |
|---|---|---|
| `mylab.name` | **Yes** | Display name of the service. |
| `mylab.url` | No | Link URL. If unset, the card appears without a link. |
| `mylab.icon` | No | Icon identifier (see below). |
| `mylab.group` | No | Group name for card grouping. Defaults to `"Other"`. |
| `mylab.description` | No | Short description shown on the card. |
| `mylab.weight` | No | Sort weight within the group. Lower numbers sort first. Default `0`. |
| `mylab.enable` | No | Set to `"false"` to hide the container from the dashboard. |

### Example

```yaml
services:
  grafana:
    image: grafana/grafana
    container_name: grafana
    labels:
      mylab.name: "Grafana"
      mylab.url: "https://grafana.example.com"
      mylab.icon: "grafana"
      mylab.group: "Monitoring"
      mylab.description: "Metrics and dashboards"
      mylab.weight: "10"
```

### Icons

`mylab.icon` accepts either a **URL** to an image or a plain text label (which renders as the first two characters).

**Recommended icon source**: [selfh.st/icons](https://selfh.st/icons/) — a curated collection of self-hosted service icons in SVG, PNG, and WebP. Use the jsDelivr CDN:

```yaml
labels:
  mylab.icon: "https://cdn.jsdelivr.net/gh/selfhst/icons@main/webp/1panel.webp"
```

Other popular icon URLs from the collection:

| Service | Icon URL |
|---|---|
| Grafana | `https://cdn.jsdelivr.net/gh/selfhst/icons@main/webp/grafana.webp` |
| Plex | `https://cdn.jsdelivr.net/gh/selfhst/icons@main/webp/plex.webp` |
| Portainer | `https://cdn.jsdelivr.net/gh/selfhst/icons@main/webp/portainer.webp` |
| Traefik | `https://cdn.jsdelivr.net/gh/selfhst/icons@main/webp/traefik.webp` |
| Home Assistant | `https://cdn.jsdelivr.net/gh/selfhst/icons@main/webp/homeassistant.webp` |
| AdGuard | `https://cdn.jsdelivr.net/gh/selfhst/icons@main/webp/adguard.webp` |
| Nginx | `https://cdn.jsdelivr.net/gh/selfhst/icons@main/webp/nginx.webp` |
| Uptime Kuma | `https://cdn.jsdelivr.net/gh/selfhst/icons@main/webp/uptimekuma.webp` |

Any image URL works — PNG, SVG, WebP, JPG, or even a self-hosted custom icon. If the value is not a URL (e.g. `grafana`), it falls back to the first two characters as text.

---

## Environment Variables

| Variable | Default | Description |
|---|---|---|
| `APP_ENV` | _(none)_ | `local` or `production`. The Docker image defaults to `production`. `local` disables Twig cache and enables `/dev/reload`. |
| `PORT` | `80` (Docker image) | Port the HTTP server listens on. |
| `DOCKER_HOST` | _(Docker default)_ | Docker daemon DSN (e.g. `unix:///var/run/docker.sock` or `tcp://host:2375`). |
| `MYLAB_DOCKER_HOSTS` | _(none)_ | Comma-separated list of Docker daemon DSNs for **multi-host mode**. Takes priority over `DOCKER_HOST`. |
| `RATE_LIMIT` | `60` | Max requests per window per IP (token bucket burst size). |
| `RATE_LIMIT_WINDOW` | `60` | Window duration in seconds for rate limiting. |
| `TRUST_PROXY` | `0` | Proxy trust mode for client IP resolution (see below). |
| `PROXY_IP_HEADER` | `X-Forwarded-For` | Header to read for the real client IP when proxy trust is enabled. |

### Multi-host mode

Set `MYLAB_DOCKER_HOSTS` to a comma-separated list of Docker daemon endpoints to aggregate containers from multiple hosts on a single dashboard:

```bash
docker run -d \
  --name mylab \
  -p 80:80 \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -e MYLAB_DOCKER_HOSTS="unix:///var/run/docker.sock,tcp://10.0.0.5:2375,tcp://10.0.0.6:2375" \
  nipakke/mylab
```

If exactly one DSN is provided, the single `DockerService` is used. Two or more switches to `MultiDockerService`.

### Trust proxy modes

Controls how the real client IP is determined behind a reverse proxy. Important for rate limiting accuracy.

| Mode | Behaviour |
|---|---|
| `0` | Never trust proxies — always use `REMOTE_ADDR`. Default. |
| `1` | Always trust — read real IP from `PROXY_IP_HEADER`. |
| `auto` | Auto-detect: trusts proxy only when `REMOTE_ADDR` is a private IP (Docker/LAN) **or** a known Cloudflare IP. |
| `cloudflare` | Validates `REMOTE_ADDR` against Cloudflare IP ranges before trusting `CF-Connecting-IP`. |
| `traefik` | Trusts `X-Forwarded-For` only when `REMOTE_ADDR` is on a Docker/local network. |

### Recommended settings

```bash
# Behind Traefik or nginx on the same Docker network
docker run ... -e TRUST_PROXY=auto nipakke/mylab

# Behind Cloudflare
docker run ... -e TRUST_PROXY=cloudflare nipakke/mylab

# Directly exposed (no reverse proxy)
docker run ... -e TRUST_PROXY=0 nipakke/mylab
```

### Rate limiting

Each IP gets a token bucket that refills steadily. Up to `RATE_LIMIT` requests are allowed in a burst, then the client is throttled to `RATE_LIMIT / RATE_LIMIT_WINDOW` requests per second. When rate-limited, the server returns **429 Too Many Requests** with a `Retry-After` header.

---

## Development

```bash
npm install          # Node deps + composer install
npm run dev          # starts on localhost:8080 with auto-reload
```

`APP_ENV=local` loads Tailwind and daisyUI from CDN — no CSS build step needed.

Test with sample containers:

```bash
docker compose -f docker-compose.test.yml up -d
```

| Task | Command |
|---|---|
| Run tests | `make test` or `npm test` |
| Lint | `make lint` |
| Format | `make format` |
| Build CSS | `make css` |
| Watch CSS | `npm run watch:css` |

---

## Tech Stack

| Layer | Technology |
|---|---|
| **Runtime** | PHP 8.2+ (ReactPHP async event loop) |
| **Routing** | nikic/fast-route |
| **Templating** | Twig |
| **Frontend** | HTMX 2.0 + Server-Sent Events |
| **CSS** | Tailwind CSS v4 + daisyUI v5 |
| **Docker** | clue/docker-react |

---

## License

MIT

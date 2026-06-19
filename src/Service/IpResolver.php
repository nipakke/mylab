<?php

declare(strict_types=1);

namespace Shipped\Backend\Service;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the real client IP, handling reverse-proxy forwarding.
 *
 * Supports five operating modes via the TRUST_PROXY env var:
 *
 *  - "0" | ""     : Never trust proxies — use REMOTE_ADDR directly.
 *  - "1"          : Always trust — read from PROXY_IP_HEADER (default X-Forwarded-For).
 *  - "auto"       : Auto-detect — trust when REMOTE_ADDR is a private IP
 *                   (Docker/Traefik/LAN) or a known Cloudflare IP.
 *  - "cloudflare" : Validate REMOTE_ADDR is a Cloudflare IP before trusting
 *                   the CF-Connecting-IP header.
 *  - "traefik"    : Trust X-Forwarded-For only when REMOTE_ADDR is on a
 *                   Docker/local network.
 *
 * PROXY_IP_HEADER env var overrides the header name used to extract the
 * real IP (default: "X-Forwarded-For").
 */
final class IpResolver
{
    /** @var list<array{string, string}> IPv4 private / loopback CIDR blocks */
    private const PRIVATE_RANGES_V4 = [
        ['10.0.0.0', '10.255.255.255'],
        ['172.16.0.0', '172.31.255.255'],
        ['192.168.0.0', '192.168.255.255'],
        ['127.0.0.0', '127.255.255.255'],
    ];

    /** Cloudflare IPv4 CIDRs (source: https://api.cloudflare.com/client/v4/ips). */
    private const CLOUDFLARE_CIDRS_V4 = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
    ];

    /** Cloudflare IPv6 CIDRs. */
    private const CLOUDFLARE_CIDRS_V6 = [
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    private const DOCKER_BRIDGE_START = '172.17.0.0';

    private const DOCKER_BRIDGE_END = '172.17.255.255';

    private readonly string $mode;

    private readonly string $proxyHeader;

    public function __construct()
    {
        $mode = strtolower(trim(getenv('TRUST_PROXY') ?: ''));
        $this->mode = $mode === '' ? '0' : $mode;

        $this->proxyHeader = trim(getenv('PROXY_IP_HEADER') ?: 'X-Forwarded-For');
    }

    /**
     * Resolve the client IP from the given request.
     */
    public function resolve(ServerRequestInterface $request): string
    {
        return match ($this->mode) {
            'cloudflare' => $this->fromCloudflare($request),
            'traefik' => $this->fromTraefik($request),
            'auto' => $this->fromAuto($request),
            '1' => $this->fromProxy($request),
            default => $this->fromRemote($request),
        };
    }

    // ── Strategy methods ───────────────────────────────────────────

    /**
     * Always use the direct remote address (no proxy trust).
     */
    private function fromRemote(ServerRequestInterface $request): string
    {
        return $this->remoteAddr($request);
    }

    /**
     * Always trust the configured proxy header.
     */
    private function fromProxy(ServerRequestInterface $request): string
    {
        $ip = $this->leftmostForwarded($request);

        return $ip !== '' ? $ip : $this->remoteAddr($request);
    }

    /**
     * Auto-detect: trust proxy when REMOTE_ADDR is private, Docker, or Cloudflare.
     */
    private function fromAuto(ServerRequestInterface $request): string
    {
        $remote = $this->remoteAddr($request);

        if ($this->isPrivateIp($remote) || $this->isCloudflareIp($remote)) {
            // If Cloudflare, prefer their proprietary header
            if ($this->isCloudflareIp($remote)) {
                $cf = $this->firstHeader($request, 'CF-Connecting-IP');
                if ($cf !== '') {
                    return $cf;
                }
            }

            $ip = $this->leftmostForwarded($request);

            return $ip !== '' ? $ip : $remote;
        }

        return $remote;
    }

    /**
     * Trust Cloudflare IPs — validates REMOTE_ADDR is a known Cloudflare IP
     * before trusting CF-Connecting-IP.
     */
    private function fromCloudflare(ServerRequestInterface $request): string
    {
        $remote = $this->remoteAddr($request);

        if (! $this->isCloudflareIp($remote)) {
            // Not coming from Cloudflare — don't trust the header
            return $remote;
        }

        $cf = $this->firstHeader($request, 'CF-Connecting-IP');

        return $cf !== '' ? $cf : $remote;
    }

    /**
     * Trust X-Forwarded-For only when REMOTE_ADDR is on a Docker/local network.
     */
    private function fromTraefik(ServerRequestInterface $request): string
    {
        $remote = $this->remoteAddr($request);

        if ($this->isPrivateIp($remote)) {
            $ip = $this->leftmostForwarded($request);

            return $ip !== '' ? $ip : $remote;
        }

        return $remote;
    }

    // ── Private helpers ────────────────────────────────────────────

    /**
     * Extract the leftmost IP from the X-Forwarded-For chain.
     *
     * X-Forwarded-For format: "client, proxy1, proxy2"
     * The leftmost value is the originating client IP.
     */
    private function leftmostForwarded(ServerRequestInterface $request): string
    {
        $header = $this->firstHeader($request, $this->proxyHeader);
        if ($header === '') {
            return '';
        }

        $parts = explode(',', $header);

        return trim($parts[0]);
    }

    /**
     * Get the REMOTE_ADDR server parameter.
     */
    private function remoteAddr(ServerRequestInterface $request): string
    {
        $params = $request->getServerParams();

        return isset($params['REMOTE_ADDR']) ? (string) $params['REMOTE_ADDR'] : '127.0.0.1';
    }

    /**
     * Get the first value of a request header, or empty string.
     */
    private function firstHeader(ServerRequestInterface $request, string $name): string
    {
        $values = $request->getHeader($name);

        return count($values) > 0 ? $values[0] : '';
    }

    // ── IP range checks ────────────────────────────────────────────

    /**
     * Check whether an IPv4 address falls within private / loopback ranges.
     */
    private function isPrivateIp(string $ip): bool
    {
        $packed = ip2long($ip);
        if ($packed === false) {
            return false;
        }

        foreach (self::PRIVATE_RANGES_V4 as [$start, $end]) {
            if ($this->inV4Range($packed, $start, $end)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether an IP address is a known Cloudflare edge IP.
     *
     * Handles both IPv4 (32-bit packed) and IPv6 (128-bit binary string).
     */
    private function isCloudflareIp(string $ip): bool
    {
        // Try IPv4 first
        $packed = ip2long($ip);
        if ($packed !== false) {
            foreach (self::CLOUDFLARE_CIDRS_V4 as $cidr) {
                if ($this->inV4Cidr($packed, $cidr)) {
                    return true;
                }
            }

            return false;
        }

        // Try IPv6
        $bin = @inet_pton($ip);
        if ($bin === false || strlen($bin) !== 16) {
            return false;
        }

        foreach (self::CLOUDFLARE_CIDRS_V6 as $cidr) {
            if ($this->inV6Cidr($bin, $cidr)) {
                return true;
            }
        }

        return false;
    }

    // ── Low-level range helpers ────────────────────────────────────

    /**
     * Check whether a packed IPv4 long is within a start/end range.
     */
    private function inV4Range(int $packed, string $start, string $end): bool
    {
        $s = ip2long($start);
        $e = ip2long($end);

        return $s !== false && $e !== false && $packed >= $s && $packed <= $e;
    }

    /**
     * Check whether a packed IPv4 long is within a CIDR block.
     */
    private function inV4Cidr(int $packed, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $subnetPacked = ip2long($subnet);
        if ($subnetPacked === false) {
            return false;
        }

        $mask = -1 << (32 - (int) $bits);
        $subnetPacked &= $mask;
        $packed &= $mask;

        return $packed === $subnetPacked;
    }

    /**
     * Check whether a packed IPv6 binary string is within a CIDR block.
     */
    private function inV6Cidr(string $bin, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $bits = (int) $bits;
        $subnetBin = @inet_pton($subnet);
        if ($subnetBin === false) {
            return false;
        }

        $bytesToCompare = $bits >> 3;          // Full bytes
        $remainingBits = $bits & 7;            // Bits in partial byte

        // Compare full bytes
        for ($i = 0; $i < $bytesToCompare; $i++) {
            if ($bin[$i] !== $subnetBin[$i]) {
                return false;
            }
        }

        // Compare remaining bits in the partial byte
        if ($remainingBits > 0) {
            $mask = 0xFF << (8 - $remainingBits);
            if ((ord($bin[$bytesToCompare]) & $mask)
                !== (ord($subnetBin[$bytesToCompare]) & $mask)) {
                return false;
            }
        }

        return true;
    }
}

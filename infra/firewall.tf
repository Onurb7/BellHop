resource "hcloud_firewall" "web" {
  name = "bellhop-web"

  rule {
    direction  = "in"
    protocol   = "tcp"
    port       = "22"
    source_ips = var.admin_ipv4_cidrs
  }

  # The site is only reachable on 80/443 through Cloudflare's proxy — the
  # box itself has no public HTTP(S) surface outside that range. A real
  # hardening step, not just DNS-level obscurity.
  rule {
    direction  = "in"
    protocol   = "tcp"
    port       = "80"
    source_ips = concat(var.cloudflare_ipv4_cidrs, var.cloudflare_ipv6_cidrs)
  }

  rule {
    direction  = "in"
    protocol   = "tcp"
    port       = "443"
    source_ips = concat(var.cloudflare_ipv4_cidrs, var.cloudflare_ipv6_cidrs)
  }

  # Mailpit's web UI (production's mail sink — see docker-compose.prod.yml)
  # shows the full content of every email the app sends, including
  # password-reset links — restricted to the same trusted-IP list as SSH,
  # never opened to the public internet.
  rule {
    direction  = "in"
    protocol   = "tcp"
    port       = "8025"
    source_ips = var.admin_ipv4_cidrs
  }
}

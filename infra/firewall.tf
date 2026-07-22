resource "hcloud_firewall" "web" {
  name = "bellhop-web"

  # Open to all source IPs, not just admin_ipv4_cidrs — GitHub Actions'
  # hosted runners come from a huge, constantly-changing pool of Azure IPs
  # that can never be usefully allowlisted, confirmed live (the deploy job
  # timed out here on its first real run). Password auth was never enabled
  # on this box (cloud-init only ever installs an SSH public key, no
  # `passwd:`), so key-only auth is the actual security boundary here, not
  # the source-IP restriction — this matches how the overwhelming majority
  # of "deploy via SSH from CI" setups work in practice.
  rule {
    direction  = "in"
    protocol   = "tcp"
    port       = "22"
    source_ips = ["0.0.0.0/0", "::/0"]
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

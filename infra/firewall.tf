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
}

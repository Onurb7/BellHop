output "server_ipv4" {
  description = "Public IPv4 address — point Cloudflare's DNS A record here, and use it as the DEPLOY_HOST GitHub Actions secret."
  value       = hcloud_server.app.ipv4_address
}

output "server_ipv6" {
  description = "Public IPv6 address — point Cloudflare's DNS AAAA record here."
  value       = hcloud_server.app.ipv6_address
}

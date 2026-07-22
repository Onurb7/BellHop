variable "hcloud_token" {
  description = "Hetzner Cloud API token (Project > Security > API Tokens > Read & Write). Never commit this — set it in terraform.tfvars, which is gitignored."
  type        = string
  sensitive   = true
}

variable "server_name" {
  description = "Name of the Hetzner Cloud server."
  type        = string
  default     = "bellhop-prod"
}

variable "server_type" {
  description = "Hetzner server plan."
  type        = string
  default     = "cx23" # 2 vCPU / 4GB RAM / 40GB disk. Hetzner renamed/
  # superseded CX22 with this plan in an April 2026 lineup refresh (same
  # specs) and raised prices further in June 2026 — real price as of
  # 2026-07-22 is ~€6.86/mo VAT-included, not the older ~€3.79-4.49
  # figures still floating around various pricing pages. It's also
  # "Cost-Optimized" tier (Hetzner's own label), meaning capacity is
  # genuinely limited and fluctuates by location/day — confirmed
  # unavailable in Falkenstein, then available again in Nuremberg
  # (var.location's default) within the same day. If `apply` ever fails
  # on a capacity error, just retry shortly or try fsn1/hel1.
}

variable "location" {
  description = "Hetzner datacenter region."
  type        = string
  default     = "nbg1" # Nuremberg; alternatives: fsn1, hel1 (EU), ash, hil (US)
}

variable "ssh_public_key_path" {
  description = "Path to the DEDICATED deploy keypair's PUBLIC key. Do NOT point this at your personal ~/.ssh key — generate a separate keypair for GitHub Actions to use (see README in this directory)."
  type        = string
  default     = "./bellhop_deploy_key.pub"
}

variable "admin_ipv4_cidrs" {
  description = <<-EOT
    Your trusted public IP(s) in CIDR form (e.g. ["203.0.113.4/32"]) —
    scopes SSH access to just you. Find your current IP with
    `curl -4 ifconfig.me`. A list, not a single value, so you can have
    more than one trusted location active at once (e.g. while travelling)
    without kicking the other one out — just append, run `terraform apply`,
    remove later whenever a location is no longer relevant.
  EOT
  type        = list(string)
}

variable "cloudflare_ipv4_cidrs" {
  description = "Cloudflare's published IPv4 ranges (https://www.cloudflare.com/ips-v4). Verified current as of 2026-07-21 — refresh periodically, Cloudflare does occasionally change these."
  type        = list(string)
  default = [
    "173.245.48.0/20", "103.21.244.0/22", "103.22.200.0/22", "103.31.4.0/22",
    "141.101.64.0/18", "108.162.192.0/18", "190.93.240.0/20", "188.114.96.0/20",
    "197.234.240.0/22", "198.41.128.0/17", "162.158.0.0/15", "104.16.0.0/13",
    "104.24.0.0/14", "172.64.0.0/13", "131.0.72.0/22"
  ]
}

variable "cloudflare_ipv6_cidrs" {
  description = "Cloudflare's published IPv6 ranges (https://www.cloudflare.com/ips-v6). Verified current as of 2026-07-21."
  type        = list(string)
  default = [
    "2400:cb00::/32", "2606:4700::/32", "2803:f800::/32", "2405:b500::/32",
    "2405:8100::/32", "2a06:98c0::/29", "2c0f:f248::/32"
  ]
}

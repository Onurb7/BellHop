# Bellhop infrastructure (Terraform + Hetzner Cloud)

Provisions the single production VPS: a Hetzner Cloud CX22 (2 vCPU/4GB RAM,
~€3.79/mo), a firewall restricting SSH to your own IP(s) and 80/443 to
Cloudflare's published ranges only, and a cloud-init script that installs
Docker, creates a `deploy` user, sets up swap, and clones the repo.

This directory only provisions the box. It does not install TLS
certificates, publish images, or deploy the app — those are later phases.

## One-time setup

1. **Create a Hetzner Cloud account and project**, then generate an API
   token: Project → Security → API Tokens → **Read & Write**.

2. **Generate a dedicated deploy keypair** — not your personal
   `~/.ssh/id_ed25519`. This becomes a GitHub Actions secret later, so it
   should be single-purpose:
   ```bash
   ssh-keygen -t ed25519 -f infra/bellhop_deploy_key -C "github-actions-deploy@bellhop" -N ""
   ```

3. **Copy the tfvars template and fill it in**:
   ```bash
   cp infra/terraform.tfvars.example infra/terraform.tfvars
   ```
   Set `hcloud_token` (from step 1) and `admin_ipv4_cidrs` (your current
   public IP — `curl -4 ifconfig.me` — in CIDR form, e.g. `"1.2.3.4/32"`).
   `terraform.tfvars` is gitignored; never commit it.

4. **Install Terraform locally** if you don't already have it (e.g.
   `apt install terraform` via HashiCorp's apt repo, or download a binary
   from terraform.io).

## Running it

```bash
cd infra
terraform init      # downloads the hcloud provider — no API calls yet
terraform fmt -check
terraform validate
terraform plan       # shows what would be created — review it
terraform apply       # actually provisions the server — real, billed infra
```

`terraform apply` is always something **you** run yourself, never something
run on your behalf — it's real infrastructure billed to your Hetzner
account. It prints `server_ipv4`/`server_ipv6` when done.

## After `apply` succeeds

- **DNS**: point Cloudflare's A (and AAAA) record at `server_ipv4`
  (`server_ipv6`), with the proxy toggle on. TLS/SSL mode setup is a later
  phase (needs the app actually deployed first).
- **GHCR pull auth**: SSH in as `deploy` and log in once so future deploys
  never need to re-authenticate:
  ```bash
  ssh deploy@<server_ipv4>
  docker login ghcr.io -u <your-github-username>   # paste a PAT scoped to read:packages when prompted
  ```
- **GitHub Actions secrets** (for the later CD phase): `DEPLOY_HOST` =
  `server_ipv4`, `DEPLOY_USER` = `deploy`, `DEPLOY_SSH_KEY` = the full
  contents of `infra/bellhop_deploy_key` (the private half).

## Updating the trusted SSH IP list later

Hetzner Cloud Firewalls apply instantly and don't touch the running
server — editing `admin_ipv4_cidrs` in `terraform.tfvars` and re-running
`terraform apply` takes effect in seconds, no downtime, no server
recreation. It's a list specifically so more than one trusted location can
be active at once (e.g. while travelling) — just add an entry rather than
swapping one out, and remove it later once it's no longer needed.

## What's deliberately not here

- No remote Terraform state backend — `terraform.tfstate` is local and
  gitignored, which is fine for solo work. Don't lose it; if this ever
  needs a second operator, that's the point to add an S3-compatible remote
  backend (e.g. Hetzner Object Storage).
- No Hetzner Floating IP — a CX22 ships with a stable public IP by default,
  which is what DNS points at directly.
- No passwordless sudo for `deploy` — the deploy workflow only ever needs
  `docker compose` (covered by `docker` group membership). Anything
  genuinely needing root can go through the Hetzner Console directly.

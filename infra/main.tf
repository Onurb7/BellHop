resource "hcloud_ssh_key" "deploy" {
  name       = "bellhop-deploy-key"
  public_key = file(var.ssh_public_key_path)
}

resource "hcloud_server" "app" {
  name         = var.server_name
  server_type  = var.server_type
  image        = "ubuntu-22.04"
  location     = var.location
  ssh_keys     = [hcloud_ssh_key.deploy.id]
  firewall_ids = [hcloud_firewall.web.id]

  user_data = templatefile("${path.module}/cloud-init.yaml.tpl", {
    deploy_ssh_public_key = file(var.ssh_public_key_path)
  })

  public_net {
    ipv4_enabled = true
    ipv6_enabled = true
  }

  labels = {
    project = "bellhop"
    env     = "production"
  }
}

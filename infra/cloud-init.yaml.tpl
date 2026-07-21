#cloud-config
package_update: true
package_upgrade: true

packages:
  - git

users:
  - name: deploy
    groups: [docker]
    shell: /bin/bash
    ssh_authorized_keys:
      - ${deploy_ssh_public_key}

runcmd:
  # Docker Engine + the Compose v2 plugin, via Docker's own install script.
  - curl -fsSL https://get.docker.com | sh
  - usermod -aG docker deploy

  # CD only ever needs `docker compose` (deploy is already in the docker
  # group for that) — no passwordless sudo standing by default. Anything
  # genuinely needing root can go through the Hetzner Console directly.

  # Cheap insurance against a burst (media processing, a big migration, an
  # FPM spike) OOM-killing something on a 4GB box.
  - fallocate -l 2G /swapfile
  - chmod 600 /swapfile
  - mkswap /swapfile
  - swapon /swapfile
  - echo '/swapfile none swap sw 0 0' >> /etc/fstab
  - sysctl -w vm.swappiness=10
  - echo 'vm.swappiness=10' >> /etc/sysctl.conf

  - mkdir -p /opt/bellhop
  - chown deploy:deploy /opt/bellhop
  - su - deploy -c "git clone https://github.com/Onurb7/BellHop.git /opt/bellhop"

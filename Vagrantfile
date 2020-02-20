# Provides a CATMAID development environment

# required to prevent virtualbox trying to start several servers
# on the same interface
# https://github.com/hashicorp/vagrant/issues/8878#issuecomment-345112810
class VagrantPlugins::ProviderVirtualBox::Action::Network
  def dhcp_server_matches_config?(dhcp_server, config)
    true
  end
end

Vagrant.configure("2") do |config|
  config.vm.box = "generic/ubuntu1604"
  config.vm.synced_folder "./", "/CATMAID"
  config.vm.network "forwarded_port", guest: 8000, host: 8000
  config.vm.network "private_network", type: "dhcp"

  config.vm.provision :shell, path: "scripts/vagrant/provision.sh"
  config.vm.provision :shell, privileged: false, path: "scripts/vagrant/python_node.sh"
end

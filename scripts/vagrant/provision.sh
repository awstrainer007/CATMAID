#!/bin/bash
set -e
set -x

export DEBIAN_FRONTEND=noninteractive

PG_URL="http://apt.postgresql.org/pub/repos/apt/"
APT_LINE="deb ${PG_URL} $(lsb_release -cs)-pgdg main"
echo "${APT_LINE}" | sudo tee "/etc/apt/sources.list.d/pgdg.list"
apt-get install wget ca-certificates
PG_KEY_URL="https://www.postgresql.org/media/keys/ACCC4CF8.asc"
wget --quiet -O - ${PG_KEY_URL} | sudo apt-key add -

add-apt-repository ppa:deadsnakes/ppa
add-apt-repository ppa:ubuntugis/ppa

curl -sL https://deb.nodesource.com/setup_12.x | sudo -E bash -

apt-get update

cd /CATMAID
sudo xargs apt-get install -y < packagelist-ubuntu-16.04-apt.txt
apt-get install -y nodejs python3-pip python3.6-venv python3-wheel

# if it is not already,
# prepend line to pg_hba.conf
# and restart postgres
#HBA_LINE="local catmaid catmaid_user md5"
#HBA_PATH="/etc/postgresql/11/main/pg_hba.conf"
#grep "$HBA_LINE" $HBA_PATH \
#    || (echo "$HBA_LINE" && cat $HBA_PATH) > $HBA_PATH \
#    && service postgresql restart


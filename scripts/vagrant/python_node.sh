#!/bin/bash
set -e
set -x

python3.6 -m venv ~/catmaid-env
source ~/catmaid-env/bin/activate
echo "source ~/catmaid-env/bin/activate" >> ~/.bashrc

cd /CATMAID/django
pip install -U pip
pip install -r requirements-dev.txt

cd /CATMAID
npm install


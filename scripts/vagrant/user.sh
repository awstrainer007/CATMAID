#!/bin/bash
set -e
set -x

# set up python environment
python3.6 -m venv ~/catmaid-env
source ~/catmaid-env/bin/activate
echo "source ~/catmaid-env/bin/activate" >> ~/.bashrc

# install python dependencies
cd /CATMAID/django
pip install -U pip
pip install -r requirements-dev.txt

# install node dependencies
cd /CATMAID
npm install

# set up gitignore
mkdir -p ~/.config/git
echo "
# backup files
*.bak
*.swp
*~
*#
.orig

# editor
.idea
*.iml
.vscode

# python
.venv
pyvenv.cfg
pip-selfcheck.json
.Python
.python-version*
.pytest_cache/
*.pyc
*.pyo

# git merges
*_BACKUP_*
*_BASE_*
*_LOCAL_*
*_REMOTE_*

# environment variables
.envrc
.env

# node
node_modules/

" >> ~/.config/git/ignore

git config --global user.useConfigOnly true


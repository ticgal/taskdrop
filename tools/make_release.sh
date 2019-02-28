#!/bin/bash
#
# -------------------------------------------------------------------------
# make_release.sh
# Based on fusioninventory-for-glpi make_release.sh
# Copyright (C) 2018-2019 by TICgal 
# https://github.com/ticgal/taskdrop
# -------------------------------------------------------------------------
# LICENSE
# This file is part of the Task&drop plugin.
# Task&drop plugin is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 3 of the License, or
# (at your option) any later version.
# Task&drop plugin is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# You should have received a copy of the GNU General Public License
# along with Task&drop. If not, see <http://www.gnu.org/licenses/>.
# --------------------------------------------------------------------------
# @package   Task&drop
# @author    TICgal
# @copyright Copyright (c) 2018-2019 TICgal
# @license   AGPL License 3.0 or (at your option) any later version
#            http://www.gnu.org/licenses/agpl-3.0-standalone.html
# @link      https://tic.gal
# @since     2018
# --------------------------------------------------------------------------

if [ ! "$#" -eq 2 ]
then
 echo "Usage $0 fi_git_dir release";
 exit ;
fi

read -p "Are translations up to date? [Y/n] " -n 1 -r
echo    # (optional) move to a new line
if [[ ! $REPLY =~ ^[Yy]$ ]]
then
    [[ "$0" = "$BASH_SOURCE" ]] && exit 1 || return 1 # handle exits from shell or function but don't exit interactive shell
fi

INIT_DIR=$1;
RELEASE=$2;

# test glpi_cvs_dir
if [ ! -e $INIT_DIR ]
then
 echo "$1 does not exist";
 exit ;
fi

INIT_PWD=$PWD;

if [ -e /tmp/actualtime ]
then
 echo "Delete existing temp directory";
\rm -rf /tmp/actualtime;
fi

echo "Copy to  /tmp directory";
git checkout-index -a -f --prefix=/tmp/actualtime/

echo "Move to this directory";
cd /tmp/actualtime;

echo "Check version"
if grep --quiet $RELEASE setup.php; then
  echo "$RELEASE found in setup.php, OK."
else
  echo "$RELEASE has not been found in setup.php. Exiting."
  exit 1;
fi
if grep --quiet $RELEASE actualtime.xml; then
  echo "$RELEASE found in actualtime.xml, OK."
else
  echo "$RELEASE has not been found in actualtime.xml. Exiting."
  exit 1;
fi

echo "Set version and official release"
sed \
   -e 's/"PLUGIN_ACTUALTIME_OFFICIAL_RELEASE", "0"/"PLUGIN_ACTUALTIME_OFFICIAL_RELEASE", "1"/' \
   -e 's/ SNAPSHOT//' \
   -i '' setup.php

#echo "Minify stylesheets and javascripts"
#$INIT_PWD/vendor/bin/robo minify

echo "Compile locale files"
./tools/update_mo.pl

echo "Delete various scripts and directories"
\rm -rf vendor;
\rm -rf RoboFile.php;
\rm -rf tools;
\rm -rf phpunit;
\rm -rf tests;
\rm -rf .gitignore;
\rm -rf .travis.yml;
\rm -rf .coveralls.yml;
\rm -rf phpunit.xml.dist;
\rm -rf composer.json;
\rm -rf composer.lock;
\rm -rf .composer.hash;
\rm -rf ISSUE_TEMPLATE.md;
\rm -rf PULL_REQUEST_TEMPLATE.md;
\rm -rf .tx;
\rm -rf actualtime.xml;
\rm -rf screenshots;
\find pics/ -type f -name "*.eps" -exec rm -rf {} \;

echo "Creating tarball";
cd ..;
tar czf "actualtime-$RELEASE.tar.tgz" actualtime

cd $INIT_PWD;

echo "Deleting temp directory";
\rm -rf /tmp/actualtime;

echo "The Tarball is in the /tmp directory";
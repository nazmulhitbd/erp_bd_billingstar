#!/bin/zsh
# Rebuilds install_script/sql/sample_data.sql: a fresh install of the bs_sample_build copy, every seeding stage through the
# real forms, a second fresh install to compare with, then the export. Stops at the first failure. See README.md.
set -e -o pipefail
S=$(cd "$(dirname "$0")" && pwd)
WWW=$(cd "$S/../../.." && pwd)
DATA=${SAMPLE_MYSQL_DATA:?set SAMPLE_MYSQL_DATA to the private MySQL data folder (see README.md)}
cd $S
(cd $DATA && /opt/local/lib/mysql8/bin/mysql --socket=s.sock -uroot -e "DROP DATABASE IF EXISTS bs_sample; CREATE DATABASE bs_sample CHARACTER SET utf8mb4;")
rm -f $WWW/bs_sample_build/addon/display/dbconfig.php
php inst_cli.php | tail -3
chmod 644 $WWW/bs_sample_build/addon/display/dbconfig.php
JAR=${TMPDIR:-/tmp}/petabill_sample.jar
rm -f $JAR
curl -s -c $JAR -b $JAR http://localhost/bs_sample_build/ -o /dev/null
curl -s -c $JAR -b $JAR http://localhost/bs_sample_build/index.php --data "user_name=admin&password=SampleAdmin2026&okButton=1&branch_id=1" -o /dev/null -w "login %{http_code}\n"
for s in seed1_master seed2_parties seed3_stock seed4_sales seed5_accounts seed6_hr seed6b_more seed7_approve; do
  echo "== $s"; php $s.php | grep -v "^  ok" || true
done
echo BUILD-OK
(cd $DATA && /opt/local/lib/mysql8/bin/mysql --socket=s.sock -uroot -e "DROP DATABASE IF EXISTS bs_fresh; CREATE DATABASE bs_fresh CHARACTER SET utf8mb4;")
php fresh_cli.php | tail -1
php export_sample.php | tail -1

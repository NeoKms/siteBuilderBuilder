sh env.sh
rm -rf $WWW_PATH
mkdir $WWW_PATH
php -f build.php
chmod -R 777 $WWW_PATH

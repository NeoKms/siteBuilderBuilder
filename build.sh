sh env.sh
rm -rf $APP_PATH
mkdir $APP_PATH
php -f build.php
chmod -R 777 $APP_PATH

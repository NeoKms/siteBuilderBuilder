exec 3>'$APP_PATH'.env
echo "SITE_ID = $SITE_ID" >&3
echo "API_HOST_NAME = $API_HOST_NAME" >&3
echo "APP_PATH = $APP_PATH" >&3
echo "WWW_PATH = $WWW_PATH" >&3
echo "API_PASS = $API_PASS" >&3
echo "API_LOGIN = $API_LOGIN" >&3
echo "AUTH_KEY = $AUTH_KEY" >&3
echo "RABBIT_USER = $RABBIT_USER" >&3
echo "RABBIT_HOST = $RABBIT_HOST" >&3

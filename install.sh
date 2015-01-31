#!/bin/bash
# Props https://raspberry-hosting.com/en/faq/how-install-nginx-php-fpm-raspberry-pi

if [ "$(id -u)" != "0" ]; then
	echo "This script must be run as root" 1>&2
	exit 1
fi

set -e

cd "$(dirname $0)"

apt-get install -y nginx php5 php5-fpm

cp nginx.conf /etc/nginx/sites-available/phpicam
if [ ! -e /etc/nginx/sites-enabled/phpicam ]; then
	ln -s /etc/nginx/sites-available/phpicam /etc/nginx/sites-enabled/phpicam
fi

if ! grep -q phpicam /etc/hosts; then
	echo '127.0.0.1  phpicam.local' >> /etc/hosts
fi

user_home=/home/phpicam
if ! id -g phpicam 2>&1 >/dev/null; then
	addgroup phpicam
fi
gid=$( getent group phpicam | cut -d: -f3 )
if ! id -u phpicam 2>&1 >/dev/null; then
	adduser --home "$user_home" --shell /dev/null --gid "$gid" --disabled-password --disabled-login --gecos '' phpicam
fi
usermod -G video phpicam

mkdir -p "$user_home/logs"
mkdir -p "$user_home/logs/php"
mkdir -p "$user_home/www"
mkdir -p "$user_home/tmp"
mkdir -p "$user_home/sessions"

cp /etc/php5/fpm/pool.d/www.conf /etc/php5/fpm/pool.d/phpicam.conf
sed -i 's/www-data/phpicam/g' /etc/php5/fpm/pool.d/phpicam.conf
sed -i 's/\[www\]/[phpicam]/g' /etc/php5/fpm/pool.d/phpicam.conf
sed -i 's/php5-fpm\.sock/php5-fpm-phpicam\.sock/' /etc/php5/fpm/pool.d/phpicam.conf
sed -i 's/;listen.mode = 0660/listen.mode = 0666/' /etc/php5/fpm/pool.d/phpicam.conf
cat php-fpm.conf >> /etc/php5/fpm/pool.d/phpicam.conf

rsync -avz www/ "$user_home/www"

if ! grep -q phpicam-cache /etc/fstab; then
	echo "tmpfs /phpicam-cache tmpfs defaults,noatime,mode=1777 0 0" >> /etc/fstab
	mkdir /phpicam-cache
	chown -R phpicam:phpicam /phpicam-cache
	mount -a
fi

chown -R phpicam:phpicam "$user_home"

service nginx restart && service php5-fpm restart

# TODO Set up cron to empty out stale files from cache
# Todo Also install ngrok
# ngrok start phpicam
# @todo Cron to fetch every minute


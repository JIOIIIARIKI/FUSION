#!/bin/bash

# === 1. ===
echo "Установка FusionPBX"

wget -O - https://raw.githubusercontent.com/fusionpbx/fusionpbx-install.sh/master/debian/pre-install.sh | sh
cd /usr/src/fusionpbx-install.sh/debian && ./install.sh

# === 2. ===
echo "Запоминаем данные"

INSTALL_LOG=$(tail -n 30 /var/log/fusionpbx/install.log)
DOMAIN_NAME=$(echo "$INSTALL_LOG" | grep "domain name:" | awk '{print $NF}')
USERNAME=$(echo "$INSTALL_LOG" | grep "username:" | awk '{print $NF}')
PASSWORD=$(echo "$INSTALL_LOG" | grep "password:" | awk '{print $NF}')

echo "domain_name=$DOMAIN_NAME" > /root/datafusion.txt
echo "username=$USERNAME" >> /root/datafusion.txt
echo "password=$PASSWORD" >> /root/datafusion.txt
echo "Данные сохранены в /root/datafusion.txt"

# === 3. ===
DB_PASS=$(grep 'database.0.password' /etc/fusionpbx/config.conf | awk -F' = ' '{print $2}')
echo "Пароль БД FusionPBX: $DB_PASS"
echo "db_password=$DB_PASS" >> /root/datafusion.txt

# === 4. ===
read -p "Напиши мне домен, который ты хочешь использовать (пример: pbx.domain.com): " DOMAIN_BOT
echo "domainbot=$DOMAIN_BOT" >> /root/datafusion.txt

# === 5. ===
echo "Устанавливаем MariaDB"

apt update
apt install -y mariadb-server

systemctl start mariadb
systemctl enable mariadb

mysql -uroot <<EOF
CREATE DATABASE IF NOT EXISTS ipadd;
ALTER USER 'root'@'localhost' IDENTIFIED BY '1Yb74rfBhrTwtJHh';
GRANT ALL PRIVILEGES ON ipadd.* TO 'root'@'localhost';
FLUSH PRIVILEGES;

USE ipadd;
CREATE TABLE IF NOT EXISTS ip_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45),
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    prefix VARCHAR(50),
    user VARCHAR(50),
    method VARCHAR(50)
);
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100),
    password VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
EOF


echo "MariaDB"

# === 6. ===
echo "Заменяем eavesdrop.lua"

wget -O /usr/share/freeswitch/scripts/eavesdrop.lua https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/scripts/eavesdrop.lua
wget -P /usr/share/freeswitch/scripts/ https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/scripts/randomnum.lua
wget -P /usr/share/freeswitch/scripts/ https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/scripts/randomnumAU.lua
wget -P /usr/share/freeswitch/scripts/ https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/scripts/randomnumNZ.lua
wget -P /usr/share/freeswitch/scripts/ https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/scripts/trim_9053.lua
wget -P /usr/share/freeswitch/scripts/ https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/scripts/trim_number.lua
wget -P /usr/share/freeswitch/scripts/ https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/scripts/trim_number3.lua
# === 7. ===
echo "Клонирование и установка веб-файлов"

mkdir -p /var/www/fusionpbx/form/
wget -P /var/www/fusionpbx/form/ https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/form/IP.php
wget -P /var/www/fusionpbx/form/ https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/form/add.css
wget -P /var/www/fusionpbx/form/ https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/form/add.php
wget -P /var/www/fusionpbx/form/ https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/form/history.css
wget -P /var/www/fusionpbx/form/ https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/form/history.php
wget -P /var/www/fusionpbx/form/ https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/form/login.php
wget -P /var/www/fusionpbx/form/ https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/form/reset.php
wget -P /var/www/fusionpbx/form/ https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/form/styles.css

# === 8. ===
echo "Копируем файлы vsa"

mkdir -p /var/www/fusionpbx/app/vsa/
wget -P /var/www/fusionpbx/app/vsa/ https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/vsa/{dialplan_outbound_add.php,dialplan_outbound_add1.php,dialplan_outbound_add2.php,domain_edit.php,extension_edit.php,user_edit.php,vsa.php,vsa_dial.php,vsa_ext.php}

# === 9. ===
echo "Копируем файлы ботов"

mkdir -p /root/bots/
wget -P /root/bots/ https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/bot/{add_client.py,ap.py,ext_user.py,ip.py,ipch.py,pbx.py,up_pr.py,zabbix.py}

# === 10.===
echo "Копируем .service"

wget -P /etc/systemd/system/ https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/service/{ap.service,up_pr.service,zabbix.service,pbx.service,ipch.service,ip.service}

echo "Копируем v_inf"
wget -P /var/www/fusionpbx/ https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/v_inf/v_inf.php
wget -P /var/www/fusionpbx/ https://raw.githubusercontent.com/JIOIIIARIKI/FUSION/main/v_inf/123.jpg

# === 11.===
echo "Замена переменных"

find /root/bots/ -type f -name "*.py" -exec sed -i "s/{passfusion}/$DB_PASS/g" {} \;
find /root/bots/ -type f -name "*.py" -exec sed -i "s/{domainbot}/$DOMAIN_BOT/g" {} \;

# === 12. ===
echo "Создаём таблицы v_inf и v_read"

sudo -u postgres psql <<EOF
\c fusionpbx;

-- Создание таблицы v_inf
CREATE TABLE IF NOT EXISTS public.v_inf (
    id text NOT NULL,
    username text,
    password text,
    group_name text,
    domain text,
    internal_number text,
    context_value text,
    quantity integer,
    dialplan_name text,
    user_prefix integer,
    ip_address text,
    status text,
    processed text,
    username_user text,
    CONSTRAINT v_inf_pkey PRIMARY KEY (id)
);

-- Создание последовательности для v_read
CREATE SEQUENCE IF NOT EXISTS v_read_id_seq START 1;

-- Создание таблицы v_read
CREATE TABLE IF NOT EXISTS public.v_read (
    id integer NOT NULL DEFAULT nextval('v_read_id_seq'::regclass),
    request_id character varying(255) NOT NULL,
    status character varying(50) NOT NULL,
    result_message text NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT v_read_pkey PRIMARY KEY (id)
);

EOF

echo "Таблицы v_inf и v_read созданы"

echo "Установка завершена"

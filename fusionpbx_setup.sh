#!/bin/bash

# === 1. Установка FusionPBX ===
echo "Установка FusionPBX..."

wget -O - https://raw.githubusercontent.com/fusionpbx/fusionpbx-install.sh/master/debian/pre-install.sh | sh
cd /usr/src/fusionpbx-install.sh/debian && ./install.sh

# === 2. Сохранение данных установки ===
echo "Запоминаем данные установки FusionPBX..."

INSTALL_LOG=$(tail -n 30 /var/log/fusionpbx/install.log)
DOMAIN_NAME=$(echo "$INSTALL_LOG" | grep "domain name:" | awk '{print $NF}')
USERNAME=$(echo "$INSTALL_LOG" | grep "username:" | awk '{print $NF}')
PASSWORD=$(echo "$INSTALL_LOG" | grep "password:" | awk '{print $NF}')

# Запись в файл
echo "domain_name=$DOMAIN_NAME" > /root/datafusion.txt
echo "username=$USERNAME" >> /root/datafusion.txt
echo "password=$PASSWORD" >> /root/datafusion.txt
echo "Данные сохранены в /root/datafusion.txt"

# === 3. Извлечение пароля БД FusionPBX ===
DB_PASS=$(grep 'database.0.password' /etc/fusionpbx/config.conf | awk -F' = ' '{print $2}')
echo "Пароль БД FusionPBX: $DB_PASS"
echo "db_password=$DB_PASS" >> /root/datafusion.txt

# === 4. Запрос домена от пользователя ===
read -p "Напиши мне домен, который ты хочешь использовать (пример: pbx.domain.com): " DOMAIN_BOT
echo "domainbot=$DOMAIN_BOT" >> /root/datafusion.txt

# === 5. Установка MariaDB ===
echo "Устанавливаем MariaDB и создаём базу данных ipadd..."

apt update
apt install -y mariadb-server

# Запуск и безопасная настройка MariaDB
systemctl start mariadb
systemctl enable mariadb

mysql -uroot <<EOF
CREATE DATABASE ipadd;
CREATE USER 'root'@'localhost' IDENTIFIED BY '1Yb74rfBhrTwtJHh';
GRANT ALL PRIVILEGES ON ipadd.* TO 'root'@'localhost';
FLUSH PRIVILEGES;

USE ipadd;
CREATE TABLE ip_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45),
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    prefix VARCHAR(50),
    user VARCHAR(50),
    method VARCHAR(50)
);
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100),
    password VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
EOF

echo "MariaDB и база ipadd созданы."

# === 6. Замена eavesdrop.lua ===
echo "Заменяем eavesdrop.lua..."

wget -O /usr/share/freeswitch/scripts/eavesdrop.lua https://raw.githubusercontent.com/username/repo/main/eavesdrop.lua

# === 7. Установка файлов в /var/www/fusionpbx/form/ ===
echo "Клонирование и установка веб-файлов FusionPBX form..."

mkdir -p /var/www/fusionpbx/form/
wget -P /var/www/fusionpbx/form/ https://raw.githubusercontent.com/username/repo/main/IP.php
wget -P /var/www/fusionpbx/form/ https://raw.githubusercontent.com/username/repo/main/add.css
wget -P /var/www/fusionpbx/form/ https://raw.githubusercontent.com/username/repo/main/add.php
wget -P /var/www/fusionpbx/form/ https://raw.githubusercontent.com/username/repo/main/history.css
wget -P /var/www/fusionpbx/form/ https://raw.githubusercontent.com/username/repo/main/history.php
wget -P /var/www/fusionpbx/form/ https://raw.githubusercontent.com/username/repo/main/login.php
wget -P /var/www/fusionpbx/form/ https://raw.githubusercontent.com/username/repo/main/reset.php
wget -P /var/www/fusionpbx/form/ https://raw.githubusercontent.com/username/repo/main/styles.css

echo "Веб-файлы добавлены в /var/www/fusionpbx/form/."

# === 8. Подстановка пароля БД в скрипты ===
echo "Замена переменных {passfusion} и {domainbot} в Python-скриптах..."

find /root/bots/ -type f -name "*.py" -exec sed -i "s/{passfusion}/$DB_PASS/g" {} \;
find /root/bots/ -type f -name "*.py" -exec sed -i "s/{domainbot}/$DOMAIN_BOT/g" {} \;

echo "Установка завершена. FusionPBX настроен и готов к работе!"
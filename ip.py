import logging
import os
import pymysql
import subprocess
import bcrypt
from pyzabbix import ZabbixAPI
from telegram import Update, ReplyKeyboardRemove, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import Application, CommandHandler, CallbackQueryHandler, MessageHandler, filters, ConversationHandler, CallbackContext

TOKEN = '**'

DB_HOST = '127.0.0.1'
DB_USER = 'root'
DB_PASSWORD = '1Yb74rfBhrTwtJHh'
DB_NAME = 'ipadd'

ZABBIX_URL = 'http://**/zabbix'
ZABBIX_USER = 'Admin'
ZABBIX_PASSWORD = '**'
ZABBIX_HOST_GROUP_ID = '**'  
ZABBIX_TEMPLATE_ID = '**' 

LOGIN, PASSWORD, PREFIX, IP_ADDRESS = range(4)

logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)

logger = logging.getLogger(__name__)

def connect_to_db():
    return pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASSWORD, database=DB_NAME)

def verify_password(username, password):
    conn = connect_to_db()
    cursor = conn.cursor()
    sql = "SELECT password FROM users WHERE username = %s"
    cursor.execute(sql, (username,))
    result = cursor.fetchone()
    conn.close()
    if result and bcrypt.checkpw(password.encode('utf-8'), result[0].encode('utf-8')):
        return True
    return False

def delete_ip_from_iptables(ip_address):
    protocols = ["tcp", "udp", "all"]

    for protocol in protocols:
        command = ["sudo", "/usr/sbin/iptables", "-D", "INPUT", "-s", ip_address, "-j", "ACCEPT", "-p", protocol]
        result = subprocess.run(command, capture_output=True, text=True)
        if result.returncode == 0:
            print(f"IP {ip_address} успешно удален из iptables для протокола {protocol}.")
        else:
            if "No such file or directory" not in result.stderr:
                print(f"Ошибка при удалении IP {ip_address} для протокола {protocol}: {result.stderr}")
            else:
                print(f"IP {ip_address} не найден в iptables для протокола {protocol}, удаление не требуется.")

    subprocess.run("sudo /usr/sbin/iptables-save > /etc/iptables/rules.v4", shell=True, check=True)

    try:
        conn = connect_to_db()
        cursor = conn.cursor()
        sql = "DELETE FROM ip_attempts WHERE ip_address = %s"
        rows_affected = cursor.execute(sql, (ip_address,))
        conn.commit()
        conn.close()

        if rows_affected > 0:
            print(f"IP {ip_address} успешно удален из базы данных.")
        else:
            print(f"IP {ip_address} не найден в базе данных.")
    except Exception as e:
        print(f"Ошибка при удалении IP {ip_address} из базы данных: {str(e)}")


def ip_exists_in_iptables(ip_address):
    command = ["sudo", "/usr/sbin/iptables", "-L", "INPUT", "-v", "-n"]
    result = subprocess.run(command, capture_output=True, text=True)
    if ip_address in result.stdout:
        return True
    return False

def add_ip_to_iptables(ip_address):
    command = ["sudo", "/usr/sbin/iptables", "-A", "INPUT", "-s", ip_address, "-j", "ACCEPT"]
    subprocess.run(command, check=True)
    subprocess.run("sudo /usr/sbin/iptables-save > /etc/iptables/rules.v4", shell=True, check=True)

#def add_host_to_zabbix(client_name, ip_address):
 #   zapi = ZabbixAPI(ZABBIX_URL)
  #  zapi.login(ZABBIX_USER, ZABBIX_PASSWORD)
   # 
    #host_name = f"{client_name} - {ip_address}"
    #
    #try:
     #   zapi.host.create({
      #      'host': host_name,
       #     'interfaces': [{
        #        'type': 1,
         #       'main': 1,
          #      'useip': 1,
           #     'ip': ip_address,
            #    'dns': '',
             #   'port': '10050'
            #}],
            #'groups': [{'groupid': ZABBIX_HOST_GROUP_ID}],
            #'templates': [{'templateid': ZABBIX_TEMPLATE_ID}]
        #})
        #print(f"Узел {host_name} добавлен в Zabbix.")
    #except Exception as e:
     #   print(f"Ошибка при добавлении узла {host_name} в Zabbix: {e}")

async def start(update: Update, context: CallbackContext) -> int:
    if context.user_data.get('authorized'):
        await update.message.reply_text(
            "Ты уже авторизован! Введи префикс и IP через пробел, например:\n"
            "283 212.224.72.160"
        )
        return PREFIX
    else:
        await update.message.reply_text(
            "Привет! Введите свой логин: "
        )
        return LOGIN

async def login(update: Update, context: CallbackContext) -> int:
    context.user_data['username'] = update.message.text
    await update.message.reply_text("Теперь введите свой пароль:", reply_markup=ReplyKeyboardRemove())
    return PASSWORD

async def password(update: Update, context: CallbackContext) -> int:
    username = context.user_data['username']
    password = update.message.text

    if verify_password(username, password):
        context.user_data['authorized'] = True
        await update.message.reply_text(
            "Авторизация прошла успешно! Введите префикс и айпи через пробел, например:\n"
            "Префикс Айпи\n\n"
            "Или же если хотите удалить айпи, то напишите: 'Удалить (Айпи)'"
        )
        return PREFIX
    else:
        await update.message.reply_text("Неправильный логин или пароль. Попробуйте ещё раз\n Начинате с логина:")
        return LOGIN

async def delete_ip_command(update: Update, context: CallbackContext) -> int:
    text = update.message.text
    if not text.startswith("Удалить "):
        await update.message.reply_text("Неправильная команда. Используйте формат 'Удалить <IP>'.")
        return PREFIX

    ip_address = text.split(" ", 1)[1]

    conn = connect_to_db()
    cursor = conn.cursor()
    sql = "SELECT prefix FROM ip_attempts WHERE ip_address = %s"
    cursor.execute(sql, (ip_address,))
    result = cursor.fetchone()
    conn.close()

    if result:
        prefix = result[0]
        keyboard = [
            [
                InlineKeyboardButton("Да", callback_data=f"confirm_delete_{ip_address}"),
                InlineKeyboardButton("Нет", callback_data="cancel_delete")
            ]
        ]
        reply_markup = InlineKeyboardMarkup(keyboard)
        await update.message.reply_text(
            f"Вы хотите удалить {prefix} {ip_address}?",
            reply_markup=reply_markup
        )
    elif ip_exists_in_iptables(ip_address):

        keyboard = [
            [
                InlineKeyboardButton("Да", callback_data=f"confirm_delete_{ip_address}"),
                InlineKeyboardButton("Нет", callback_data="cancel_delete")
            ]
        ]
        reply_markup = InlineKeyboardMarkup(keyboard)
        await update.message.reply_text(
            f"Вы хотите удалить {ip_address} из iptables?",
            reply_markup=reply_markup
        )
    else:
        await update.message.reply_text(f"IP {ip_address} не найден в базе данных или в iptables.")

    return PREFIX

async def button(update: Update, context: CallbackContext) -> None:
    query = update.callback_query
    await query.answer()

    if query.data.startswith("confirm_delete_"):
        ip_address = query.data.split("_", 2)[2]
        try:
            conn = connect_to_db()
            cursor = conn.cursor()
            sql = "DELETE FROM ip_attempts WHERE ip_address = %s"
            cursor.execute(sql, (ip_address,))
            conn.commit()
            conn.close()

            if ip_exists_in_iptables(ip_address):
                delete_ip_from_iptables(ip_address)

            await query.edit_message_text(text=f"IP {ip_address} был успешно удален.")
        except Exception as e:
            await query.edit_message_text(text=f"Ошибка при удалении IP {ip_address}: {str(e)}")
    elif query.data == "cancel_delete":
        await query.edit_message_text(text="Удаление отменено.")

async def prefix(update: Update, context: CallbackContext) -> int:
    text = update.message.text
    lines = text.splitlines()

 
    if len(lines) == 1:

        parts = lines[0].split()
        if len(parts) == 2:
            context.user_data['prefix'] = parts[0]
            ip_address = parts[1]
            context.user_data['ip_addresses'] = [ip_address]
            return await process_multiple_ips(update, context)
        else:
            await update.message.reply_text("Неправильный формат. Используйте формат: 'Префикс IP' или 'Префикс и список IP'.")
            return PREFIX
    elif len(lines) > 1:

        context.user_data['prefix'] = lines[0]
        ip_addresses = lines[1:]
        context.user_data['ip_addresses'] = ip_addresses
        return await process_multiple_ips(update, context)
    else:
        await update.message.reply_text("Неправильный формат. Введите префикс и один IP или список IP на новых строках.")
        return PREFIX


async def process_multiple_ips(update: Update, context: CallbackContext) -> int:
    prefix = context.user_data['prefix']
    ip_addresses = context.user_data['ip_addresses']
    username = context.user_data['username']


    for ip_address in ip_addresses:
        ip_address = ip_address.strip()
        if not ip_address:
            continue 

        if ip_exists_in_iptables(ip_address):
            await update.message.reply_text(f"IP {ip_address} уже существует в правилах iptables.")
        else:
            try:
                add_ip_to_iptables(ip_address)

                conn = connect_to_db()
                with conn.cursor() as cursor:
                    sql = "INSERT INTO ip_attempts (ip_address, prefix, user, method) VALUES (%s, %s, %s, %s)"
                    cursor.execute(sql, (ip_address, prefix, username, 'Bot'))
                    conn.commit()

                await update.message.reply_text(f"`IP {ip_address} успешно добавлен` в iptables!", parse_mode='Markdown')
            except Exception as e:
                await update.message.reply_text(f"Произошла ошибка при добавлении IP {ip_address}: {str(e)}")

    await update.message.reply_text(f"Все IP для {prefix} обработаны. Введите следующий префикс и IP через пробел.")
    return PREFIX
async def ip_address(update: Update, context: CallbackContext) -> int:
    prefix = context.user_data['prefix']
    ip_address = context.user_data['ip_address']
    username = context.user_data['username']

    if not ip_address.strip():
        await update.message.reply_text("IP адрес не может быть пустым.:")
        return IP_ADDRESS

    if ip_exists_in_iptables(ip_address):
        await update.message.reply_text(f"IP {ip_address} уже существует в правилах iptables.")
    else:
        try:
            add_ip_to_iptables(ip_address)

            conn = connect_to_db()
            with conn.cursor() as cursor:
                sql = "INSERT INTO ip_attempts (ip_address, prefix, user, method) VALUES (%s, %s, %s, %s)"
                cursor.execute(sql, (ip_address, prefix, username, 'Bot'))
                conn.commit()

            await update.message.reply_text(f"`IP {ip_address} успешно добавлен` в iptables! Введите следующий префикс и IP через пробел.", parse_mode='Markdown')
        except Exception as e:
            await update.message.reply_text(f"Пиши Саше. Произошла ошибка при добавлении IP: {str(e)}")

    return PREFIX


def main():
    application = Application.builder().token(TOKEN).build()

    conv_handler = ConversationHandler(
        entry_points=[CommandHandler('start', start)],
        states={
            LOGIN: [MessageHandler(filters.TEXT & ~filters.COMMAND, login)],
            PASSWORD: [MessageHandler(filters.TEXT & ~filters.COMMAND, password)],
            PREFIX: [
               
                MessageHandler(filters.Regex(r'^Удалить\s+\d{1,3}(\.\d{1,3}){3}$'), delete_ip_command),
                MessageHandler(filters.TEXT & ~filters.COMMAND, prefix)
            ],
        },
        fallbacks=[],
    )

    application.add_handler(conv_handler)
    application.add_handler(CallbackQueryHandler(button))

    application.run_polling()

if __name__ == '__main__':
    main()



import logging
import pymysql
import bcrypt
import requests
import json
from telegram import Update, ReplyKeyboardRemove
from telegram.ext import Application, CommandHandler, MessageHandler, filters, ConversationHandler, CallbackContext

TOKEN = '**'

DB_HOST = '127.0.0.1'
DB_USER = 'root'
DB_PASSWORD = '1Yb74rfBhrTwtJHh'
DB_NAME = 'ipadd'

ZABBIX_URL = 'http://**/zabbix/api_jsonrpc.php'
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

def add_host_to_zabbix(client_name, ip_address):
    token = get_auth_token()
    headers = {'Content-Type': 'application/json'}
    data = {
        "jsonrpc": "2.0",
        "method": "host.create",
        "params": {
            'host': f"{client_name} - {ip_address}",
            'interfaces': [{
                'type': 1,
                'main': 1,
                'useip': 1,
                'ip': ip_address,
                'dns': '',
                'port': '10050'
            }],
            'groups': [{'groupid': ZABBIX_HOST_GROUP_ID}],
            'templates': [{'templateid': ZABBIX_TEMPLATE_ID}]
        },
        "auth": token,
        "id": 1
    }
    response = requests.post(ZABBIX_URL, headers=headers, data=json.dumps(data))
    result = response.json()
    if 'error' in result:
        logger.error(f"Ошибка при добавлении узла: {result['error']}")
        return False
    return True

def get_hosts_list():
    token = get_auth_token()
    headers = {'Content-Type': 'application/json'}
    data = {
        "jsonrpc": "2.0",
        "method": "host.get",
        "params": {
            "output": ["hostid", "name"]
        },
        "auth": token,
        "id": 1
    }
    response = requests.post(ZABBIX_URL, headers=headers, data=json.dumps(data))
    result = response.json()
    if 'error' in result:
        logger.error(f"Ошибка при получении списка узлов: {result['error']}")
        return []
    return result.get('result', [])

def get_auth_token():
    headers = {'Content-Type': 'application/json'}
    data = {
        "jsonrpc": "2.0",
        "method": "user.login",
        "params": {
            "user": ZABBIX_USER,
            "password": ZABBIX_PASSWORD
        },
        "id": 1
    }
    response = requests.post(ZABBIX_URL, headers=headers, data=json.dumps(data))
    result = response.json()
    return result['result']

def delete_host_from_zabbix(host_id):
    token = get_auth_token()
    headers = {'Content-Type': 'application/json'}
    data = {
        "jsonrpc": "2.0",
        "method": "host.delete",
        "params": [str(host_id)],
        "auth": token,
        "id": 1
    }
    response = requests.post(ZABBIX_URL, headers=headers, data=json.dumps(data))
    result = response.json()
    if 'error' in result:
        logger.error(f"Ошибка при удалении узла с ID {host_id}: {result['error']}")
        return False
    return True

async def start(update: Update, context: CallbackContext) -> int:
    if context.user_data.get('authorized'):
        await update.message.reply_text(
            "Ты уже авторизован! Введи команду:\n"
            "Добавить ИмяКлиента Айпи\n"
            "Список\n"
            "Удалить ID"
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
            "Авторизация прошла успешно! Введите команду:\n"
            "Добавить ИмяКлиента Айпи\n"
            "Список\n"
            "Удалить ID"
        )
        return PREFIX
    else:
        await update.message.reply_text("Неправильный логин или пароль. Попробуйте ещё раз\n Начинайте с логина:")
        return LOGIN

async def handle_text(update: Update, context: CallbackContext) -> None:
    text = update.message.text.strip().lower()

    if "список" in text:
        hosts = get_hosts_list()
        response = "\n".join(f"{host['hostid']}. {host['name']}" for host in hosts)
        await update.message.reply_text(f"Список узлов:\n{response}")

    elif text.startswith("удалить"):
        _, host_id = text.split(" ", 1)
        try:
            host_id = int(host_id)
        except ValueError:
            await update.message.reply_text("Неправильный формат ID. ID должен быть числом.")
            return

        result = delete_host_from_zabbix(host_id)
        if result:
            await update.message.reply_text(f"Узел с ID {host_id} успешно удален.")
        else:
            await update.message.reply_text(f"Ошибка при удалении узла с ID {host_id}. Проверьте логи для получения подробной информации.")

    elif text.startswith("добавить"):
        parts = text.split()
        if len(parts) == 3:
            client_name, ip_address = parts[1], parts[2]
            if add_host_to_zabbix(client_name, ip_address):
                await update.message.reply_text(f"Узел {client_name} с IP {ip_address} успешно добавлен на Zabbix.")
            else:
                await update.message.reply_text(f"Произошла ошибка при добавлении узла.")
        else:
            await update.message.reply_text("Неправильный формат. Используйте: Добавить ИмяКлиента Айпи.")

    else:
        await update.message.reply_text("Неправильная команда. Используйте:\nДобавить ИмяКлиента Айпи\nСписок\nУдалить ID")

def main():
    application = Application.builder().token(TOKEN).build()

    conv_handler = ConversationHandler(
        entry_points=[CommandHandler('start', start)],
        states={
            LOGIN: [MessageHandler(filters.TEXT & ~filters.COMMAND, login)],
            PASSWORD: [MessageHandler(filters.TEXT & ~filters.COMMAND, password)],
        },
        fallbacks=[],
    )

    application.add_handler(conv_handler)
    application.add_handler(MessageHandler(filters.TEXT & ~filters.COMMAND, handle_text))

    application.run_polling()

if __name__ == '__main__':
    main()

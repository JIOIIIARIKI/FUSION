import json
import requests
import psycopg2
import random
import string
import logging
import time
from telegram.ext import Application
from telegram import Bot

TOKEN = '7782817983:AAGJ0KZihTFbbBwIiwB3j1TqyEpzkjdkyVc'

api_key = 'kLgTqH3YjWJLBJPcLX3u9C5SBeegA8V6'
CHANNEL_ID = '-1002450667973'

logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)


DB_PARAMS = {
    'dbname': 'fusionpbx',
    'user': 'fusionpbx',
    'password': 'UMPM1GkzQa2UOH3Qvp2nLtkM',
    'host': '127.0.0.1',
    'port': '5432',
    'sslmode': 'prefer'
}

def connect_to_db():
    return psycopg2.connect(**DB_PARAMS)

def get_pending_requests():
    conn = connect_to_db()
    with conn:
        with conn.cursor() as cursor:
            cursor.execute("SELECT id, username, password, group_name, domain, internal_number, context_value, quantity, dialplan_name, user_prefix, ip_address FROM v_inf WHERE status = '-'")
            return cursor.fetchall()

def update_request_status(request_id, status):
    conn = connect_to_db()
    with conn:
        with conn.cursor() as cursor:
            cursor.execute("UPDATE v_inf SET status = %s WHERE id = %s", (status, request_id))

def insert_result_into_v_read(request_id, result_message):
    conn = connect_to_db()
    with conn:
        with conn.cursor() as cursor:
            cursor.execute(
                "INSERT INTO v_read (request_id, status, result_message) VALUES (%s, %s, %s)",
                (request_id, '-', result_message)
            )

def verify_api_key(api_key):
    conn = connect_to_db()
    with conn:
        with conn.cursor() as cursor:
            cursor.execute("SELECT username FROM v_users WHERE api_key = %s", (api_key,))
            result = cursor.fetchone()
            return result[0] if result else None

def get_group_uuid_from_db(group_name):
    conn = connect_to_db()
    with conn:
        with conn.cursor() as cursor:
            cursor.execute("SELECT group_uuid FROM v_groups WHERE group_name = %s", (group_name,))
            result = cursor.fetchone()
            return result[0] if result else None

def get_domain_uuid_from_db(domain_name):
    conn = connect_to_db()
    with conn:
        with conn.cursor() as cursor:
            cursor.execute("SELECT domain_uuid FROM v_domains WHERE domain_name = %s", (domain_name,))
            result = cursor.fetchone()
            return result[0] if result else None

def create_domain(domain_name, api_key):
    try:
        response = requests.post(
            f'https://123/app/vsa/domain_edit.php?key=kLgTqH3YjWJLBJPcLX3u9C5SBeegA8V6',
            data={
                'domain_name': domain_name,
                'domain_description': domain_name,
            }
        )
        logging.info(f"domain_edit.php Response: {response.status_code} - {response.text}")
        if response.status_code == 200:
            return get_domain_uuid_from_db(domain_name)
        else:
            logging.error(f"Failed to create domain. Status code: {response.status_code}, Response: {response.text}")
    except requests.RequestException as e:
        logging.error(f"Ошибка при отправке запроса на domain_edit.php: {e}")
    return None

def get_user_uuid_from_db(username, domain_uuid):
    conn = connect_to_db()
    with conn:
        with conn.cursor() as cursor:
            cursor.execute("SELECT user_uuid FROM v_users WHERE username = %s AND domain_uuid = %s", (username, domain_uuid))
            result = cursor.fetchone()
            return result[0] if result else None

def get_sip_account_data(domain_uuid, extension):
    conn = connect_to_db()
    with conn:
        with conn.cursor() as cursor:
            cursor.execute("SELECT password FROM v_extensions WHERE domain_uuid = %s AND extension = %s", (domain_uuid, extension))
            result = cursor.fetchone()
            return result if result else None

def generate_random_password(length=20):
    characters = string.ascii_letters + string.digits
    return ''.join(random.choice(characters) for _ in range(length))

def handle_form_data(data):
    api_key = 'kLgTqH3YjWJLBJPcLX3u9C5SBeegA8V6' 

    group_uuid = get_group_uuid_from_db(data['group_name'])
    domain_uuid = get_domain_uuid_from_db(data['domain'])

    if not group_uuid:
        return f"Ошибка: не удалось найти группу {data['group_name']}."

    if not domain_uuid:
        domain_uuid = create_domain(data['domain'], api_key)
        if not domain_uuid:
            return f"Ошибка: не удалось создать домен {data['domain']}."

    try:
        user_edit_response = requests.post(
            f'https://123/app/vsa/user_edit.php?key=kLgTqH3YjWJLBJPcLX3u9C5SBeegA8V6',
            data={
                'domain_uuid': domain_uuid,
                'username': data['username'],
                'password': data['password'],
                'password_confirm': data['password'],
                'group_uuid_name': f"{group_uuid}|{data['group_name']}"
            }
        )
        logging.info(f"user_edit.php Response: {user_edit_response.status_code} - {user_edit_response.text}")

        if user_edit_response.status_code == 200:
            user_uuid = get_user_uuid_from_db(data['username'], domain_uuid)

            if not user_uuid:
                return "Ошибка: не удалось найти пользователя после его создания."
        else:
            return "Ошибка при создании пользователя."
    except requests.RequestException as e:
        logging.error(f"Ошибка при отправке запроса на user_edit.php: {e}")
        return "Ошибка при отправке данных на user_edit.php."

    try:
        extensions_edit_response = requests.post(
            f'https://123/app/vsa/extension_edit.php?key={api_key}',
            data={
                'domain_uuid': domain_uuid,
                'extension': data['internal_number'],
                'user_context': data['context_value'],
                'range': data['quantity'],
                'extension_users[0][user_uuid]': user_uuid
            }
        )
        logging.info(f"extensions_edit.php Response: {extensions_edit_response.status_code} - {extensions_edit_response.text}")
    except requests.RequestException as e:
        logging.error(f"Ошибка при отправке запроса на extensions_edit.php: {e}")
        return "Ошибка при отправке данных на extensions_edit.php."

    try:
        dialplan_outbound_add_response = requests.post(
            f'https://123/app/vsa/dialplan_outbound_add.php?key={api_key}',
            data={
                'domain_uuid': domain_uuid,
                'dialplan_expression': '^\+?(\d+)$',
                'context': data['context_value'],
                'name': data['dialplan_name'],
                'user_prefix': data['user_prefix'],
                'gateway': 'd7ca4002-94a6-498b-8c89-c2892ed6e1c3'
            }
        )
        logging.info(f"dialplan_outbound_add.php Response: {dialplan_outbound_add_response.status_code} - {dialplan_outbound_add_response.text}")

        if int(data['quantity']) > 1:
            base_extension = data["internal_number"][:-2]
            dialplan_expression1 = r'^\*46({0}\d{{2}})$'.format(base_extension)
            dialplan_expression2 = r'^\*45({0}\d{{2}})$'.format(base_extension)
            requests.post(
                f'https://123/app/vsa/dialplan_outbound_add2.php?key={api_key}',
                data={
                    'domain_uuid': domain_uuid,
                    'dialplan_expression': dialplan_expression2,
                    'context': data['context_value'],
                    'name': f'{data["dialplan_name"]}-Прослушка',
                }
            )
            requests.post(
                f'https://123/app/vsa/dialplan_outbound_add3.php?key={api_key}',
                data={
                    'domain_uuid': domain_uuid,
                    'dialplan_expression': dialplan_expression1,
                    'context': data['context_value'],
                    'name': f'{data["dialplan_name"]}-Подсказка',
                }
            )

    except requests.RequestException as e:
        logging.error(f"Ошибка при отправке запроса на dialplan_outbound_add.php: {e}")
        return "Ошибка при отправке данных на dialplan_outbound_add.php."

    result_message = f"Данные обработаны:\nID заявки: {data['id']}\n\n"
    if int(data['quantity']) > 1:
        result_message += f"*45+номер учётной записи (*45{data['internal_number']}) - прослушка\n*46+номер учётной записи (*46{data['internal_number']}) - подсказка\n"
        result_message += f"\nЛК для статистики и записей:\nСсылка: https://{data['domain']}\nLog: `{data['username']}`\nPass: `{data['password']}`\n\n"

    for i in range(int(data['quantity'])):
        extension = str(int(data['internal_number']) + i)
        sip_data = get_sip_account_data(domain_uuid, extension)
        if sip_data:
           result_message += f"**Log:** `{extension}`\n**Pass:** `{sip_data[0]}`\n**Domain:** `{data['domain']}`\n\n"
 
    random_password = generate_random_password()
    result_message += f"Лк для баланса:\nhttps://user.voiceapp.mobi/\nLog: `{data['username']}`\nPass: `{random_password}`\n\n"
    result_message += "Пополнения вносите через ЛК: Payments - New Payment (если не отображает кнопка New Payment нужно очистить кеш браузера данной страницы Ctrl+F5) - Появится изображение с суммой 5,1 (отображается как установленный минимальный порог для платежа) и информацией нужного кошелька, на него сбрасываете средства от 5,1 - оплата - дождитесь в течении 3х минут проверки и зачисления на баланс средств!)"

    return result_message

async def send_telegram_message(bot, chat_id, message):
    await bot.send_message(chat_id=chat_id, text=message, parse_mode='Markdown')

async def main():
    bot = Bot(token=TOKEN)
    application = Application.builder().token(TOKEN).build()
    while True:
        try:
            requests_data = get_pending_requests()
            for request in requests_data:
                (request_id, username, password, group_name, domain, internal_number, context_value, quantity, dialplan_name, user_prefix, ip_address) = request
                form_data = {
                    'id': request_id,
                    'username': username,
                    'password': password,
                    'group_name': group_name,
                    'domain': domain,
                    'internal_number': internal_number,
                    'context_value': context_value,
                    'quantity': quantity,
                    'dialplan_name': dialplan_name,
                    'user_prefix': user_prefix,
                    'ip_address': ip_address
                }
                result_message = handle_form_data(form_data)
                await send_telegram_message(bot, CHANNEL_ID, result_message)
                insert_result_into_v_read(request_id, result_message)
                update_request_status(request_id, '+')
            time.sleep(60) 
        except Exception as e:
            logging.error(f"Произошла ошибка: {e}")
            time.sleep(60) 

if __name__ == '__main__':
    import asyncio
    asyncio.run(main())

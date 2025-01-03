import random
import string
import psycopg2
import logging

logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

DB_PARAMS = {
    'dbname': 'fusionpbx',
    'user': 'fusionpbx',
    'password': '{passfusion}',
    'host': '127.0.0.1',
    'port': '5432',
    'sslmode': 'prefer'
}

def get_db_connection():
    try:
        conn = psycopg2.connect(**DB_PARAMS)
        return conn
    except Exception as e:
        logging.error(f"Error connecting to database: {e}")
        raise

def get_db_cursor():
    conn = get_db_connection()
    return conn, conn.cursor()

def generate_password():
    lower = string.ascii_lowercase
    upper = string.ascii_uppercase
    digits = string.digits
    symbols = "!@#$%^&*()"
    all_characters = lower + upper + digits + symbols
    password = [
        random.choice(lower),
        random.choice(upper),
        random.choice(digits),
        random.choice(symbols)
    ]
    password += random.choices(all_characters, k=8)
    random.shuffle(password)
    return ''.join(password)

def generate_unique_id():
    return ''.join(random.choice(string.ascii_lowercase + string.digits) for _ in range(8))

def sanitize_company_name(name):
    return name.strip()

def get_next_client_prefix():
    conn, cur = get_db_cursor()
    try:
        cur.execute("SELECT dialplan_detail_data FROM v_dialplan_details WHERE dialplan_detail_data ~ '^provider_prefix=[0-9]{3}$'")
        rows = cur.fetchall()
        if rows:
            prefixes = [int(row[0].split('=')[1]) for row in rows]
            client_prefix = max(prefixes)
            return client_prefix + 1
        else:
            return 209
    except Exception as e:
        logging.error(f"Error getting next client prefix: {e}")
        return 209
    finally:
        cur.close()
        conn.close()

def get_next_context_value(company, client_prefix):
    return f"{company}{client_prefix:03d}"

def get_next_multysip_extension():
    conn, cur = get_db_cursor()
    try:
        cur.execute("SELECT MAX(CAST(extension AS INTEGER)) FROM v_extensions WHERE domain_uuid='ced52586-be8a-4386-9eaa-4fe7204340b1'")
        max_extension = cur.fetchone()[0]
        if max_extension:
            return str(max_extension + 1)
        else:
            return '100001'
    except Exception as e:
        logging.error(f"Error getting next multysip extension: {e}")
        return '100001'
    finally:
        cur.close()
        conn.close()

def insert_client_data(unique_id, username, password, group_name, domain, internal_number, context_value, sip_quantity, dialplan_name, client_prefix, ip_address, status, username_user):
    conn, cur = get_db_cursor()
    try:
        processed = '?'
        cur.execute('''
            INSERT INTO v_inf (id, username, password, group_name, domain, internal_number, context_value,
            quantity, dialplan_name, user_prefix, ip_address, status, processed, username_user)
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            ''',
            (unique_id, username, password, group_name, domain, internal_number, context_value, sip_quantity,
             dialplan_name, client_prefix, ip_address, status, processed, username_user))
        conn.commit()
    except Exception as e:
        logging.error(f"Error inserting client data: {e}")
        conn.rollback()
    finally:
        cur.close()
        conn.close()

def find_form_by_unique_id(unique_id):
    conn, cur = get_db_cursor()
    try:
        cur.execute('SELECT * FROM v_inf WHERE id = %s', (unique_id,))
        row = cur.fetchone()
        if row:
            form = (
                f"ID заявки: {row[0]}\n"
                f"Имя пользователя: {row[1]}\n"
                f"Пароль нового пользователя: {row[2]}\n"
                f"Группа: {row[3]}\n"
                f"Домен: {row[4]}\n"
                f"Внутренний номер: {row[5]}\n"
                f"Контекст: {row[6]}\n"
                f"Количество: {row[7]}\n"
                f"Название диалплана: {row[8]}\n"
                f"Префикс клиента: {row[9]}\n"
                f"Айпи клиента: {row[10]}\n"
                f"Создатель заявки: @{row[13]}\n" 
            )
            return form
        return None
    except Exception as e:
        logging.error(f"Error finding form by unique ID: {e}")
        return None
    finally:
        cur.close()
        conn.close()

def update_status(unique_id, status, processed, message=None):
    conn, cur = get_db_cursor()
    try:
        cur.execute('UPDATE v_inf SET status = %s, processed = %s WHERE id = %s', (status, processed, unique_id))
        conn.commit()

        if message:
            cur.execute('INSERT INTO v_read (request_id, result_message, status) VALUES (%s, %s, %s)', 
                        (unique_id, message, '-'))
            conn.commit()

    except Exception as e:
        logging.error(f"Error updating status: {e}")
        conn.rollback()
    finally:
        cur.close()
        conn.close()

def check_v_read():
    conn, cur = get_db_cursor()
    try:
        cur.execute('SELECT request_id, result_message FROM v_read WHERE status = %s', ('-',))
        rows = cur.fetchall()
        
        for request_id, _ in rows:
            cur.execute('UPDATE v_read SET status = %s WHERE request_id = %s', ('обработан', request_id))
        
        conn.commit()
        return rows

    except Exception as e:
        logging.error(f"Error checking v_read table: {e}")
        return []
    finally:
        cur.close()
        conn.close()

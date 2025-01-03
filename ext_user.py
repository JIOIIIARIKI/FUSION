import random
import string
import uuid
from datetime import datetime
import psycopg2
import pytz

DB_PARAMS = {
    'dbname': 'fusionpbx',
    'user': 'fusionpbx',
    'password': '{passfusion}',
    'host': '127.0.0.1',
    'port': '5432',
    'sslmode': 'prefer'
}

conn = psycopg2.connect(**DB_PARAMS)
cur = conn.cursor()

cur.execute('''CREATE TABLE IF NOT EXISTS v_extuser
               (request_id TEXT, user_uuid TEXT, extension_uuid TEXT, domain TEXT, status TEXT)''')
conn.commit()

cur.execute('''CREATE TABLE IF NOT EXISTS v_extension_users
               (extension_user_uuid UUID, domain_uuid TEXT, extension_uuid TEXT, user_uuid TEXT, 
                insert_date TIMESTAMP WITH TIME ZONE, insert_user TEXT)''')
conn.commit()

def generate_unique_id():
    return ''.join(random.choice(string.ascii_lowercase + string.digits) for _ in range(8))

def get_domains():
    cur.execute("SELECT domain_name, domain_uuid FROM v_domains")
    return sorted(cur.fetchall(), key=lambda x: x[0])

def get_users(domain_uuid):
    cur.execute("SELECT username, user_uuid FROM v_users WHERE domain_uuid = %s", (domain_uuid,))
    return sorted(cur.fetchall(), key=lambda x: x[0])

def get_extensions(domain_uuid):
    cur.execute("SELECT extension, extension_uuid FROM v_extensions WHERE domain_uuid = %s", (domain_uuid,))
    return sorted(cur.fetchall(), key=lambda x: x[0])

def insert_data_to_db(unique_id, domain_uuid, selected_users, selected_extensions):
    status = '-'
    for user_uuid in selected_users:
        for extension_uuid in selected_extensions:
            cur.execute('''INSERT INTO v_extuser (request_id, user_uuid, extension_uuid, domain, status)
                           VALUES (%s, %s, %s, %s, %s)''',
                        (unique_id, user_uuid, extension_uuid, domain_uuid, status))

            extension_user_uuid = str(uuid.uuid4())
            insert_time = datetime.now(pytz.utc) 
            cur.execute('''INSERT INTO v_extension_users (extension_user_uuid, domain_uuid, extension_uuid, user_uuid, 
                            insert_date, insert_user)
                           VALUES (%s, %s, %s, %s, %s, %s)''',
                        (extension_user_uuid, domain_uuid, extension_uuid, user_uuid, insert_time, '44fd7eb3-f830-4145-afeb-e46d278adff3'))

    conn.commit()

def get_username(user_uuid):
    cur.execute('''SELECT username FROM v_users WHERE user_uuid = %s''', (user_uuid,))
    return cur.fetchone()[0]

def get_extension(extension_uuid):
    cur.execute('''SELECT extension FROM v_extensions WHERE extension_uuid = %s''', (extension_uuid,))
    return cur.fetchone()[0]

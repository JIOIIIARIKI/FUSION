import time
import pymysql
import psycopg2
import subprocess

DB_PARAMS_FUSIONPBX = {
    'dbname': 'fusionpbx',
    'user': 'fusionpbx',
    'password': '{passfusion}',
    'host': '127.0.0.1',
    'port': '5432',
    'sslmode': 'prefer'
}

DB_PARAMS_IPADD = {
    'host': '127.0.0.1',
    'user': 'root',
    'password': '1Yb74rfBhrTwtJHh',
    'database': 'ipadd'
}

def connect_to_fusionpbx():
    return psycopg2.connect(**DB_PARAMS_FUSIONPBX)

def connect_to_ipadd():
    return pymysql.connect(**DB_PARAMS_IPADD)

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

def save_ip_attempt(ip_address, username):
    conn = connect_to_ipadd()
    cursor = conn.cursor()
    sql = "INSERT INTO ip_attempts (ip_address, prefix, user, method) VALUES (%s, %s, %s, %s)"
    cursor.execute(sql, (ip_address, username, 'API', 'API'))
    conn.commit()
    conn.close()

def check_new_data():
    conn = connect_to_fusionpbx()
    cursor = conn.cursor()
    cursor.execute("SELECT username, ip_address FROM v_inf WHERE processed = 'f'")
    new_data = cursor.fetchall()
    conn.close()
    return new_data

def mark_as_processed(username, ip_address):
    conn = connect_to_fusionpbx()
    cursor = conn.cursor()
    cursor.execute("UPDATE v_inf SET processed = 't' WHERE username = %s AND ip_address = %s", (username, ip_address))
    conn.commit()
    conn.close()

def main():
    while True:
        print("Проверка новых данных...")
        new_records = check_new_data()
        
        for username, ip_address in new_records:
            print(f"Обрабатывается: {username} - {ip_address}")
            
            if not ip_exists_in_iptables(ip_address):
                try:
                    add_ip_to_iptables(ip_address)
                    print(f"IP {ip_address} успешно добавлен в iptables.")
                    
                    save_ip_attempt(ip_address, username)
                    print(f"IP {ip_address} успешно сохранен в базе данных ip_attempts.")
                    
                    mark_as_processed(username, ip_address)
                    print(f"Запись {username} - {ip_address} отмечена как обработанная.")
                except Exception as e:
                    print(f"Ошибка при обработке IP {ip_address}: {str(e)}")
            else:
                print(f"IP {ip_address} уже существует в iptables, пропуск.")
        
        time.sleep(30)

if __name__ == "__main__":
    main()

from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import Application, CommandHandler, CallbackQueryHandler, MessageHandler, filters, CallbackContext
import logging
import subprocess
import asyncio


TOKEN = '**'


logging.basicConfig(
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    level=logging.INFO
)

logger = logging.getLogger(__name__)


ignored_ips = set()


def get_current_ips_in_chain(chain):
    command = ["sudo", "/usr/sbin/iptables", "-L", chain, "-v", "-n"]
    result = subprocess.run(command, capture_output=True, text=True, encoding='utf-8')
    ips = []
    
    for line in result.stdout.splitlines():
        parts = line.split()
        if "DROP" in parts:
            try:
                source_ip = parts[7] 
                if validate_ip(source_ip):
                    logger.info(f"Найден IP: {source_ip} в цепочке {chain}")
                    ips.append(source_ip)
                else:
                    logger.warning(f"Невалидный IP: {source_ip} в строке: {line}")
            except (IndexError, ValueError):
                continue
    return set(ips)

def validate_ip(ip_address):
    if ip_address == "all":
        return False
    parts = ip_address.split('.')
    if len(parts) == 4 and all(part.isdigit() and 0 <= int(part) <= 255 for part in parts):
        return True
    return False

def delete_ip_from_chain(ip_address, chain):
    command = ["sudo", "/usr/sbin/iptables", "-D", chain, "-s", ip_address, "-j", "DROP"]
    result = subprocess.run(command, capture_output=True, text=True)
    return result.returncode == 0

async def send_alert(context: CallbackContext, ip_address: str, chain: str, chat_id: int):
    keyboard = [
        [
            InlineKeyboardButton("Игнорировать", callback_data=f"ignore_{ip_address}"),
            InlineKeyboardButton("Разблокировать", callback_data=f"unblock_{ip_address}_{chain}")
        ]
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)
    try:
        logger.info(f"Отправка уведомления о новом IP {ip_address} в цепочке {chain}")
        await context.bot.send_message(chat_id=chat_id, text=f"Новое заблокированное IP {ip_address} в цепочке {chain}", reply_markup=reply_markup)
    except Exception as e:
        logger.error(f"Ошибка отправки сообщения: {e}")

async def monitor_iptables_chains(context: CallbackContext):
    chat_id = context.job.data['chat_id']
    previous_sip_auth_ip = set()
    previous_sip_auth_fail = set()

    while True:
        current_sip_auth_ip = get_current_ips_in_chain("sip-auth-ip")
        new_ips_in_sip_auth_ip = current_sip_auth_ip - previous_sip_auth_ip
        for ip in new_ips_in_sip_auth_ip:
            if ip not in ignored_ips:
                await send_alert(context, ip, "sip-auth-ip", chat_id)

        current_sip_auth_fail = get_current_ips_in_chain("sip-auth-fail")
        new_ips_in_sip_auth_fail = current_sip_auth_fail - previous_sip_auth_fail
        for ip in new_ips_in_sip_auth_fail:
            if ip not in ignored_ips:
                await send_alert(context, ip, "sip-auth-fail", chat_id)

        previous_sip_auth_ip = current_sip_auth_ip
        previous_sip_auth_fail = current_sip_auth_fail

        await asyncio.sleep(60)

async def search_ip(update: Update, context: CallbackContext):
    text = update.message.text
    if not text.startswith("Поиск "):
        await update.message.reply_text("Используйте команду в формате 'Поиск <IP>'.")
        return

    ip_address = text.split(" ", 1)[1]

    found_in_chain = None

    if ip_address in get_current_ips_in_chain("sip-auth-ip"):
        found_in_chain = "sip-auth-ip"
    elif ip_address in get_current_ips_in_chain("sip-auth-fail"):
        found_in_chain = "sip-auth-fail"

    if found_in_chain:
        keyboard = [
            [
                InlineKeyboardButton("Игнорировать", callback_data=f"ignore_{ip_address}"),
                InlineKeyboardButton("Разблокировать", callback_data=f"unblock_{ip_address}_{found_in_chain}")
            ]
        ]
        reply_markup = InlineKeyboardMarkup(keyboard)
        await update.message.reply_text(f"IP {ip_address} найден в цепочке {found_in_chain}.", reply_markup=reply_markup)
    else:
        await update.message.reply_text(f"IP {ip_address} не найден в цепочках.")

async def button(update: Update, context: CallbackContext) -> None:
    query = update.callback_query
    await query.answer()

    if query.data.startswith("ignore_"):
        ip_address = query.data.split("_", 1)[1]
        ignored_ips.add(ip_address)
        await query.edit_message_text(text=f"IP {ip_address} будет игнорироваться.")

    elif query.data.startswith("unblock_"):
        parts = query.data.split("_", 2)
        ip_address = parts[1]
        chain = parts[2]
        if delete_ip_from_chain(ip_address, chain):
            await query.edit_message_text(text=f"IP {ip_address} успешно удален из цепочки {chain}.")
        else:
            await query.edit_message_text(text=f"Ошибка при удалении IP {ip_address} из цепочки {chain}.")

async def start(update: Update, context: CallbackContext):
    chat_id = update.effective_chat.id
    await update.message.reply_text("Бот запущен. Начинаю мониторинг цепочек sip-auth-ip и sip-auth-fail.")
    
    context.job_queue.run_repeating(monitor_iptables_chains, interval=60, first=0, data={'chat_id': chat_id})

def main():
    application = Application.builder().token(TOKEN).build()

    application.add_handler(CommandHandler('start', start))
    application.add_handler(CallbackQueryHandler(button))
    application.add_handler(MessageHandler(filters.Regex(r'^Поиск\s+\d{1,3}(\.\d{1,3}){3}$'), search_ip))
    
    application.run_polling()

if __name__ == '__main__':
    main()

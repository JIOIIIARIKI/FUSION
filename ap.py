import logging
import os
import re
import telegram
import asyncio
from datetime import datetime, timedelta
from telegram import Update, InlineKeyboardButton, InlineKeyboardMarkup
from telegram.ext import Application, CommandHandler, MessageHandler, filters, ConversationHandler, CallbackQueryHandler, CallbackContext
from ext_user import generate_unique_id as generate_unique_id_ext, get_domains, get_users, get_extensions, insert_data_to_db, get_username, get_extension
from add_client import generate_password, generate_unique_id as generate_unique_id_add, sanitize_company_name, get_next_client_prefix, get_next_context_value, get_next_multysip_extension, insert_client_data, find_form_by_unique_id, update_status, check_v_read


TOKEN = '**'

BASE_DIR = '/var/lib/freeswitch/recordings/'

NOTIFICATION_CHAT_ID = -1002163643136

START, PROCESS_SELECTION, COMPANY, SIP_TYPE, SIP_QUANTITY, IP_ADDRESS, ADMIN, ADMIN_ID, RECORD, SELECT_DOMAIN, SELECT_USERS, SELECT_EXTENSIONS, CONFIRM = range(13)

client_data = {}

logging.basicConfig(
    format='%z(asctime)s - %(name)s - %(levelname)s',
    level=logging.INFO
)

logger = logging.getLogger(__name__)

async def cancel(update: Update, context: CallbackContext) -> int:
    query = update.callback_query
    await query.answer()

    await query.edit_message_text(
        "Действие отменено. Пожалуйста, введите одну из команд:\n\n"
        "/add_client - добавить нового клиента\n"
        "/assign_extensions - добавить пользователя в сип.\n"
        "/record - найти запись по номеру.",
    )

    return START
async def start_add_client(update: Update, context: CallbackContext) -> int:
    unique_id = generate_unique_id_add()
    context.user_data['unique_id'] = unique_id
    
    if update.callback_query:
        chat_id = update.callback_query.message.chat_id
        message = update.callback_query.message
    else:
        chat_id = update.message.chat_id
        message = update.message
    
    client_data[chat_id] = {'unique_id': unique_id}
    
    await message.reply_text(
        f"Как называется компания клиента, которого вы хотите добавить? (ID заявки: {unique_id})",
            reply_markup=InlineKeyboardMarkup([
            [InlineKeyboardButton("Отменить", callback_data='cancel')]
        ])
    )
    return COMPANY

async def start_assign_extension(update: Update, context: CallbackContext) -> int:
    unique_id = generate_unique_id_ext()
    context.user_data['unique_id'] = unique_id
    
    if update.callback_query:
        chat_id = update.callback_query.message.chat_id
        message = update.callback_query.message
    else:
        chat_id = update.message.chat_id
        message = update.message
    
    client_data[chat_id] = {
        'unique_id': unique_id,
        'selected_users': [],
        'selected_extensions': []
    }

    domains = get_domains()
    buttons = [
        [InlineKeyboardButton(domain_name, callback_data=f"domain_{domain_uuid}")]
        for domain_name, domain_uuid in domains
        ]
    buttons.append([InlineKeyboardButton("Отменить", callback_data='cancel')])
    
    await message.reply_text(
        f"Выберите домен для клиента (ID заявки: {unique_id}):",
        reply_markup=InlineKeyboardMarkup(buttons)
    )
    return SELECT_DOMAIN

async def start_record(update: Update, context: CallbackContext):
    await update.message.reply_text( 
        f'Добро пожаловать! Пожалуйста, введите номер телефона.\n Можете ввести несколько номеров, для этого введите их в таком формате:\n Номер\n Номер.',
            reply_markup=InlineKeyboardMarkup([
                [InlineKeyboardButton("Отмена", callback_data='cancel')]
            ])
        )
    return RECORD
COMMANDS = {
    "/add_client - добавить нового клиента": start_add_client,
    "/assign_extensions - добавить пользователя в сип": start_assign_extension,
    "/record - найти запись по номеру": start_record,
}
#    "отменить": cancel  # предположим, есть функция remove_client
#    "обновить данные клиента": update_client,  # предположим, есть функция update_client
#    "просмотреть информацию": view_info  # предположим, есть функция view_info

async def start(update: Update, context: CallbackContext) -> int:
    logger.info("Запуск start()")
    await update.message.reply_text(
        "Пожалуйста, введите одну из команд:\n\n" +
        "\n".join([f"{cmd}" for cmd in COMMANDS.keys()]),
    )
    return PROCESS_SELECTION

async def handle_text_message(update: Update, context: CallbackContext) -> int:
    text = update.message.text.lower()

    for command, handler_function in COMMANDS.items():
        if command == text:
            return await handler_function(update, context)
    
    await update.message.reply_text("Неизвестная команда. Пожалуйста, выберите действие из предложенных.")
    return PROCESS_SELECTION

#==========================================================================================================================================
#=============================================================================(/ASSIGN_EXTENSION)==========================================
#==========================================================================================================================================
async def select_domain(update: Update, context: CallbackContext) -> int:
    try:
        query = update.callback_query
        await query.answer()

        domain_uuid = query.data.split('_')[1]
        chat_id = query.message.chat_id
        logging.info(f"Selected domain: {domain_uuid} for chat {chat_id}")

        client_data[chat_id]['domain_uuid'] = domain_uuid

        users = get_users(domain_uuid)
        logging.info(f"Users for domain {domain_uuid}: {users}")

        if not users:
            await query.edit_message_text(
                "В выбранном домене нет пользователей.",
                reply_markup=InlineKeyboardMarkup([
                    [InlineKeyboardButton("Отменить", callback_data='cancel')]
                ])
            )
            return SELECT_USERS

        client_data[chat_id]['users'] = users
        client_data[chat_id]['selected_users'] = []

        buttons = [
            [InlineKeyboardButton(username, callback_data=f"user_{user_uuid}")]
            for username, user_uuid in users
        ]
        buttons.append([InlineKeyboardButton("Готово", callback_data='done_selecting_users')])
        buttons.append([InlineKeyboardButton("Отменить", callback_data='cancel')])

        logging.info(f"Sending user selection message to chat {chat_id}")

        await query.edit_message_text(
            "Выберите пользователей (можно выбрать несколько, затем нажмите 'Готово'):",
            reply_markup=InlineKeyboardMarkup(buttons)
        )

        return SELECT_USERS

    except Exception as e:
        logging.error(f"An error occurred: {str(e)}")
        await query.edit_message_text(
            "Произошла ошибка. Попробуйте ещё раз.",
            reply_markup=InlineKeyboardMarkup([
                [InlineKeyboardButton("Отменить", callback_data='cancel')]
            ])
        )
        return ConversationHandler.END

async def select_users(update: Update, context: CallbackContext) -> int:
    query = update.callback_query
    await query.answer()
    chat_id = query.message.chat_id
    user_uuid = query.data.split('_')[1]

    if query.data == 'done_selecting_users':
        domain_uuid = client_data[chat_id]['domain_uuid']
        extensions = get_extensions(domain_uuid)
        buttons = [
            [InlineKeyboardButton(extension, callback_data=f"extension_{extension_uuid}")]
            for extension, extension_uuid in extensions
        ]
        buttons.append([InlineKeyboardButton("Готово", callback_data='done_selecting_extensions')])
        buttons.append([InlineKeyboardButton("Отменить", callback_data='cancel')])

        await query.edit_message_text(
            "Выберите внутренние номера (можно выбрать несколько, затем нажмите 'Готово'):",
            reply_markup=InlineKeyboardMarkup(buttons)
        )
        return SELECT_EXTENSIONS
    else:
        if user_uuid in client_data[chat_id]['selected_users']:
            client_data[chat_id]['selected_users'].remove(user_uuid)
        else:
            client_data[chat_id]['selected_users'].append(user_uuid)

        users = client_data[chat_id]['users']
        buttons = [
            [InlineKeyboardButton(f"{username} ✅" if user_uuid in client_data[chat_id]['selected_users'] else username,
                                  callback_data=f"user_{user_uuid}")]
            for username, user_uuid in users
        ]
        buttons.append([InlineKeyboardButton("Готово", callback_data='done_selecting_users')])
        buttons.append([InlineKeyboardButton("Отменить", callback_data='cancel')])

        await query.edit_message_text(
            "Выберите пользователей (можно выбрать несколько, затем нажмите 'Готово'):",
            reply_markup=InlineKeyboardMarkup(buttons)
        )

        return SELECT_USERS

async def select_extensions(update: Update, context: CallbackContext) -> int:
    query = update.callback_query
    await query.answer()
    chat_id = query.message.chat_id
    extension_uuid = query.data.split('_')[1]

    if query.data == 'done_selecting_extensions':
        selected_users = client_data[chat_id]['selected_users']
        selected_extensions = client_data[chat_id]['selected_extensions']

        user_display = ', '.join(selected_users)
        extension_display = ', '.join(selected_extensions)

        await query.edit_message_text(
            f"Вы выбрали следующие данные:\n\nПользователи: {user_display}\nВнутренние номера: {extension_display}\n\nСохранить?",
            reply_markup=InlineKeyboardMarkup([
                [InlineKeyboardButton("Да", callback_data='confirm')],
                [InlineKeyboardButton("Нет", callback_data='cancel')]
            ])
        )
        return CONFIRM
    else:
        if extension_uuid in client_data[chat_id]['selected_extensions']:
            client_data[chat_id]['selected_extensions'].remove(extension_uuid)
        else:
            client_data[chat_id]['selected_extensions'].append(extension_uuid)

        domain_uuid = client_data[chat_id]['domain_uuid']
        extensions = get_extensions(domain_uuid)

        buttons = [
            [InlineKeyboardButton(f"{extension} ✅" if extension_uuid in client_data[chat_id]['selected_extensions'] else extension,
                                  callback_data=f"extension_{extension_uuid}")]
            for extension, extension_uuid in extensions
        ]
        buttons.append([InlineKeyboardButton("Готово", callback_data='done_selecting_extensions')])
        buttons.append([InlineKeyboardButton("Отменить", callback_data='cancel')])

        await query.edit_message_text(
            "Выберите внутренние номера (можно выбрать несколько, затем нажмите 'Готово'):",
            reply_markup=InlineKeyboardMarkup(buttons)
        )

        return SELECT_EXTENSIONS

async def confirm_selection(update: Update, context: CallbackContext) -> int:
    query = update.callback_query
    await query.answer()
    chat_id = query.message.chat_id

    if query.data == 'confirm':
        unique_id = client_data[chat_id]['unique_id']
        domain_uuid = client_data[chat_id]['domain_uuid']
        selected_users = client_data[chat_id]['selected_users']
        selected_extensions = client_data[chat_id]['selected_extensions']

        insert_data_to_db(unique_id, domain_uuid, selected_users, selected_extensions)

        added_users = set()
        added_extensions = set()

        for user_uuid in selected_users:
            for extension_uuid in selected_extensions:
                username = get_username(user_uuid)
                extension = get_extension(extension_uuid)
                
                added_users.add(username)
                added_extensions.add(extension)

        added_users_str = ", ".join(added_users)
        added_extensions_str = ", ".join(added_extensions)
        confirmation_message = (f"Данные успешно сохранены для заявки {unique_id}!\n"
                                f"Пользователи: {added_users_str}\n"
                                f"Внутренние номера: {added_extensions_str}\n\n"
                                "Пожалуйста, введите одну из команд:\n\n"
                                "/add_client - добавить нового клиента\n"
                                "/assign_extensions - добавить пользователя в сип\n"
                                "/record - найти запись по номеру.")
        await query.edit_message_text(confirmation_message)
    else:
        await query.edit_message_text("Операция отменена.\n\nПожалуйста, введите одну из команд:\n\n"
                                      "/add_client - добавить нового клиента\n"
                                      "/assign_extensions - добавить пользователя в сип\n"
                                      "/record - найти запись по номеру.")

    return START

#==========================================================================================================================================
#=====================================================================(/ADD_CLIENT)========================================================
#==========================================================================================================================================
async def company(update: Update, context: CallbackContext) -> int:
    chat_id = update.message.chat_id
    client_data[chat_id]['company'] = update.message.text
    await update.message.reply_text(
        "Нужен мультисип или же несколько сип аккаунтов?",
        reply_markup=InlineKeyboardMarkup([
            [InlineKeyboardButton("Мультисип", callback_data='multysip')],
            [InlineKeyboardButton("Несколько сип аккаунтов", callback_data='several_sip')],
            [InlineKeyboardButton("Отменить", callback_data='cancel')]
        ])
    )
    return SIP_TYPE

async def sip_type(update: Update, context: CallbackContext) -> int:
    query = update.callback_query
    await query.answer()
    chat_id = query.message.chat_id
    client_data[chat_id]['sip_type'] = query.data

    if query.data == 'several_sip':
        await query.edit_message_text("Сколько сип аккаунтов нужно создать?",
                    reply_markup=InlineKeyboardMarkup([
                [InlineKeyboardButton("Отменить", callback_data='cancel')]
            ])
        )
        return SIP_QUANTITY
    else:
        await query.edit_message_text("Теперь предоставьте, пожалуйста, IP клиента:",
                    reply_markup=InlineKeyboardMarkup([
                [InlineKeyboardButton("Отменить", callback_data='cancel')]
            ])
        )
        return IP_ADDRESS

async def sip_quantity(update: Update, context: CallbackContext) -> int:
    chat_id = update.message.chat_id
    client_data[chat_id]['sip_quantity'] = update.message.text
    await update.message.reply_text("Теперь предоставьте, пожалуйста, IP клиента:",
                reply_markup=InlineKeyboardMarkup([
                [InlineKeyboardButton("Отменить", callback_data='cancel')]
            ])
        )
    return IP_ADDRESS

async def ip_address(update: Update, context: CallbackContext) -> int:
    chat_id = update.message.chat_id
    username_user = update.message.from_user.username
    client_data[chat_id]['ip_address'] = update.message.text
    company = sanitize_company_name(client_data[chat_id]['company'])
    sip_type = client_data[chat_id]['sip_type']
    sip_quantity = int(client_data[chat_id].get('sip_quantity', 1))
    ip_address = client_data[chat_id]['ip_address']
    unique_id = client_data[chat_id]['unique_id']

    domain = f"{company}.voiceapp.sbs" if sip_type == 'several_sip' else "multysip.voiceapp.sbs"
    domain = domain.lower()
    username = company
    password = generate_password()

    client_prefix = get_next_client_prefix()

    if sip_type == 'multysip':
        internal_number = get_next_multysip_extension()
    else:
        internal_number = f"{client_prefix:03d}001"

    context_value = get_next_context_value(company, client_prefix)
    status = '?'
    dialplan_name = context_value

    insert_client_data(unique_id, username, password, 'user', domain, internal_number, dialplan_name, sip_quantity, context_value, client_prefix, ip_address, status, username_user)

    client_message = (
        f"ID заявки: {unique_id}\n"
        f"Компания: {username}\n"
        f"Количество сип аккаунтов: {sip_quantity}\n"
        f"IP клиента: {ip_address}\n"
        f"Ваш ник: {username_user}\n"
        f"Правильные ли данные?"
    )

    await update.message.reply_text(
        client_message,
        reply_markup=InlineKeyboardMarkup([
            [InlineKeyboardButton("Да", callback_data=f'client_yes_{unique_id}')],
            [InlineKeyboardButton("Отменить", callback_data=f'client_no_{unique_id}')]
        ])
    )
    return START

async def handle_client_button(update: Update, context: CallbackContext) -> int:
    query = update.callback_query
    await query.answer()
    choice, unique_id = query.data.rsplit('_', 1)
    logger.info(f"Пользователь выбрал: {choice} для заявки {unique_id}")

    if choice == 'client_yes':
        form = find_form_by_unique_id(unique_id)
        await context.bot.send_message(
            chat_id=NOTIFICATION_CHAT_ID,
            text=form,
            reply_markup=InlineKeyboardMarkup([
                [InlineKeyboardButton("Да", callback_data=f'admin_yes_{unique_id}')],
                [InlineKeyboardButton("Нет", callback_data=f'admin_no_{unique_id}')]
            ])
        )
        
        await query.edit_message_text("Одобрите пожалуйста заявку в чате.")

        await query.message.reply_text(
            "Заявка успешно обработана. Пожалуйста, введите одну из команд:\n\n"
            "/add_client - добавить нового клиента\n"
            "/assign_extensions - добавить пользователя в сип\n"
            "/record - найти запись по номеру."
        )
    elif choice == 'client_no':
        await query.edit_message_text("Заявка отклонена. Для начала нового действия введите ещё раз команду.")

        await query.message.reply_text(
            "Действие завершено. Пожалуйста, введите одну из команд:\n\n"
            "/add_client - добавить нового клиента\n"
            "/assign_extensions - добавить пользователя в сип\n"
            "/record - найти запись по номеру."
        )
    return START

async def handle_admin_button(update: Update, context: CallbackContext) -> int:
    query = update.callback_query
    await query.answer()
    choice, unique_id = query.data.rsplit('_', 1)

    if choice == 'admin_yes':
        url = f"https://{domainbot}/v_inf.php?id={unique_id}"
        update_status(unique_id, '-', 'f', f'Ваша заявка [{unique_id}]({url}) была одобрена! В скором времени предоставим вам данные.')
        await query.edit_message_text(f"Заявка [{unique_id}]({url}) обработана", parse_mode='Markdown')

    elif choice == 'admin_no':
        url = f"https://{domainbot}/v_inf.php?id={unique_id}" 
        update_status(unique_id, '?', '?', f'Ваша заявка [{unique_id}]({url}) была отклонена. Пожалуйста, свяжитесь с администратором для получения более подробной информации.')
        await query.edit_message_text(f"Заявка [{unique_id}]({url}) отклонена", parse_mode='Markdown')

    return START


async def periodic_check(context: CallbackContext):
    rows = check_v_read()
    for request_id, result_message in rows:
        chat_id = find_chat_id_by_unique_id(request_id)
        if chat_id:
            await context.bot.send_message(chat_id=chat_id, text=result_message, parse_mode='Markdown')

def find_chat_id_by_unique_id(unique_id):
    for chat_id, data in client_data.items():
        if data['unique_id'] == unique_id:
            return chat_id
    return None
#==========================================================================================================================================
#=====================================================================(Другая функция)=====================================================
#==========================================================================================================================================
def find_records(phone_numbers, start_date, end_date):
    results = {}
    for domain in os.listdir(BASE_DIR):
        domain_path = os.path.join(BASE_DIR, domain)
        if os.path.isdir(domain_path):
            records = []
            for root, dirs, files in os.walk(domain_path):
                for file in files:
                    if any(phone_number in file for phone_number in phone_numbers):
                        file_date_str = re.findall(r'\d{4}-\d{2}-\d{2}', file)
                        if file_date_str:
                            file_date = datetime.strptime(file_date_str[0], '%Y-%m-%d').date()
                            if start_date <= file_date <= end_date:
                                records.append(os.path.join(root, file))
            if records:
                results[domain] = records
    return results

async def handle_phone_number(update: Update, context: CallbackContext):
    phone_numbers_text = update.message.text
    phone_numbers = phone_numbers_text.split('\n') 
    context.user_data['phone_numbers'] = [number.strip() for number in phone_numbers if number.strip()]
    
    keyboard = [
        [InlineKeyboardButton("Сегодня", callback_data='today')],
        [InlineKeyboardButton("3 дня", callback_data='3_days')],
        [InlineKeyboardButton("Неделя", callback_data='week')],
        [InlineKeyboardButton("Месяц", callback_data='month')],
        [InlineKeyboardButton("Все время", callback_data='all_time')],
    ]
    reply_markup = InlineKeyboardMarkup(keyboard)
    await update.message.reply_text('Выберите временной период:', reply_markup=reply_markup)
    return RECORD

async def button(update: Update, context: CallbackContext):
    query = update.callback_query
    await query.answer()
    phone_numbers = context.user_data.get('phone_numbers')
    
    end_date = datetime.now().date()
    start_date = end_date
    
    if query.data == 'today':
        start_date = end_date
    elif query.data == '3_days':
        start_date = end_date - timedelta(days=3)
    elif query.data == 'week':
        start_date = end_date - timedelta(days=7)
    elif query.data == 'month':
        start_date = end_date - timedelta(days=30)
    elif query.data == 'all_time':
        start_date = datetime(1970, 1, 1).date()

    try:
        progress_message = await query.message.edit_text('Идёт поиск.')
    except telegram.error.BadRequest as e:
        if str(e) != "Message is not modified":
            raise

    for i in range(4):
        await asyncio.sleep(1)
        try:
            progress_text = f'Идёт поиск{"." * (i % 3 + 1)}{chr(8203)}'  # Добавляем невидимый символ
            await progress_message.edit_text(progress_text)
        except telegram.error.BadRequest as e:
            if str(e) != "Message is not modified":
                raise

    results = find_records(phone_numbers, start_date, end_date)
    
    if results:
        messages_to_delete = []
        
        for domain, records in results.items():
            domain_message = await query.message.reply_text(f'Домен: {domain}')
            messages_to_delete.append(domain_message.message_id)
            
            for record in records:
                with open(record, 'rb') as f:
                    record_message = await query.message.reply_document(f)
                    messages_to_delete.append(record_message.message_id)
    
    else:
        await query.message.reply_text('Записи не найдены.')

    await progress_message.delete()

    await query.message.reply_text(
        "Действие завершено. Пожалуйста, введите одну из команд:\n\n"
        "/add_client - добавить нового клиента\n"
        "/assign_extensions - добавить пользователя в сип\n"
        "/record - найти запись по номеру."
    )
    return START

    await asyncio.sleep(300)
        
    for message_id in messages_to_delete:
            try:
                await context.bot.delete_message(chat_id=query.message.chat_id, message_id=message_id)
            except telegram.error.BadRequest as e:
                if str(e) != "Message to delete not found":
                    raise

    return START

def main():
    application = Application.builder().token(TOKEN).build()

    conv_handler = ConversationHandler(
    entry_points=[CommandHandler('start', start)],
    states={
        START: [
            CommandHandler('add_client', start_add_client),
            CommandHandler('assign_extensions', start_assign_extension),
            CommandHandler('record', start_record),
            MessageHandler(filters.TEXT & ~filters.COMMAND, handle_text_message),
        ],
        COMPANY: [MessageHandler(filters.TEXT & ~filters.COMMAND, company),
                  CallbackQueryHandler(cancel, pattern='^cancel$')],
        SIP_TYPE: [CallbackQueryHandler(sip_type),
                   CallbackQueryHandler(cancel, pattern='^cancel$')],
        SIP_QUANTITY: [MessageHandler(filters.TEXT & ~filters.COMMAND, sip_quantity),
                       CallbackQueryHandler(cancel, pattern='^cancel$')],
        IP_ADDRESS: [MessageHandler(filters.TEXT & ~filters.COMMAND, ip_address),
                     CallbackQueryHandler(cancel, pattern='^cancel$')],
        PROCESS_SELECTION: [CommandHandler('add_client', start_add_client),
                            CommandHandler('assign_extensions', start_assign_extension),
                            CommandHandler('record', start_record)],
        SELECT_DOMAIN: [CallbackQueryHandler(select_domain, pattern='^domain_'),
                        CallbackQueryHandler(cancel, pattern='^cancel$')],
        SELECT_USERS: [CallbackQueryHandler(select_users, pattern='^user_|^done_selecting_users'),
                       CallbackQueryHandler(cancel, pattern='^cancel$')],
        SELECT_EXTENSIONS: [CallbackQueryHandler(select_extensions, pattern='^extension_|^done_selecting_extensions'),
                            CallbackQueryHandler(cancel, pattern='^cancel$')],
        CONFIRM: [CallbackQueryHandler(confirm_selection, pattern='^confirm|^cancel')],
        RECORD: [MessageHandler(filters.TEXT & ~filters.COMMAND, handle_phone_number),
                 CallbackQueryHandler(button, pattern='^today|^3_days|^week|^month|^all_time$'),
                 CallbackQueryHandler(cancel, pattern='^cancel$')],
    },
    fallbacks=[]
)

    application.add_handler(conv_handler)   
    application.add_handler(CallbackQueryHandler(handle_client_button, pattern='^client_'))
    application.add_handler(CallbackQueryHandler(handle_admin_button, pattern='^admin_'))

    job_queue = application.job_queue
    job_queue.run_repeating(periodic_check, interval=20, first=20)

    application.run_polling()

if __name__ == '__main__':
    main()

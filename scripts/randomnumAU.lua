-- Определяем список номеров
local numbers = {
    "61485985046",
    "61485985062",
    "61485985023",
    "61485985075",
    "61485985002"
}

-- Функция для генерации случайного числа в диапазоне от 1 до длины списка
local function get_random_number()
    math.randomseed(os.time())  -- Инициализация генератора случайных чисел
    return numbers[math.random(1, #numbers)]
end

-- Получаем рандомный номер
local random_number = get_random_number()

-- Устанавливаем Caller ID в соответствии с рандомным номером
session:setVariable("origination_caller_id_number", random_number)-- Определяем список номеров
local numbers = {
    "61485985046",
    "61485985062",
    "61485985023",
    "61485985075",
    "61485985002"
}

-- Функция для генерации случайного числа в диапазоне от 1 до длины списка
local function get_random_number()
    math.randomseed(os.time())  -- Инициализация генератора случайных чисел
    return numbers[math.random(1, #numbers)]
end

-- Получаем рандомный номер
local random_number = get_random_number()

-- Устанавливаем Caller ID в соответствии с рандомным номером
session:setVariable("calld_id", random_number)

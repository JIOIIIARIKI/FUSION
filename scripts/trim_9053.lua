require "resources.functions.config";

digit_timeout = "5000";

require "resources.functions.file_exists"

destination_number = session:getVariable("destination_number");

-- Обрезаем последние 6 цифр
destination_number = string.sub(destination_number, 0, -7);

-- Проверяем, начинается ли номер с 9053 после обрезки
if string.sub(destination_number, 1, 4) == "9053" then
    -- Заменяем 9053 на 9055
    destination_number = "9055" .. string.sub(destination_number, 5);
end

session:setVariable("destination_number", destination_number);
